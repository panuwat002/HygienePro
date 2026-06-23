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
    public function startSession(User $user, int $departmentId, string $type, bool $forceNew = false): InspectionSession
    {
        $shift = \App\Models\Shift::detectCurrent();
        $today = now()->toDateString();

        $session = DB::transaction(function () use ($user, $departmentId, $type, $today, $shift, $forceNew) {
            $session = InspectionSession::where('department_id', $departmentId)
                ->where('inspection_date', $today)
                ->where('shift', $shift)
                ->where('type', $type)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($session && in_array($session->status, ['in_progress', 'paused'], true)) {
                if ($forceNew) {
                    throw ValidationException::withMessages([
                        'session' => 'มีเซสชันที่ยังดำเนินการอยู่ ไม่สามารถเริ่มรอบใหม่ได้ (Active Session Exists)',
                    ]);
                }

                if ($session->status === 'paused') {
                    $session->update(['status' => 'in_progress']);
                }

                return $session;
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

        $session->update([
            'status' => 'completed',
        ]);

        $this->notifySupervisorsFinished($session);
    }

    protected function notifySupervisorsStarted(InspectionSession $session): void
    {
        try {
            $supervisors = User::where('role', 'supervisor')->orWhere('level', 4)->get()->filter(function($u) {
                return $u->isQA();
            });

            foreach ($supervisors as $supervisor) {
                if ($supervisor->email) {
                    Mail::to($supervisor->email)->send(new InspectionSessionStarted($session));
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send Inspection Started email: ' . $e->getMessage());
        }
    }

    protected function notifySupervisorsFinished(InspectionSession $session): void
    {
        try {
            $supervisors = User::where('role', 'supervisor')->orWhere('level', 4)->get()->filter(function($u) {
                return $u->isQA();
            });

            $logs = InspectionLog::where('session_id', $session->id)->get();
            $stats = [
                'total' => $logs->count(),
                'pass' => $logs->where('result', 'pass')->count(),
                'fail' => $logs->where('result', 'fail')->count(),
            ];

            foreach ($supervisors as $supervisor) {
                if ($supervisor->email) {
                    Mail::to($supervisor->email)->send(new InspectionSessionFinished($session, $stats));
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send Inspection Finished email: ' . $e->getMessage());
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
                'is_locked' => true
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

            $pendingLog = InspectionLog::where('employee_id', $employeeId)
                ->where('checkpoint_id', $checkpointId)
                ->where('verification_status', 'reclean')
                ->whereDoesntHave('rechecks')
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

            return InspectionLog::updateOrCreate(
                [
                    'session_id' => $session->id,
                    'employee_id' => $employeeId,
                    'checkpoint_id' => $checkpointId,
                ],
                $logData
            );
        });
    }
}
