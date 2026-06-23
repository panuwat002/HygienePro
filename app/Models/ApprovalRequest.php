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
}
