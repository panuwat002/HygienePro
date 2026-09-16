<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ApprovalRequestPolicy
{
    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, ApprovalRequest $approvalRequest): Response
    {
        if ($approvalRequest->status !== 'pending') {
            return Response::deny('This request is no longer pending.');
        }

        $currentStep = $approvalRequest->flow->steps->where('step_order', $approvalRequest->current_step_order)->first();
        if (!$currentStep) {
            return Response::deny('Invalid step.');
        }

        // Nobody approves their own request, whatever the step says. Without this a
        // manager assigned to the department could file a request and sign it off alone,
        // which is the one thing an approval chain exists to prevent.
        if ($approvalRequest->requester_id === $user->id && ! $user->isAdmin()) {
            return Response::deny('Unauthorized. You cannot approve your own request.');
        }

        if ($currentStep->user_id) {
            if ($currentStep->user_id !== $user->id) {
                return Response::deny('Unauthorized. This step is assigned to a specific user.');
            }

            // If user_id is set and matches, bypass role and department checks
            return Response::allow();
        }

        if ($currentStep->role) {
            if ($currentStep->role !== $user->role) {
                return Response::deny('Unauthorized. Incorrect role.');
            }

            if (!is_null($currentStep->department_id)) {
                if ($currentStep->department_id !== $user->department_id) {
                    return Response::deny('Unauthorized. You must be in the assigned department.');
                }

                return Response::allow();
            }

            $requester = $approvalRequest->requester;

            if ($requester) {
                if ($requester->manager_id !== $user->id) {
                    return Response::deny('Unauthorized. You must be the direct manager (Line Manager) of the requester.');
                }

                return Response::allow();
            }

            // Fallback for old data
            if (!is_null($approvalRequest->department_id) && $approvalRequest->department_id !== $user->department_id) {
                return Response::deny('Unauthorized. You must be in the same department as the requester.');
            }

            return Response::allow();
        }

        // A step with neither user_id nor role assigned matches nobody. This used to
        // fall through to a blanket Response::allow() at the end of the method, so any
        // authenticated user could approve it. ApprovalFlowController::storeStep guards
        // against creating such a step, but its check is `!$request->role` (so the
        // string "0" slips past) and approval_flow_steps.role is nullable at the DB
        // level, so the guard is not a control. Deny by default instead.
        return Response::deny('Unauthorized. This approval step has no assignee configured.');
    }
}
