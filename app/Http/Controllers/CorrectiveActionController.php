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
                
                // Manager/Supervisor Visibility: See all items in their department
                // ONLY if user is Supervisor/Manager (Level >= 4)
                if ($user->department_id && $user->level >= 4) {
                    $q->orWhereHas('log.session', function($sq) use ($user) {
                        $sq->where('department_id', $user->department_id);
                    });
                }
            });
        }

        $actions = $query->get();

        $openActions = $actions->whereIn('status', ['open', 'assigned', 'resolved']);
        $completedActions = $actions->whereIn('status', ['closed', 'verified']);
        
        $assignableUsers = \App\Models\User::whereHas('department', function($q) {
            $q->where('dept_name', 'like', '%Production%')
              ->orWhere('dept_name', 'like', '%ผลิต%');
        })
        ->where(function($q) {
            $q->whereIn('role', ['supervisor', 'manager'])
              ->orWhere('level', '>=', 4);
        })
        ->get();

        // Calculate Stats
        $stats = [
            'total' => $actions->count(),
            'open' => $openActions->whereIn('status', ['open', 'assigned'])->count(),
            'resolved' => $openActions->where('status', 'resolved')->count(),
            'closed' => $completedActions->count(),
            'overdue' => $actions->where('due_date', '<', now())->whereNotIn('status', ['closed', 'verified'])->count(),
        ];

        // AI Smart Tags Trend (Top 5 tags)
        $aiTagsTrend = $actions->pluck('ai_tags')->flatten()->filter()->countBy()->sortDesc()->take(5);

        return view('corrective.index', compact('openActions', 'completedActions', 'assignableUsers', 'stats', 'aiTagsTrend'));
    }

    public function assign(Request $request)
    {
        $request->validate([
            'action_id' => 'required|exists:corrective_actions,id',
            'assigned_to' => 'required|exists:users,id',
            'due_date' => 'nullable|date|after_or_equal:today'
        ]);

        $action = \App\Models\CorrectiveAction::findOrFail($request->action_id);

        // Reassigning a closed CAR silently reopened finished audit work.
        if ($action->status === 'closed') {
            return back()->with('error', 'ใบแจ้งปัญหานี้ปิดไปแล้ว ไม่สามารถจ่ายงานใหม่ได้ (CAR is already closed)');
        }

        $user = auth()->user();
        $targetDeptId = $action->log?->session?->department_id;

        // Authorization: Admin, QA, or Manager/Supervisor (level >= 4) of the target department
        if (!$user->isAdmin() && !$user->isQA()) {
            if ($user->level < 4 || $user->department_id !== $targetDeptId) {
                return back()->with('error', 'คุณไม่มีสิทธิ์กำหนดผู้รับผิดชอบงานนี้ (Unauthorized to assign)');
            }
            
            // SECURITY FIX: Ensure the assignee is from the SAME department
            $assignee = \App\Models\User::find($request->assigned_to);
            if ($assignee && $assignee->department_id !== $targetDeptId && !$assignee->isAdmin() && !$assignee->isQA()) {
                return back()->with('error', 'ไม่สามารถจ่ายงานข้ามแผนกได้ (Cannot assign to user in different department)');
            }
        }
        
        $oldAssignee = $action->assigned_to;
        
        $dueDate = $request->due_date ? \Carbon\Carbon::parse($request->due_date) : $action->due_date;
        if ($request->filled('due_preset')) {
            $presetMap = [
                '2h' => now()->addHours(2),
                '1d' => now()->addDay(),
                '3d' => now()->addDays(3),
                '7d' => now()->addDays(7),
            ];
            if (isset($presetMap[$request->due_preset])) {
                $dueDate = $presetMap[$request->due_preset];
            }
        }

        $action->update([
            'assigned_to' => $request->assigned_to,
            'assigned_at' => now(),
            'status' => 'assigned',
            'due_date' => $dueDate,
            'financial_loss' => $request->input('financial_loss', $action->financial_loss),
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
            if ($recipient && $recipient->email && $recipient->wantsEmailFor('email_car_new')) {
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
        
        $user = auth()->user();
        $targetDeptId = $log->session?->department_id;
        
        // Authorization: Admin, QA, Inspector of this session, or Manager/Supervisor of target dept
        if (!$user->isAdmin() && !$user->isQA() && $log->session?->inspector_id !== $user->id) {
            if ($user->level < 4 || $user->department_id !== $targetDeptId) {
                return back()->with('error', 'คุณไม่มีสิทธิ์สร้างใบแจ้งปัญหา (Unauthorized to escalate)');
            }
        }

        $assigneeId = $request->assignee_id;
        
        // SECURITY FIX: Ensure the assignee is from the SAME department
        if ($assigneeId && !$user->isAdmin() && !$user->isQA()) {
            $assignee = \App\Models\User::find($assigneeId);
            if ($assignee && $assignee->department_id !== $targetDeptId && !$assignee->isAdmin() && !$assignee->isQA()) {
                return back()->with('error', 'ไม่สามารถจ่ายงานข้ามแผนกได้ (Cannot assign to user in different department)');
            }
        }
        // BUG-010 Fix: Extract magic number / Use config
        $defaultDueHours = 24;
        $dueDate = $request->due_date ? \Carbon\Carbon::parse($request->due_date) : now()->addHours($defaultDueHours);

        $existing = \App\Models\CorrectiveAction::where('inspection_log_id', $log->id)->first();

        // A CAR that has already been resolved or closed is a finished audit record.
        // updateOrCreate() used to silently reopen and rewrite it, which let anyone
        // authorised to escalate in this department erase the recorded root cause and
        // reassign the escalator. Refuse instead.
        if ($existing && in_array($existing->status, ['resolved', 'closed'], true)) {
            return back()->with('error', 'ใบแจ้งปัญหานี้ปิดไปแล้ว ไม่สามารถแจ้งซ้ำได้ (CAR already resolved/closed)');
        }

        $attributes = [
            'status' => $assigneeId ? 'assigned' : 'open',
            'root_cause' => $request->note,
            'assigned_to' => $assigneeId,
            'assigned_at' => $assigneeId ? now() : null,
            'due_date' => $dueDate,
            'ai_tags' => \App\Services\AIService::getTagsFromFinding($request->note ?? ''),
        ];

        // escalated_by is the key close() authorises on, so it is set once at creation
        // and never transferred by a later escalate() on the same log.
        if (! $existing) {
            $attributes['escalated_by'] = auth()->id();
        }

        $action = \App\Models\CorrectiveAction::updateOrCreate(
            ['inspection_log_id' => $log->id],
            $attributes
        );

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
                if ($recipient->email && $recipient->wantsEmailFor('email_car_new')) {
                    $emails[] = $recipient->email;
                }
            }

            if (count($emails) > 0) {
                \Illuminate\Support\Facades\Mail::bcc($emails)->send(new \App\Mail\NewCAREscalated($action));
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
            'proof_image' => 'required|mimes:jpeg,png,jpg,gif,webp|max:10240' // 10MB
        ]);

        $action = \App\Models\CorrectiveAction::findOrFail($request->action_id);

        // A closed CAR is a finished audit record. Without this guard it could be
        // pushed back to 'resolved' indefinitely — and resolve() sets assigned_to to
        // the caller, so the escalator of a closed ticket could reopen it and claim
        // ownership of work someone else completed.
        if ($action->status === 'closed') {
            return back()->with('error', 'ใบแจ้งปัญหานี้ปิดไปแล้ว ไม่สามารถแก้ไขได้ (CAR is already closed)');
        }

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

        if ($action->log) {
            $action->log->update([
                'correction_action' => $request->action_taken
            ]);
        }

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

        // SECURITY FIX: Ensure the assignee is from the SAME department
        if (!$user->isAdmin() && !$user->isQA()) {
            $assignee = \App\Models\User::find($request->assigned_to);
            if ($assignee && $assignee->department_id !== $targetDeptId && !$assignee->isAdmin() && !$assignee->isQA()) {
                return back()->with('error', 'ไม่สามารถจ่ายงานข้ามแผนกได้ (Cannot delegate to user in different department)');
            }
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
        
        // findOrFail, not find: the `exists` rule above queries the raw table and so
        // matches soft-deleted rows, which find() then excludes — leaving $action null
        // and 500ing on the update below. 404 is the right answer for a trashed CAR.
        $action = \App\Models\CorrectiveAction::findOrFail($request->action_id);

        $isQA = auth()->user()->isQA();
        $isManager = auth()->user()->level >= 5;
        $targetDeptId = $action->log?->session?->department_id;

        // Manager can only close tickets in their own department. Strict comparison and
        // an explicit null guard: with loose ==, a departmentless manager matched an
        // orphaned log's null department and was treated as authorised.
        $isAuthorizedManager = $isManager
            && $targetDeptId !== null
            && auth()->user()->department_id === $targetDeptId;

        if (auth()->id() != $action->escalated_by && !auth()->user()->isAdmin() && !$isQA && !$isAuthorizedManager) {
             return back()->with('error', 'Only the escalator, QA, or authorized Manager can close this ticket.');
        }

        // Closing says the problem is fixed and somebody checked. With nothing
        // written down the record cannot answer the question it exists for, so
        // say what is missing rather than letting the model guard throw.
        if (blank($action->action_taken)) {
            return back()->with('error', 'ยังไม่ได้บันทึกวิธีแก้ไข จึงปิดใบนี้ไม่ได้ — กรุณาบันทึกสิ่งที่ทำไปก่อน (ถ้าตรวจซ้ำแล้วไม่พบปัญหา ให้ระบุไว้เช่นกัน)');
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
                // Determine the new status based on the user's role closing the CAR
                $newStatus = null;
                $user = auth()->user();
                
                if ($user->isAdmin() || $user->isQA() || $user->level >= 5) {
                    $newStatus = 'approved';
                } elseif ($user->level >= 4) { // Supervisor
                    $newStatus = 'verified';
                } // Inspector / Staff (Level < 4) do not auto-approve/verify. Log remains 'reclean'
                
                if ($newStatus) {
                    // 1. Update the primary log
                    $log->update([
                        'verification_status' => $newStatus,
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
                                'verification_status' => $newStatus,
                                'verified_at' => now()
                            ]);
                        }
                    }
                }
            }
        }
        // -----------------------------------

        return back()->with('success', 'Ticket closed.');
    }
}
