<?php

namespace App\Services;

use App\Models\InspectionSession;
use App\Models\InspectionLog;
use App\Models\Department;
use App\Models\Checkpoint;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use App\Mail\InspectionSessionStarted;
use App\Mail\InspectionSessionFinished;

class InspectionService
{
    /**
     * Start or Retrieve an active Inspection Session.
     */
    public function startSession(User $user, int $departmentId, string $type, bool $forceNew = false, string $manualShift = null, bool $isSampling = false, ?int $sampleSize = null): InspectionSession
    {
        // Personnel rounds must name the shift the inspector picked. Falling back to a
        // time-guessed generic key ('morning') makes the session target every shift of that
        // type at once, which bulk pass then sweeps in one click.
        if ($type === 'personnel' && empty($manualShift)) {
            throw ValidationException::withMessages([
                'shift' => 'กรุณาเลือกกะที่ต้องการตรวจก่อนเริ่มการตรวจ',
            ]);
        }

        $shift = $manualShift ?? \App\Models\Shift::detectCurrent();
        $today = now()->hour < 6 ? now()->subDay()->toDateString() : now()->toDateString();

        $session = DB::transaction(function () use ($user, $departmentId, $type, $today, $shift, $forceNew, $isSampling, $sampleSize) {
            $session = InspectionSession::where('department_id', $departmentId)
                ->whereDate('inspection_date', $today)
                ->where('shift', $shift)
                ->where('type', $type)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($session && in_array($session->status, ['in_progress', 'paused'], true)) {
                if ($forceNew) {
                    $session->update(['status' => 'completed']);
                    // Fall through to create a new round below
                } else {
                    if ($session->status === 'paused') {
                        $session->update(['status' => 'in_progress']);
                    }
                    if ($isSampling) {
                        $session->update(['is_sampling' => true, 'sample_size' => $sampleSize]);
                    }
                    return $session;
                }
            }

            // "ตรวจต่อ": pick the finished round back up rather than opening a fresh one that
            // re-lists every target already covered. The bulk checklist scopes "already
            // inspected" to session_id, so staying on the same session is what makes the
            // earlier work show as done. A round the supervisor has already reviewed is
            // closed for good — that one always starts a new round.
            if ($session && $session->status === 'completed' && ! $forceNew && $this->canReopen($session)) {
                $session->update(['status' => 'in_progress']);
                if ($isSampling) {
                    $session->update(['is_sampling' => true, 'sample_size' => $sampleSize]);
                }

                return $session;
            }

            // A NEW personnel round must name the shift cards the inspector picked
            // ('custom_11'), never a generic key. A generic key spans every shift of its type,
            // which is how one "ผ่านทุกคนที่เหลือ" swept three shift groups at once. Checked
            // here rather than at the top so rounds already stored with a generic key stay
            // resumable above.
            if ($type === 'personnel') {
                $tokens = array_filter(array_map('trim', explode(',', (string) $shift)));
                $generic = array_filter($tokens, fn ($t) => ! preg_match('/^custom_\d+$/', $t));

                if (! empty($generic)) {
                    throw ValidationException::withMessages([
                        'shift' => 'กรุณาเลือกกะที่ต้องการตรวจจากรายการกะก่อนเริ่มการตรวจ',
                    ]);
                }
            }

            $nextRound = $session ? $session->round + 1 : 1;

            return InspectionSession::create([
                'department_id' => $departmentId,
                'inspection_date' => $today,
                'shift' => $shift,
                'inspector_id' => $user->id,
                'status' => 'in_progress',
                'type' => $type,
                'round' => $nextRound,
                'is_sampling' => $isSampling,
                'sample_size' => $sampleSize,
            ]);
        });

        if ($session->wasRecentlyCreated) {
            $this->notifySupervisorsStarted($session);
        }

        return $session;
    }

    /**
     * Finish a session (Inspector Done).
     */
    /**
     * A finished round can be picked back up with "ตรวจต่อ" unless the supervisor has
     * already reviewed it — reopening a reviewed round would change what was signed off.
     */
    public function canReopen(InspectionSession $session): bool
    {
        return $session->status === 'completed'
            && ! $session->isLocked()
            && $session->verified_at === null
            && $session->approved_at === null
            // The live QA flow stamps verification on the LOGS, never on the session row
            // (InspectionController::verify()), so the session columns above stay null even
            // for a fully signed-off round. The logs are what actually says it was reviewed.
            && ! $session->logs()->whereNotNull('verified_at')->exists();
    }

