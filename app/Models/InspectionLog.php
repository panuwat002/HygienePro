<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InspectionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'location_id',
        'machine_id',
        'checkpoint_id',
        'employee_id',
        'result',
        'parent_id',
        'note',
        'correction_action',
        'photo_path',
        'inspected_at',
        'checkpoint_title_snapshot',
        'dept_snapshot',
        'verified_at',
        'verifier_id',
        'verification_status',
        'verification_comment',
        'approved_by',  // The QA manager who approved — never verifier_id, see migration
        'approved_at',
        'acknowledged_by', // Gap 3: Dept Head acknowledgement
        'acknowledged_at', // Gap 3: Dept Head acknowledgement
    ];

    protected $casts = [
        'inspected_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'acknowledged_at' => 'datetime', // Gap 3
    ];

    /**
     * A log belongs to the day its round covers, not to the day it was typed.
     *
     * Nine separate places write a log with `'inspected_at' => now()` - the
     * personnel flow, the bulk pass, and seven branches of the area flow - and
     * a backdated round needs every one of them to land on the round's own
     * date instead. Two places decide "has this target already been covered?"
     * by matching inspected_at against the session's inspection_date, so a log
     * left on the wrong day would make a round re-list people it had just
     * inspected.
     *
     * Done here rather than at each call site because there are nine of them
     * and the tenth would be written without remembering. The clock time is
     * kept, and when the row was really typed is still in its own created_at.
     *
     * Not covered: InspectionLog::insert(), which bypasses model events -
     * InspectionService::bulkPassRemaining() therefore asks the session for
     * the moment itself.
     */
    protected static function booted(): void
    {
        static::creating(function (self $log) {
            if (! $log->session_id) {
                return;
            }

            $session = $log->relationLoaded('session')
                ? $log->getRelation('session')
                : InspectionSession::find($log->session_id);

            if ($session?->isBackdated()) {
                $log->inspected_at = $session->inspectionMoment();
            }
        });
    }

    /**
     * The department a finding against this log belongs to - who has to fix it.
     *
     * A person belongs to a department, so a personnel finding has always had
     * an owner. An area or a machine did not: nothing in the data said whose
     * room it was, so the corrective action fell back to the session's
     * department, and an area round takes that from whoever walked it. That is
     * always QA, so every area finding landed on QA to repair - the department
     * that inspects became the department that fixes, and the people who run
     * the room were never told.
     *
     * Rooms now carry a department. The session remains the fallback for rooms
     * nobody has assigned yet, so nothing changes until somebody says who owns
     * what.
     */
    public function owningDepartmentId(): ?int
    {
        if ($this->employee_id) {
            return $this->employee?->department_id ?? $this->session?->department_id;
        }

        // A machine belongs wherever it stands.
        $location = $this->location ?? $this->machine?->location;

        return $location?->department_id ?? $this->session?->department_id;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function checkpoint()
    {
        return $this->belongsTo(Checkpoint::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function session()
    {
        return $this->belongsTo(InspectionSession::class);
    }

    public function parentLog()
    {
        return $this->belongsTo(InspectionLog::class, 'parent_id');
    }

    public function rechecks()
    {
        return $this->hasMany(InspectionLog::class, 'parent_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function correctiveAction()
    {
        return $this->hasOne(CorrectiveAction::class);
    }

    /**
     * Check if this failed inspection log has been resolved.
     *
     * @return bool
     */
    public function isResolved(): bool
    {
        if ($this->result !== 'fail') {
            return false;
        }

        if (!$this->correctiveAction) {
            return false;
        }

        return in_array($this->correctiveAction->status, ['resolved', 'closed', 'verified']);
    }

    // Gap 3: Acknowledgement relationship
    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
