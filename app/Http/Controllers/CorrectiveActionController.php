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
        $completedActions = $actions->whereIn('status', ['closed']);
        
        // Prepare Assignable Users (Same Department)
        $assignableUsers = collect();
        if ($user->department_id) {
            $assignableUsers = \App\Models\User::where('department_id', $user->department_id)->get();
        } else {
            // Admin sees all? Or maybe just limit to empty if no dept
            $assignableUsers = \App\Models\User::all();
        }

        return view('corrective.index', compact('openActions', 'completedActions', 'assignableUsers'));
    }

    public function assign(Request $request)
    {
        $request->validate([
            'action_id' => 'required|exists:corrective_actions,id',
            'user_id' => 'required|exists:users,id',
            'due_date' => 'nullable|date|after_or_equal:today'
        ]);

        $action = \App\Models\CorrectiveAction::findOrFail($request->action_id);
        
        $oldAssignee = $action->assigned_to;
        
        $action->update([
            'assigned_to' => $request->user_id,
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
            'new_values' => ['assigned_to' => $request->user_id],
            'description' => 'Changed assignee for CAR #' . $action->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Email Notification
        try {
            $recipient = \App\Models\User::find($request->user_id);
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
        ]);

        // Email Notification
        try {
            $deptId = $log->session->department_id;
            
            // Determine Recipient
            $recipient = null;
            if ($assigneeId) {
                $recipient = \App\Models\User::find($assigneeId);
            } else {
                // Auto-route to Manager
                $recipient = \App\Models\User::where('department_id', $deptId)
                            ->where('level', '>=', 5)
                            ->first();
            }
            
             if ($recipient && $recipient->email) {
                \Illuminate\Support\Facades\Mail::to($recipient->email)->send(new \App\Mail\NewCAREscalated($action));
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
            'proof_image' => 'required|image|max:10240' // 10MB
        ]);

        $action = \App\Models\CorrectiveAction::find($request->action_id);
        
        $path = $request->file('proof_image')->store('corrective_proofs', 'public');

        $action->update([
            'status' => 'resolved',
            'action_taken' => $request->action_taken,
            'proof_image' => $path,
            'resolved_at' => now(),
            'assigned_to' => auth()->id() // Auto-claim by resolver
        ]);

        // Start approval flow for the resolved CAR
        $flow = $action->startApprovalFlow();

        if ($flow) {
            return back()->with('success', 'Corrective action resolved and sent for approval.');
        }

        return back()->with('success', 'Corrective action resolved successfully.');
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