    public function finishSession(InspectionSession $session): void
    {
        if ($session->status === 'completed') {
            return; // Already done
        }

        // A reopened round is finished more than once. The announcements below — supervisor
        // bell, LINE summary — belong to the round, so they go out once; a second one reads
        // as a second round. What DOES have to get through on a later finish is anything the
        // continuation newly produced, so the CAR summary and the escalation are handled on
        // their own terms rather than under this flag.
        $announcedAt = $session->finished_notified_at;
        $alreadyAnnounced = $announcedAt !== null;

        $session->update([
            'status' => 'completed',
            'finished_notified_at' => $announcedAt ?? now(),
        ]);

        // Auto-Escalation for Random Audits.
        // A control, not an announcement: the fail rate may only cross the threshold on a
        // continuation, and that round still has to escalate. escalated_at keeps it to once.
        if ($session->is_sampling && $session->audit_escalated_at === null) {
            $targetColumn = 'employee_id';
            if ($session->type === 'machine') {
                $targetColumn = 'machine_id';
            } elseif ($session->type === 'area') {
                $targetColumn = 'location_id';
            }

            $totalInspected = $session->logs()->distinct($targetColumn)->count($targetColumn);
            if ($totalInspected > 0) {
                // Count targets who have at least one fail log
                $failedTargetsCount = $session->logs()
                    ->where('result', 'fail')
                    ->distinct($targetColumn)
                    ->count($targetColumn);

                $failRate = ($failedTargetsCount / $totalInspected) * 100;
                // If fail rate > 20%, trigger auto-escalation
                if ($failRate > 20) {
                    $this->triggerRandomAuditEscalation($session, $failRate, $failedTargetsCount, $totalInspected);
                    $session->update(['audit_escalated_at' => now()]);
                }
            }
        }

        if ($alreadyAnnounced) {
            // Managers hear about CARs only here, never per-CAR while inspecting, so anything
            // the continuation opened would otherwise reach nobody. Scope it to those.
            $this->notifyManagersOfSessionCars($session, $announcedAt);

            return;
        }

        $this->notifySupervisorsFinished($session);
        // Fix #7: One consolidated bell entry for all CARs opened this session.
        $this->notifyManagersOfSessionCars($session);

        // --- LINE Notification Integration ---
        try {
            $stats = [
                'total' => $session->logs()->count(),
                'pass' => $session->logs()->where('result', 'pass')->count(),
                'fail' => $session->logs()->where('result', 'fail')->count(),
            ];
            $randomAssigned = $session->logs()->whereNull('verification_status')->count();
            
            \Illuminate\Support\Facades\Notification::route(\App\Channels\LineMessagingChannel::class, '')
                ->notify(new \App\Notifications\SessionSummaryLineNotification($session, $stats, $randomAssigned));
        } catch (\Exception $e) {
            \Log::error('LINE Notify Error on Finish Session: ' . $e->getMessage());
        }
    }

    /**
     * Fix #7: Send ONE SessionCarsSummaryNotification per manager listing every
     * auto-CAR opened during this session. Silent if no CARs. Called from
     * finishSession() and from the area/machine auto-complete path.
     */
    /**
     * @param \Illuminate\Support\Carbon|null $since Only summarise CARs opened after this,
     *        so a round finished again after "ตรวจต่อ" reports what the continuation found
     *        instead of repeating the whole round.
     */
    public function notifyManagersOfSessionCars(InspectionSession $session, $since = null): void
    {
        // Fix #10: Eager-load the log's machine and location so the mapper below
        // doesn't fire a query per CAR just to build a place label.
        $cars = \App\Models\CorrectiveAction::whereHas('log', function ($q) use ($session) {
            $q->where('session_id', $session->id);
        })
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->with(['log.machine', 'log.location', 'log.employee'])->get();

        if ($cars->isEmpty()) {
            return;
        }

        $summaries = $cars->map(function ($car) {
            $log = $car->log;
            return [
                'car_id' => $car->id,
                'checkpoint' => $log?->checkpoint_title_snapshot ?? 'ไม่ระบุจุดตรวจ',
                'place' => $log?->employee ? $log->employee->fullname : (
                    $log?->machine?->name ?? $log?->location?->location_name ?? 'ไม่ระบุพื้นที่/บุคคล'
                ),
            ];
        })->all();

        $managers = \App\Models\User::where('department_id', $session->department_id)
            ->where('level', '>=', 5)
            ->get();

        foreach ($managers as $manager) {
            $manager->notify(new \App\Notifications\SessionCarsSummaryNotification($session, $summaries));
        }
    }

