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

        if ($currentStep->user_id) {
            if ($currentStep->user_id !== $user->id) {
                return Response::deny('Unauthorized. This step is assigned to a specific user.');
            }
            // If user_id is set and matches, bypass role and department checks
        } elseif ($currentStep->role) {
            if ($currentStep->role !== $user->role) {
                return Response::deny('Unauthorized. Incorrect role.');
            }
            if (!is_null($currentStep->department_id)) {
                if ($currentStep->department_id !== $user->department_id) {
                    return Response::deny('Unauthorized. You must be in the assigned department.');
                }
            } else {
                $requester = $approvalRequest->requester;
                if ($requester) {
                    if ($requester->manager_id !== $user->id) {
                        return Response::deny('Unauthorized. You must be the direct manager (Line Manager) of the requester.');
                    }
                } else {
                    // Fallback for old data
                    if (!is_null($approvalRequest->department_id) && $approvalRequest->department_id !== $user->department_id) {
                        return Response::deny('Unauthorized. You must be in the same department as the requester.');
                    }
                }
            }
        }

        return Response::allow();
    }
}
