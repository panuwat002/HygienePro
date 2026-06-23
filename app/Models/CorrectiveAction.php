<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorrectiveAction extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    use \App\Traits\HasApprovals;

    protected $fillable = [
        'inspection_log_id',
        'status',
        'approval_status',
        'escalated_by',
        'assigned_to',
        'root_cause',
        'action_taken',
        'proof_image',
        'escalated_at',
        'assigned_at',
        'resolved_at',
        'closed_at',
        'due_date'
    ];

    protected $casts = [
        'escalated_at' => 'datetime',
        'assigned_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'due_date' => 'datetime',
    ];

    public function log()
    {
        return $this->belongsTo(InspectionLog::class, 'inspection_log_id');
    }

    public function escalator()
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function getApprovalDetails()
    {
        return "CAR for Log ID: {$this->inspection_log_id}\nRoot Cause: {$this->root_cause}\nAction Taken: {$this->action_taken}";
    }

    public function markAsApproved()
    {
        $this->approval_status = 'approved';
        $this->status = 'closed'; // Or another appropriate status based on your business logic
        $this->save();
    }

    public function markAsRejected($reason = null)
    {
        $this->approval_status = 'rejected';
        $this->status = 'open'; // Reset status back to open
        $this->save();
    }

    public function getApprovalDepartmentId()
    {
        return $this->log?->session?->department_id;
    }
}