    /**
     * Trigger auto-escalation for failed random audits.
     * Creates a Re-check session for the entire department and notifies managers.
     */
    protected function triggerRandomAuditEscalation(InspectionSession $session, float $failRate, int $failedTargetsCount, int $totalInspected): void
    {
        // 1. Create a Re-check InspectionSession for the entire department (NOT sampling)
        $recheckSession = InspectionSession::create([
            'department_id' => $session->department_id,
            'inspection_date' => now()->toDateString(),
            'shift' => $session->shift,
            'inspector_id' => $session->inspector_id, // Assigned to the same QA Sup initially, or null if it should be unassigned
            'status' => 'in_progress',
            'type' => $session->type,
            'round' => $session->round + 1,
            'is_sampling' => false,
            'sample_size' => null,
            'is_audit' => false,
        ]);

        // 2. Determine recipients: Department Managers (Level 5+) and QA Managers
        $managers = \App\Models\User::where('department_id', $session->department_id)
            ->where('level', '>=', 5)
            ->get();
            
        $qaManagers = \App\Models\User::where('role', 'manager')
            ->whereHas('department', function($q) {
                $q->where('dept_code', 'QA');
            })
            ->get();
            
        $recipients = $managers->merge($qaManagers)->unique('id');
        $emails = $recipients->pluck('email')->filter()->toArray();

        // 3. Send Email
        if (count($emails) > 0) {
            \Illuminate\Support\Facades\Mail::bcc($emails)->send(new \App\Mail\RandomAuditEscalationMail($session, $recheckSession, $failRate, $failedTargetsCount, $totalInspected));
        }
        
        // 4. Send In-App Notification
        foreach ($recipients as $recipient) {
            $recipient->notify(new \App\Notifications\RandomAuditEscalationNotification($session, $recheckSession, $failRate, $failedTargetsCount, $totalInspected));
        }
        
        \Log::info("Random Audit Auto-Escalation triggered for Session {$session->id}. Fail Rate: {$failRate}%. Recheck Session {$recheckSession->id} created.");
    }
    /**
     * Auto-close inspection sessions left open past their shift end + grace period.
     * Idempotent and fail-safe. Returns the number of sessions closed.
     */
    public function autoCloseStaleSessions(): int
    {
        if (! config('inspection.auto_close.enabled', true)) {
            return 0;
        }

        $sessions = InspectionSession::whereIn('status', ['in_progress', 'paused'])
            ->where('is_locked', false)
            ->get();

        $closed = 0;
        foreach ($sessions as $session) {
            if (! $this->isStale($session)) {
                continue;
            }

            $this->finishSession($session);

            \App\Models\ActivityLog::create([
                'user_id'     => null, // system actor
                'action'      => 'auto_close',
                'model_type'  => InspectionSession::class,
                'model_id'    => $session->id,
                'description' => 'ปิดรอบอัตโนมัติโดยระบบ (เลยกะ ' . $session->shift . ' + '
                    . config('inspection.auto_close.grace_hours', 2) . ' ชม. และไม่มีการตรวจใน '
                    . config('inspection.auto_close.idle_minutes', 30) . ' นาที)',
            ]);

            $closed++;
        }

        return $closed;
    }

    public function isStale(InspectionSession $session): bool
    {
        if ($session->isLocked() || ! in_array($session->status, ['in_progress', 'paused'], true)) {
            return false;
        }

        $shiftEnd = $this->shiftEndAt($session);
        if ($shiftEnd === null) {
            return false; // fail-safe: unknown shift end -> never auto-close
        }

        $graceHours = (float) config('inspection.auto_close.grace_hours', 2);
        if (now()->lt($shiftEnd->copy()->addHours($graceHours))) {
            return false; // still within shift + grace
        }

        $idleMinutes = (int) config('inspection.auto_close.idle_minutes', 30);
        if ($this->lastActivityAt($session)->gt(now()->copy()->subMinutes($idleMinutes))) {
            return false; // recent activity -> defer close
        }

        return true;
    }

