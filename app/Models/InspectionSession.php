<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InspectionSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'inspector_id',
        'type', // Added type
        'department_id',
        'inspection_date',
        'shift',
        'round',
        'status',
        'locked_at',
        'verified_by',
        'verified_at',
        'approved_by',
        'approved_at',
        'is_locked', // Gap 2: Lock flag after final approval
        'is_audit', // Bulk Pass: Flag for audit sessions
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'locked_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'is_locked' => 'boolean', // Gap 2: Lock flag
        'is_audit' => 'boolean', // Bulk Pass: Audit flag
    ];

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function logs()
    {
        return $this->hasMany(InspectionLog::class, 'session_id');
    }

    public function signatures()
    {
        return $this->hasMany(Signature::class, 'session_id');
    }

    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    public function hasPendingRecleans(): bool
    {
        return $this->logs()
            ->where('verification_status', 'reclean')
            ->whereDoesntHave('rechecks')
            ->exists();
    }

    /**
     * Session accepts new/edited logs (full inspection or re-clean fixes only).
     */
    public function allowsEditing(bool $recleanOnly = false): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        if (in_array($this->status, ['in_progress', 'paused'], true)) {
            return true;
        }

        if ($this->status === 'completed' && $recleanOnly && $this->hasPendingRecleans()) {
            return true;
        }

        return false;
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
