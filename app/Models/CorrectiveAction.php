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
        'resolved_by', // who actually did it - never the same column as who it was given to
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

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Was this dealt with by somebody outside the department that owns it?
     *
     * QA stepping in for Production is deliberate - it gets the floor cleaned
     * sooner than waiting for the department to pick the ticket up. The risk is
     * not that it happens; it is that it happens every time and nobody notices,
     * because once it is done the record looks exactly like Production having
     * fixed their own problem.
     *
     * Derived, not stored: a stored flag could be set without the people
     * changing, or the people change without the flag. The departments are the
     * fact.
     */
    public function wasResolvedOnBehalf(): bool
    {
        $resolver = $this->resolver;

        if (! $resolver || ! $this->resolved_at) {
            return false;
        }

        $owner = $this->log?->owningDepartmentId();

        if (! $owner || ! $resolver->department_id) {
            return false;
        }

        // A department answers for anything split out of it and for the one it
        // was split out of: ห้องแคะ's head is Production's head, and neither is
        // standing in for the other.
        return ! in_array($resolver->department_id, self::departmentsAnsweringFor($owner), true);
    }

    /**
     * The owning department, the one it was split out of, and the ones split
     * out of it. Same reach as User::canAcknowledge().
     *
     * @return array<int, int>
     */
    public static function departmentsAnsweringFor(int $departmentId): array
    {
        $department = Department::select('id', 'parent_department_id')->find($departmentId);

        if (! $department) {
            return [$departmentId];
        }

        return Department::query()
            ->where('id', $departmentId)
            ->orWhere('parent_department_id', $departmentId)
            ->when($department->parent_department_id, fn ($q) => $q
                ->orWhere('id', $department->parent_department_id))
            ->pluck('id')
            ->all();
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