    public function shiftEndAt(InspectionSession $session): ?\Illuminate\Support\Carbon
    {
        // Resolve through the session itself so a picked shift card ('custom_11') works, not
        // just the generic keys. Matching shift_name literally missed every real row, which
        // left shiftEndAt() null and personnel sessions never auto-closing.
        $shifts = $session->getResolvedShifts()['shifts'];
        if ($shifts->isEmpty()) {
            return null;
        }

        $date = \Illuminate\Support\Carbon::parse($session->inspection_date)->toDateString();
        $latest = null;

        // A session may span several shifts ("custom_4,custom_9"); it is only over once the
        // last of them has ended.
        foreach ($shifts as $shift) {
            if (empty($shift->end_time)) {
                continue;
            }

            $end = \Illuminate\Support\Carbon::parse($date . ' ' . $shift->end_time);

            // Shift wraps past midnight (e.g. 17:00-02:00): end lands on the next day
            if (! empty($shift->start_time) && $shift->end_time <= $shift->start_time) {
                $end->addDay();
            }

            if ($latest === null || $end->gt($latest)) {
                $latest = $end;
            }
        }

        return $latest;
    }

    public function lastActivityAt(InspectionSession $session): \Illuminate\Support\Carbon
    {
        $lastLog = InspectionLog::where('session_id', $session->id)->max('created_at');
        if ($lastLog) {
            return \Illuminate\Support\Carbon::parse($lastLog);
        }

        return \Illuminate\Support\Carbon::parse($session->updated_at ?? $session->created_at ?? now());
    }

