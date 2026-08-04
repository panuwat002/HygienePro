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
        'acknowledged_by', // Gap 3: Dept Head acknowledgement
        'acknowledged_at', // Gap 3: Dept Head acknowledgement
    ];

    protected $casts = [
        'inspected_at' => 'datetime',
        'verified_at' => 'datetime',
        'acknowledged_at' => 'datetime', // Gap 3
    ];

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
