<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorrectiveAction extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    use \Illuminate\Database\Eloquent\SoftDeletes;
    use \App\Traits\HasApprovals;
    use \App\Traits\LogsActivity;

    protected $fillable = [
        'inspection_log_id',
        'status',
        'ai_tags',
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
        'due_date',
        'financial_loss',
    ];

    protected $casts = [
        'escalated_at' => 'datetime',
        'assigned_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'due_date' => 'datetime',
        'ai_tags' => 'array',
        'financial_loss' => 'decimal:2',
    ];

    /**
     * A finding cannot become "checked" or "finished" with nothing written down.
     *
     * Every route that moves a CAR to 'verified' or 'closed' is fixed, but this
     * is the statement the whole record exists to support - an auditor's first
     * question is what did you do about it - so it is enforced here too, where
     * no future route can reopen the hole quietly.
     *
     * Only transitions are guarded. Seeding or importing a historical row that
     * is already closed is a different thing from claiming one is finished now.
     */
    protected static function booted(): void
    {
        static::updating(function (self $action) {
            if (! $action->isDirty('status')) {
                return;
            }

            if (! in_array($action->status, ['verified', 'closed'], true)) {
                return;
            }

            if (filled($action->action_taken)) {
                return;
            }

            throw new \LogicException(
                "CorrectiveAction {$action->id} cannot become '{$action->status}' with no action_taken: "
                . 'closing a finding without recording what was done destroys the evidence it exists to hold.'
            );
        });
    }

    /**
     * Get SLA Status: 'normal', 'near_due' (within 6 hrs), 'overdue'
     */
    public function getSlaStatusAttribute(): string
    {
        if ($this->status === 'closed' || $this->status === 'resolved' || $this->approval_status === 'approved') {
            return 'normal';
        }

        if (!$this->due_date) {
            return 'normal';
        }

        $now = now();
        if ($now->gt($this->due_date)) {
            return 'overdue';
        }

        if ($now->diffInHours($this->due_date, false) <= 6) {
            return 'near_due';
        }

        return 'normal';
    }

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
        $log = $this->log;
        if ($log) {
            $target = '';
            if ($log->employee) {
                $target = "พนักงาน: " . $log->employee->fullname;
            } elseif ($log->machine) {
                $target = "เครื่องจักร: " . $log->machine->name;
            } elseif ($log->location) {
                $target = "พื้นที่: " . $log->location->location_name;
            }
            
            $checkpoint = $log->checkpoint ? $log->checkpoint->title : $log->checkpoint_title_snapshot;
            return "เป้าหมาย (Target): {$target}\nหัวข้อที่ตก (Issue): {$checkpoint}\nสาเหตุ (Root Cause): {$this->root_cause}\nการแก้ไข (Action): {$this->action_taken}";
        }
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

    public function getApprovalRequesterId()
    {
        return $this->escalated_by;
    }
}