    protected function notifySupervisorsStarted(InspectionSession $session): void
    {
        try {
            $supervisors = User::with('department')
                ->where(function($q) {
                    $q->where('role', 'supervisor')->orWhere('level', 4);
                })
                ->get()
                ->filter(fn($u) => $u->isQA());

            $emails = [];
            foreach ($supervisors as $supervisor) {
                // Send in-app database notification individually
                $supervisor->notify(new \App\Notifications\InspectionStartedNotification($session));
                if ($supervisor->email && $supervisor->wantsEmailFor('email_session_started')) {
                    $emails[] = $supervisor->email;
                }
            }

            // Send ONE grouped email to all supervisors
            if (count($emails) > 0) {
                \Illuminate\Support\Facades\Mail::bcc($emails)->send(new \App\Mail\InspectionSessionStarted($session));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send Inspection Started email: ' . $e->getMessage());
        }
    }

    protected function notifySupervisorsFinished(InspectionSession $session): void
    {
        try {
            $supervisors = User::with('department')
                ->where(function($q) {
                    $q->where('role', 'supervisor')->orWhere('level', 4);
                })
                ->get()
                ->filter(fn($u) => $u->isQA());

            $targetColumn = 'employee_id';
            $unit = 'คน';
            if ($session->type === 'machine') {
                $targetColumn = 'machine_id';
                $unit = 'เครื่อง';
            } elseif ($session->type === 'area') {
                $targetColumn = 'location_id';
                $unit = 'พื้นที่';
            }
            
            $totalTargets = $session->logs()->distinct($targetColumn)->count($targetColumn);
            $failedTargets = $session->logs()->where('result', 'fail')->distinct($targetColumn)->count($targetColumn);
            $passedTargets = $totalTargets - $failedTargets;

            $stats = [
                'total' => $session->logs()->count(),
                'pass' => $session->logs()->where('result', 'pass')->count(),
                'fail' => $session->logs()->where('result', 'fail')->count(),
                'total_targets' => $totalTargets,
                'passed_targets' => $passedTargets,
                'failed_targets' => $failedTargets,
                'unit' => $unit
            ];

            // Determine which event type this is
            $eventName = $stats['fail'] > 0 ? 'email_session_finished_fail' : 'email_session_finished_pass';

            $emails = [];
            foreach ($supervisors as $supervisor) {
                // Send in-app database notification individually if needed
                // $supervisor->notify(new \App\Notifications\InspectionFinishedNotification($session));
                if ($supervisor->email && $supervisor->wantsEmailFor($eventName)) {
                    $emails[] = $supervisor->email;
                }
            }

            // Send ONE grouped email to all supervisors
            if (count($emails) > 0) {
                \Illuminate\Support\Facades\Mail::bcc($emails)->send(new \App\Mail\InspectionSessionFinished($session, $stats));
            }
            
            \Log::info("Session {$session->id} finished emails sent to QA Supervisors.");
        } catch (\Exception $e) {
            \Log::error('Failed to process Inspection Finished event: ' . $e->getMessage());
        }
    }

    /**
     * Verify a session (Supervisor).
     */
    public function verifySession(InspectionSession $session, User $verifier): void
    {
        if (!$verifier->can('verify')) {
             throw new \Exception("User not authorized to verify.");
        }

        // Logic: Cannot verify if there are pending re-cleans?
        // Or specific logic from controller. 
        // For now, we update the session level strictly.
        
        $session->update([
            'verified_by' => $verifier->id,
            'verified_at' => now(),
            // Status could arguably translate to 'Verified' state if we added a specific column,
            // but currently system uses verified_at not null.
        ]);
        
        // Also bulk approve logs if implementation requires it? 
        // Current controller logic seems to verified individual groups of logs. 
        // We will keep session-level verification simple.
    }

    /**
     * Approve a session (Manager). This Finalizes and Locks.
     */
    public function approveSession(InspectionSession $session, User $approver): void
    {
        if (!$approver->can('approve')) {
             throw new \Exception("User not authorized to approve.");
        }

        DB::transaction(function () use ($session, $approver) {
            $session->update([
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'is_locked' => true,
                'locked_at' => now(),
            ]);

            // Optional: Bulk approve all logs inside?
            // InspectionLog::where('session_id', $session->id)->update(['verification_status' => 'approved']);
        });
    }

    /**
     * Store a Log Entry (Single Checkpoint).
     */
    public function storeLog(InspectionSession $session, int $employeeId, int $checkpointId, array $data, ?string $photoPath = null): InspectionLog
    {
        if ($session->is_locked) {
            throw ValidationException::withMessages(['session' => 'Session is Locked.']);
        }

        // Check Completion Lock (unless fixing reclean)
        if ($session->status === 'completed') {
             $isRecleanFix = InspectionLog::where('session_id', $session->id)
                ->where('employee_id', $employeeId)
                ->where('checkpoint_id', $checkpointId)
                ->where('verification_status', 'reclean')
                ->exists();
                
             if (!$isRecleanFix) {
                 throw ValidationException::withMessages(['session' => 'Session is Completed.']);
             }
        }

        return DB::transaction(function () use ($session, $employeeId, $checkpointId, $data, $photoPath) {
            $logData = [
                'session_id' => $session->id,
                'employee_id' => $employeeId,
                'checkpoint_id' => $checkpointId,
                'result' => $data['result'],
                'correction_action' => $data['correction'] ?? null,
                'inspected_at' => now(),
                'dept_snapshot' => $session->department->dept_name ?? 'N/A',
            ];

            if ($photoPath) {
                $logData['photo_path'] = $photoPath;
            }

            // Loop Engineering: Auto-CAR triggers when fail
            if ($logData['result'] === 'fail') {
                $logData['verification_status'] = 'reclean';
            }

            $pendingLog = InspectionLog::where('employee_id', $employeeId)
                ->where('checkpoint_id', $checkpointId)
                ->where('verification_status', 'reclean')
                ->whereDoesntHave('rechecks')
                ->whereHas('session', fn($q) => $q->where('department_id', $session->department_id))
                ->latest()
                ->first();

            if ($pendingLog) {
                $logData['verification_status'] = null;
                $logData['verified_at'] = null;
                $logData['verifier_id'] = null;
                $logData['verification_comment'] = null;

                $isSameSessionRow = $pendingLog->session_id === $session->id;

                if (!$isSameSessionRow) {
                    $logData['parent_id'] = $pendingLog->id;
                    return InspectionLog::create($logData);
                }
            }

            $log = InspectionLog::updateOrCreate(
                [
                    'session_id' => $session->id,
                    'employee_id' => $employeeId,
                    'checkpoint_id' => $checkpointId,
                ],
                $logData
            );

            // Loop Engineering: Auto-CAR creation.
            // Fix #7: We used to notify managers per-CAR, which flooded the bell during a
            // normal shift. The per-CAR notification is now suppressed for AUTO-created CARs;
            // a single SessionCarsSummaryNotification fires when the session completes
            // (see notifyManagersOfSessionCars). Manual escalations from
            // CorrectiveActionController keep their own per-CAR notification because they
            // are explicit high-priority actions.
            if ($log->result === 'fail') {
                \App\Models\CorrectiveAction::firstOrCreate([
                    'inspection_log_id' => $log->id,
                ], [
                    'status' => 'open',
                    'escalated_by' => $session->inspector_id,
                    'root_cause' => $log->correction_action ?? 'ระบบสั่งแก้ไขอัตโนมัติ เนื่องจากผลการตรวจไม่ผ่าน',
                    'due_date' => now()->addHours(24),
                ]);
            }

            return $log;
        });
    }

    /**
     * Bulk Pass: Create "pass" logs for all remaining (uninspected) employees in a session.
     * Only targets employees whose shift matches the session's shift.
     *
     * @return int Number of employees bulk-passed
     */
    public function bulkPassRemaining(InspectionSession $session): int
    {
        if ($session->is_locked) {
            throw ValidationException::withMessages(['session' => 'Session is Locked.']);
        }

        if (!in_array($session->status, ['in_progress', 'paused'], true)) {
            throw ValidationException::withMessages(['session' => 'Session must be in-progress or paused.']);
        }

        if ($session->type !== 'personnel') {
            throw ValidationException::withMessages(['session' => 'Bulk Pass is only available for personnel inspections.']);
        }

        if ($session->is_sampling) {
            throw ValidationException::withMessages(['session' => 'Bulk Pass is disabled during Random Audits. Please inspect employees individually.']);
        }

        return DB::transaction(function () use ($session) {
            // 1. Get all active personnel checkpoints (global fallback)
            $globalCheckpointIds = Checkpoint::where('type', 'person')
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            if (empty($globalCheckpointIds)) {
                return 0;
            }

            // 2. Get employees in this department + matching shift (Shift Filtering using getTargetEmployees)
            $targetEmployees = $session->getTargetEmployees();
            $targetEmployeeIds = $targetEmployees->pluck('id')->toArray();

            if (empty($targetEmployeeIds)) {
                return 0;
            }

            // 3. Get already-inspected employee IDs in this session
            $inspectedIds = InspectionLog::where('session_id', $session->id)
                ->whereIn('employee_id', $targetEmployeeIds)
                ->pluck('employee_id')
                ->unique()
                ->toArray();

            // 4. Find remaining (uninspected) employees
            $remainingIds = array_diff($targetEmployeeIds, $inspectedIds);

            if (empty($remainingIds)) {
                return 0;
            }

            // 5. Build bulk insert data - respecting per-employee checkpoint assignments
            $now = now();
            $deptSnapshot = $session->department->dept_name ?? 'N/A';
            $bulkData = [];

            // Pre-load employees with their checkpoints and locations for efficiency
            $remainingEmployees = Employee::with(['checkpoints', 'location'])
                ->whereIn('id', $remainingIds)
                ->get()
                ->keyBy('id');

            foreach ($remainingIds as $employeeId) {
                $emp = $remainingEmployees->get($employeeId);

                // Priority 1: Employee-specific checkpoints (employee_checkpoint)
                $empCheckpointIds = [];
                if ($emp) {
                    $empCheckpointIds = $emp->checkpoints
                        ->where('is_active', true)
                        ->where('type', 'person')
                        ->pluck('id')
                        ->toArray();
                }

                // Priority 2: Location checkpoints (location_checkpoint)
                if (empty($empCheckpointIds) && $emp?->location) {
                    $empCheckpointIds = $emp->location->checkpoints()
                        ->where('is_active', true)
                        ->where('type', 'person')
                        ->pluck('checkpoints.id')
                        ->toArray();
                }

                // Priority 3: All active checkpoints (fallback)
                if (empty($empCheckpointIds)) {
                    $empCheckpointIds = $globalCheckpointIds;
                }

                foreach ($empCheckpointIds as $checkpointId) {
                    $bulkData[] = [
                        'session_id' => $session->id,
                        'employee_id' => $employeeId,
                        'checkpoint_id' => $checkpointId,
                        'result' => 'pass',
                        'inspected_at' => $now,
                        'dept_snapshot' => $deptSnapshot,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // 6. Bulk insert in chunks of 500 to avoid memory issues
            foreach (array_chunk($bulkData, 500) as $chunk) {
                InspectionLog::insert($chunk);
            }

            \Log::info('Bulk Pass executed', [
                'session_id' => $session->id,
                'inspector_id' => $session->inspector_id,
                'employees_passed' => count($remainingIds),
                'logs_created' => count($bulkData),
            ]);

            return count($remainingIds);
        });
    }
}
