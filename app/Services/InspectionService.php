<?php

namespace App\Services;

use App\Models\InspectionSession;
use App\Models\InspectionLog;
use App\Models\Department;
use App\Models\Checkpoint;
use App\Models\User;
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
    public function startSession(User $user, int $departmentId, string $type, bool $forceNew = false, string $manualShift = null): InspectionSession
    {
        $shift = $manualShift ?? \App\Models\Shift::detectCurrent();
        $today = now()->toDateString();

        $session = DB::transaction(function () use ($user, $departmentId, $type, $today, $shift, $forceNew) {
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
                    return $session;
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
    public function finishSession(InspectionSession $session): void
    {
        if ($session->status === 'completed') {
            return; // Already done
        }

        // Apply Random Verification Logic before completing
        $this->applyRandomVerification($session);

        $session->update([
            'status' => 'completed',
        ]);

        $this->notifySupervisorsFinished($session);
        // Fix #7: One consolidated bell entry for all CARs opened this session.
        $this->notifyManagersOfSessionCars($session);
    }

    /**
     * Fix #7: Send ONE SessionCarsSummaryNotification per manager listing every
     * auto-CAR opened during this session. Silent if no CARs. Called from
     * finishSession() and from the area/machine auto-complete path.
     */
    public function notifyManagersOfSessionCars(InspectionSession $session): void
    {
        // Fix #10: Eager-load the log's machine and location so the mapper below
        // doesn't fire a query per CAR just to build a place label.
        $cars = \App\Models\CorrectiveAction::whereHas('log', function ($q) use ($session) {
            $q->where('session_id', $session->id);
        })->with(['log.machine', 'log.location'])->get();

        if ($cars->isEmpty()) {
            return;
        }

        $summaries = $cars->map(function ($car) {
            $log = $car->log;
            return [
                'car_id' => $car->id,
                'checkpoint' => $log?->checkpoint_title_snapshot ?? 'ไม่ระบุจุดตรวจ',
                'place' => $log?->machine?->name
                    ?? $log?->location?->location_name
                    ?? 'ไม่ระบุพื้นที่',
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
     * Apply Automated Random Verification.
     * Auto-verify 90% of passed targets, leaving 10% (and all failed targets) for manual verification.
     */
    protected function applyRandomVerification(InspectionSession $session): void
    {
        $logs = \App\Models\InspectionLog::where('session_id', $session->id)
                    ->whereNull('verification_status')
                    ->get();
        
        if ($logs->isEmpty()) return;

        // Group logs by target (employee, machine, or location)
        $targets = $logs->groupBy(function($log) {
            if ($log->employee_id) return 'e_' . $log->employee_id;
            if ($log->machine_id) return 'm_' . $log->machine_id;
            return 'l_' . ($log->location_id ?? 0);
        });

        $passedTargets = [];

        foreach ($targets as $targetKey => $targetLogs) {
            // If the target has ANY failed log, we skip auto-verification (MUST be manually verified)
            $hasFail = $targetLogs->contains('result', 'fail');
            if (!$hasFail) {
                $passedTargets[] = $targetKey;
            }
        }

        $totalPassed = count($passedTargets);
        if ($totalPassed > 0) {
            // Smart Auto-Verification (Risk-Based Sampling)
            $highRiskTargets = [];
            $lowRiskTargets = [];

            // Query failures in the last 30 days for these targets
            $thirtyDaysAgo = now()->subDays(30);
            
            foreach ($passedTargets as $tKey) {
                $type = substr($tKey, 0, 1);
                $id = substr($tKey, 2);

                $query = \App\Models\InspectionLog::where('result', 'fail')
                    ->where('inspected_at', '>=', $thirtyDaysAgo);

                if ($type === 'e') {
                    $query->where('employee_id', $id);
                } elseif ($type === 'm') {
                    $query->where('machine_id', $id);
                } else {
                    $query->where('location_id', $id);
                }

                if ($query->exists()) {
                    $highRiskTargets[] = $tKey;
                } else {
                    $lowRiskTargets[] = $tKey;
                }
            }

            // High risk targets are NEVER auto-verified.
            // Low risk targets get 90% auto-verified (10% manual verification).
            $targetsToAutoVerify = [];
            $verifyCount = 0;
            
            $totalLowRisk = count($lowRiskTargets);
            if ($totalLowRisk > 0) {
                $verifyCount = (int) ceil($totalLowRisk * 0.10);
                shuffle($lowRiskTargets);
                
                // The ones beyond the $verifyCount will be Auto-Verified
                $targetsToAutoVerify = array_slice($lowRiskTargets, $verifyCount);
            }
            
            if (count($targetsToAutoVerify) > 0) {
                $logIdsToAutoVerify = [];
                foreach ($targetsToAutoVerify as $tKey) {
                    $logIdsToAutoVerify = array_merge($logIdsToAutoVerify, $targets[$tKey]->pluck('id')->toArray());
                }

                \App\Models\InspectionLog::whereIn('id', $logIdsToAutoVerify)->update([
                    'verification_status' => 'auto_verified',
                    'verified_at' => now(),
                    'verification_comment' => 'สุ่มทวนสอบ: ผ่านการอนุมัติอัตโนมัติ (Smart Auto-verified)',
                ]);

                // Notify Inspector
                if ($session->inspector) {
                    $session->inspector->notify(new \App\Notifications\SmartAutoVerifiedNotification(count($logIdsToAutoVerify)));
                }

                \Illuminate\Support\Facades\Log::info("Smart Auto-verified " . count($targetsToAutoVerify) . " low-risk targets for session {$session->id}. High risk: " . count($highRiskTargets) . ". Kept {$verifyCount} low-risk targets for manual verification.");
            }
        }
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
        $names = match (strtolower((string) $session->shift)) {
            'morning'   => ['morning', 'กะเช้า'],
            'afternoon' => ['afternoon', 'กะบ่าย'],
            'night'     => ['night', 'กะดึก'],
            default     => [(string) $session->shift],
        };

        $shift = \App\Models\Shift::whereIn('shift_name', $names)->first();
        if (! $shift || empty($shift->end_time)) {
            return null;
        }

        $date = \Illuminate\Support\Carbon::parse($session->inspection_date)->toDateString();
        $end  = \Illuminate\Support\Carbon::parse($date . ' ' . $shift->end_time);

        // Shift wraps past midnight (e.g. night 22:00-06:00): end lands on the next day
        if (! empty($shift->start_time) && $shift->end_time <= $shift->start_time) {
            $end->addDay();
        }

        return $end;
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
                if ($supervisor->email) {
                    $emails[] = $supervisor->email;
                }
            }

            // Send ONE grouped email to all supervisors
            if (count($emails) > 0) {
                \Illuminate\Support\Facades\Mail::to($emails)->send(new \App\Mail\InspectionSessionStarted($session));
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

            $emails = [];
            foreach ($supervisors as $supervisor) {
                // Send in-app database notification individually if needed
                // $supervisor->notify(new \App\Notifications\InspectionFinishedNotification($session));
                if ($supervisor->email) {
                    $emails[] = $supervisor->email;
                }
            }

            // Send ONE grouped email to all supervisors
            if (count($emails) > 0) {
                $stats = [
                    'total' => $session->logs()->count(),
                    'pass' => $session->logs()->where('status', 'pass')->count(),
                    'fail' => $session->logs()->whereIn('status', ['fail', 'reclean'])->count(),
                ];
                \Illuminate\Support\Facades\Mail::to($emails)->send(new \App\Mail\InspectionSessionFinished($session, $stats));
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

        return DB::transaction(function () use ($session) {
            // 1. Get all active personnel checkpoints
            $checkpoints = Checkpoint::where('type', 'person')
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            if (empty($checkpoints)) {
                return 0;
            }

            // 2. Get employees in this department + matching shift (Shift Filtering)
            $shiftNames = match($session->shift) {
                'morning' => ['morning', 'กะเช้า'],
                'afternoon' => ['afternoon', 'กะบ่าย'],
                'night' => ['night', 'กะดึก'],
                default => [$session->shift]
            };
            $targetEmployeeIds = \App\Models\Employee::where('department_id', $session->department_id)
                ->where('is_active', true)
                ->whereHas('shift', function ($q) use ($shiftNames) {
                    $q->whereIn('shift_name', $shiftNames);
                })
                ->pluck('id')
                ->toArray();

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

            // 5. Build bulk insert data
            $now = now();
            $deptSnapshot = $session->department->dept_name ?? 'N/A';
            $bulkData = [];

            foreach ($remainingIds as $employeeId) {
                foreach ($checkpoints as $checkpointId) {
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
