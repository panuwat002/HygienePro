<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CorrectiveActionController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Base Query
        $query = \App\Models\CorrectiveAction::with([
            'log.checkpoint', 
            'log.location', 
            'log.machine', 
            'log.employee.department',
            'escalator', 
            'assignee'
        ])
        // Sort Priority: Pending Action ('open', 'assigned') -> Resolved -> Closed
        ->orderByRaw("CASE WHEN status IN ('open', 'assigned') THEN 0 ELSE 1 END ASC")
        ->orderBy('created_at', 'desc');

        // --- NAME FIX PATCH: Removed (Moved to fix_escalated_by.php) ---
        // ---------------------------------------------------------------

        // Filter Logic
        $isQA = $user->isQA();

        if (!$user->isAdmin() && !$isQA) {
            $query->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('escalated_by', $user->id);
                
                // Manager Visibility: See all items in their department
                // ONLY if user is Manager (Level >= 5)
                if ($user->department_id && $user->level >= 5) {
                    $q->orWhereHas('log.session', function($sq) use ($user) {
                        $sq->where('department_id', $user->department_id);
                    });
                }
            });
        }

        $actions = $query->get();

        $openActions = $actions->whereIn('status', ['open', 'assigned', 'resolved']);
        $completedActions = $actions->whereIn('status', ['closed', 'verified']);
        
        // Prepare Assignable Users: เฉพาะแผนกผลิต (Production) ที่มีสิทธิ์ระดับ Supervisor หรือ Manager ขึ้นไป
        $assignableUsers = \App\Models\User::whereHas('department', function($q) {
            $q->where('dept_name', 'like', '%Production%')
              ->orWhere('dept_name', 'like', '%ผลิต%');
        })
        ->where(function($q) {
            $q->whereIn('role', ['supervisor', 'manager'])
              ->orWhere('level', '>=', 4);
        })
        ->get();
        return view('corrective.index', compact('openActions', 'completedActions', 'assignableUsers'));
    }

    public function assign(Request $request)
    {
        $request->validate([
            'action_id' => 'required|exists:corrective_actions,id',
            'assigned_to' => 'required|exists:users,id',
            'due_date' => 'nullable|date|after_or_equal:today'
        ]);

        $action = \App\Models\CorrectiveAction::findOrFail($request->action_id);
        
        $oldAssignee = $action->assigned_to;
        
        $action->update([
            'assigned_to' => $request->assigned_to,
            'assigned_at' => now(),
            'status' => 'assigned',
            'due_date' => $request->due_date ?? $action->due_date
        ]);

        // Manual Activity Log
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'change_assignee',
            'model_type' => \App\Models\CorrectiveAction::class,
            'model_id' => $action->id,
            'old_values' => ['assigned_to' => $oldAssignee],
            'new_values' => ['assigned_to' => $request->assigned_to],
            'description' => 'Changed assignee for CAR #' . $action->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Email Notification
        try {
            $recipient = \App\Models\User::find($request->assigned_to);
            if ($recipient && $recipient->email) {
                \Illuminate\Support\Facades\Mail::to($recipient->email)->send(new \App\Mail\NewCAREscalated($action));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send CAR assignment email: ' . $e->getMessage());
        }

        return back()->with('success', 'Assigned task to user successfully.');
    }

    public function escalate(Request $request)
    {
        $request->validate([
            'log_id' => 'required|exists:inspection_logs,id',
            'note' => 'nullable|string',
            'assignee_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date|after_or_equal:today',
        ]);

        $log = \App\Models\InspectionLog::find($request->log_id);

        $assigneeId = $request->assignee_id;
        // BUG-010 Fix: Extract magic number / Use config
        $defaultDueHours = 24;
        $dueDate = $request->due_date ? \Carbon\Carbon::parse($request->due_date) : now()->addHours($defaultDueHours);

        $action = \App\Models\CorrectiveAction::create([
            'inspection_log_id' => $log->id,
            'status' => $assigneeId ? 'assigned' : 'open',
            'escalated_by' => auth()->id(),
            'root_cause' => $request->note,
            'assigned_to' => $assigneeId,
            'assigned_at' => $assigneeId ? now() : null,
            'due_date' => $dueDate,
            'ai_tags' => \App\Services\AIService::getTagsFromFinding($request->note ?? ''),
        ]);

        // Email Notification
        try {
            $deptId = $log->session->department_id;
            
            // Determine Recipients
            $recipients = collect();
            if ($assigneeId) {
                $user = \App\Models\User::find($assigneeId);
                if ($user) $recipients->push($user);
            } else {
                // Auto-route to Managers and Supervisors
                $recipients = \App\Models\User::where('department_id', $deptId)
                            ->where(function($q) {
                                $q->whereIn('role', ['supervisor', 'manager'])
                                  ->orWhere('level', '>=', 4);
                            })
                            ->get();
            }
            
            $emails = [];
            foreach ($recipients as $recipient) {
                $recipient->notify(new \App\Notifications\NewCARNotification($action));
                if ($recipient->email) {
                    $emails[] = $recipient->email;
                }
            }

            if (count($emails) > 0) {
                \Illuminate\Support\Facades\Mail::to($emails)->send(new \App\Mail\NewCAREscalated($action));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send CAR email: ' . $e->getMessage());
        }

        return back()->with('success', 'Defect escalated' . ($assigneeId ? ' & assigned' : '') . ' successfully.');
    }

    public function resolve(Request $request)
    {
        $request->validate([
            'action_id' => 'required|exists:corrective_actions,id',
            'action_taken' => 'required|string',
            'preventive_action' => 'required|string',
            'proof_image' => 'required|image|max:10240' // 10MB
        ]);

        $action = \App\Models\CorrectiveAction::findOrFail($request->action_id);

        $user = auth()->user();
        if ($action->assigned_to !== $user->id && $action->escalated_by !== $user->id && !$user->isAdmin() && !$user->isQA()) {
            return back()->with('error', 'คุณไม่มีสิทธิ์แก้ไขรายการนี้ (Only assigned user or escalator can resolve)');
        }
        
        $path = $request->file('proof_image')->store('corrective_proofs', 'public');

        $action->update([
            'status' => 'resolved',
            'action_taken' => $request->action_taken,
            'preventive_action' => $request->preventive_action,
            'proof_image' => $path,
            'resolved_at' => now(),
            'assigned_to' => auth()->id() // Auto-claim by resolver
        ]);

        // Loop Engineering: Work is resolved, goes back to QA via Verify page.
        // Removed startApprovalFlow() to break the linear cycle.

        // Notify the person who escalated it
        if ($action->escalator) {
            $action->escalator->notify(new \App\Notifications\CARResolvedNotification($action));
        } elseif ($action->log && $action->log->session) {
            // Notify the inspector if escalator relation doesn't exist
            $inspector = \App\Models\User::find($action->log->session->inspector_id);
            if ($inspector) {
                $inspector->notify(new \App\Notifications\CARResolvedNotification($action));
            }
        }

        return back()->with('success', 'Corrective action resolved successfully.');
    }

    public function delegate(Request $request)
    {
        $request->validate([
            'action_id' => 'required|exists:corrective_actions,id',
            'assigned_to' => 'required|exists:users,id'
        ]);

        $action = \App\Models\CorrectiveAction::findOrFail($request->action_id);
        
        $user = auth()->user();
        $targetDeptId = $action->log?->session?->department_id;

        // Only Manager of the Target Department, Admin, or QA can delegate
        if ($user->department_id !== $targetDeptId && !$user->isAdmin() && !$user->isQA()) {
            return back()->with('error', 'คุณไม่มีสิทธิ์มอบหมายงานในแผนกนี้ (Only Manager of the target department can delegate)');
        }

        // Only Managers (level >= 5) or QA/Admin can delegate
        if ($user->level < 5 && !$user->isAdmin() && !$user->isQA()) {
            return back()->with('error', 'คุณต้องมีสิทธิ์ระดับ Manager ขึ้นไปเพื่อมอบหมายงาน (Manager level required)');
        }

        $action->update([
            'assigned_to' => $request->assigned_to,
            'assigned_at' => now(),
            'status' => 'open' // Still open, just assigned
        ]);

        return back()->with('success', 'มอบหมายงานเรียบร้อยแล้ว (Work delegated successfully.)');
    }

    public function close(Request $request)
    {
        $request->validate(['action_id' => 'required|exists:corrective_actions,id']);
        
        $action = \App\Models\CorrectiveAction::find($request->action_id);
        
        $isQA = auth()->user()->isQA();
        $isManager = auth()->user()->level >= 5;

        if (auth()->id() != $action->escalated_by && !auth()->user()->isAdmin() && !$isQA && !$isManager) {
             return back()->with('error', 'Only the escalator, QA, or Manager can close this ticket.');
        }

        $action->update([
            'status' => 'closed',
            'closed_at' => now()
        ]);

        // Email Notification: Send Email to Assignee when QA closes the ticket
        if ($action->assignee) {
            try {
                $action->assignee->notify(new \App\Notifications\CARClosedNotification($action));
            } catch (\Exception $e) {
                \Log::error('Failed to send CARClosedNotification: ' . $e->getMessage());
            }
        }

        // --- AUTO-APPROVE INSPECTION LOG AND RELATED GROUP LOGS ---
        if ($action->inspection_log_id) {
            $log = \App\Models\InspectionLog::find($action->inspection_log_id);
            if ($log) {
                // 1. Update the primary log
                $log->update([
                    'verification_status' => 'approved',
                    'verified_at' => now()
                ]);

                // 2. Find and close ALL related 'reclean' logs in the same group
                //    Group = Same session + Same employee/machine/location
                $relatedQuery = \App\Models\InspectionLog::where('session_id', $log->session_id)
                    ->where('verification_status', 'reclean');
                
                // BUG-002 Fix: Better targeting of related logs
                // Ensure we don't accidentally select all logs if identifiers are missing
                $hasIdentifier = false;

                if ($log->employee_id) {
                    $relatedQuery->where('employee_id', $log->employee_id);
                    $hasIdentifier = true;
                } 
                if ($log->machine_id) {
                    $relatedQuery->where('machine_id', $log->machine_id);
                    $hasIdentifier = true;
                }
                if ($log->location_id) {
                    // Only use location if it's an Area inspection (no machine/employee)
                    // Or if we want to group by location generally. 
                    // Current logic implies specific target.
                    if (!$log->machine_id && !$log->employee_id) {
                        $relatedQuery->where('location_id', $log->location_id);
                        $hasIdentifier = true;
                    }
                }

                // Guard: If no valid identifier found (unlikely but safe), don't update others
                if (!$hasIdentifier) {
                    $relatedQuery->whereRaw('0 = 1'); // Return empty set
                }

                $relatedLogs = $relatedQuery->get();

                foreach ($relatedLogs as $relatedLog) {
                    // Check if this log's CAR is also closed
                    $relatedCAR = $relatedLog->correctiveAction;
                    
                    if (!$relatedCAR || $relatedCAR->status === 'closed') {
                        // No CAR or CAR is closed -> Approve this log too
                        $relatedLog->update([
                            'verification_status' => 'approved',
                            'verified_at' => now()
                        ]);
                    }
                }
            }
        }
        // -----------------------------------

        return back()->with('success', 'Ticket closed and related Inspection Logs approved.');
    }
}
