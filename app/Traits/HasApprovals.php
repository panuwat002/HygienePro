<?php

namespace App\Traits;

use App\Models\ApprovalRequest;
use App\Models\ApprovalFlow;

trait HasApprovals
{
    /**
     * Get the approval requests for the model.
     */
    public function approvals()
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }

    public function startApprovalFlow()
    {
        $flow = ApprovalFlow::where('target_model', get_class($this))
                            ->where('is_active', true)
                            ->first();

        if ($flow && $flow->steps()->count() > 0) {
            // Update the model status if it has an approval_status column
            if (in_array('approval_status', $this->getFillable()) || \Schema::hasColumn($this->getTable(), 'approval_status')) {
                $this->update(['approval_status' => 'pending']);
            }

            $departmentId = method_exists($this, 'getApprovalDepartmentId') ? $this->getApprovalDepartmentId() : ($this->department_id ?? null);
            $requesterId = method_exists($this, 'getApprovalRequesterId') ? $this->getApprovalRequesterId() : (auth()->id() ?? null);

            return ApprovalRequest::updateOrCreate(
                [
                    'approvable_type' => get_class($this),
                    'approvable_id' => $this->id,
                ],
                [
                    'approval_flow_id' => $flow->id,
                    'department_id' => $departmentId,
                    'requester_id' => $requesterId,
                    'current_step_order' => 1,
                    'status' => 'pending',
                ]
            );
        }

        return null;
    }
}
