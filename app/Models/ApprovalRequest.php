<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_flow_id',
        'department_id',
        'requester_id',
        'approvable_id',
        'approvable_type',
        'current_step_order',
        'status',
        'rejection_reason',
    ];

    public function flow()
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function approvable()
    {
        return $this->morphTo();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Scope a query to only include pending approval requests assigned to the given user.
     */
    public function scopePendingForUser($query, $user)
    {
        return $query->where('status', 'pending')
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
            });
    }
}
