<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApprovalRequest;

class ApprovalController extends Controller
{
    public function pending()
    {
        $user = auth()->user();

        // Get requests where status is 'pending' and the current step is assigned to me (by user_id or role)
        $requests = ApprovalRequest::with(['flow.steps', 'approvable'])
            ->where('status', 'pending')
            ->whereHas('flow.steps', function($q) use ($user) {
                // The step order matches the request's current_step_order
                $q->whereColumn('step_order', 'approval_requests.current_step_order')
                  ->where(function($sub) use ($user) {
                      // 1. If user_id is set, ONLY that specific user can see it
                      $sub->where(function($userQ) use ($user) {
                          $userQ->whereNotNull('user_id')
                                ->where('user_id', $user->id);
                      })
                      // 2. If user_id is NOT set, check by role and department
                      ->orWhere(function($roleQ) use ($user) {
                          $roleQ->whereNull('user_id')
                                ->where('role', $user->role)
                                ->where(function($deptQ) use ($user) {
                                    // Case 1: Step has a specific department, user must be in that department
                                    $deptQ->where('department_id', $user->department_id)
                                          // Case 2: Step is dynamic (Option B: Direct Line Manager)
                                          ->orWhere(function($dynamicQ) use ($user) {
                                              $dynamicQ->whereNull('department_id')
                                                       ->where(function($reqQ) use ($user) {
                                                           // Must be direct manager of the requester
                                                           $reqQ->whereRaw('approval_requests.requester_id IN (SELECT id FROM users WHERE manager_id = ?)', [$user->id])
                                                                // Fallback for old data without requester_id (checks department instead)
                                                                ->orWhere(function($oldDataQ) use ($user) {
                                                                    $oldDataQ->whereNull('approval_requests.requester_id')
                                                                             ->where(function($reqDeptQ) use ($user) {
                                                                                 $reqDeptQ->whereRaw('approval_requests.department_id IS NULL')
                                                                                          ->orWhereRaw('approval_requests.department_id = ?', [$user->department_id]);
                                                                             });
                                                                });
                                                       });
                                          });
                                });
                      });
                  });
            })
            ->latest()
            ->get();

        return view('approvals.pending', compact('requests'));
    }

    public function approve(Request $request, ApprovalRequest $approval)
    {
        // 1. Verify it is pending and it is my turn
        if ($approval->status !== 'pending') {
            return back()->with('error', 'This request is no longer pending.');
        }

        $currentStep = $approval->flow->steps->where('step_order', $approval->current_step_order)->first();
        if (!$currentStep) {
            return back()->with('error', 'Invalid step.');
        }

        $user = auth()->user();
        if ($currentStep->user_id) {
            if ($currentStep->user_id !== $user->id) {
                 return back()->with('error', 'Unauthorized. This step is assigned to a specific user.');
            }
            // If user_id is set and matches, we bypass role and department checks completely
        } elseif ($currentStep->role) {
            if ($currentStep->role !== $user->role) {
                return back()->with('error', 'Unauthorized. Incorrect role.');
            }
            if (!is_null($currentStep->department_id)) {
                if ($currentStep->department_id !== $user->department_id) {
                    return back()->with('error', 'Unauthorized. You must be in the assigned department.');
                }
            } else {
                $requester = $approval->requester;
                if ($requester) {
                    if ($requester->manager_id !== $user->id) {
                        return back()->with('error', 'Unauthorized. You must be the direct manager (Line Manager) of the requester.');
                    }
                } else {
                    // Fallback for old data
                    if (!is_null($approval->department_id) && $approval->department_id !== $user->department_id) {
                        return back()->with('error', 'Unauthorized. You must be in the same department as the requester.');
                    }
                }
            }
        }

        // 2. Determine if there is a next step
        $nextStep = $approval->flow->steps->where('step_order', '>', $approval->current_step_order)->sortBy('step_order')->first();

        if ($nextStep) {
            // Move to next step
            $approval->current_step_order = $nextStep->step_order;
            $approval->save();
            return back()->with('success', 'Request approved. Moved to next step.');
        } else {
            // Fully approved
            $approval->status = 'approved';
            $approval->save();

            // Update Target Model Status
            if (method_exists($approval->approvable, 'markAsApproved')) {
                $approval->approvable->markAsApproved();
            } else {
                $approval->approvable->approval_status = 'approved';
                $approval->approvable->save();
            }

            return back()->with('success', 'Request fully approved.');
        }
    }

    public function reject(Request $request, ApprovalRequest $approval)
    {
        $request->validate([
            'reason' => 'nullable|string'
        ]);

        if ($approval->status !== 'pending') {
            return back()->with('error', 'This request is no longer pending.');
        }

        $approval->status = 'rejected';
        $approval->rejection_reason = $request->reason;
        $approval->save();

        // Update Target Model Status
        if (method_exists($approval->approvable, 'markAsRejected')) {
            $approval->approvable->markAsRejected($request->reason);
        } else {
            $approval->approvable->approval_status = 'rejected';
            $approval->approvable->save();
        }

        return back()->with('success', 'Request rejected.');
    }
}
