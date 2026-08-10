<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RandomAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'audit_date',
        'shift',
        'status',
        'auditor_id',
        'week_number',
        'year',
        'sample_size',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'audit_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function auditor()
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    /**
     * Scope: Get audits for the current week.
     */
    public function scopeCurrentWeek($query)
    {
        return $query->where('week_number', now()->isoWeek())
                     ->where('year', now()->year);
    }

    /**
     * Scope: Get today's pending audits.
     */
    public function scopeTodayPending($query)
    {
        return $query->where('audit_date', now()->toDateString())
                     ->where('status', 'pending');
    }

    /**
     * Check if this audit is for today.
     */
    public function isToday(): bool
    {
        return $this->audit_date->isToday();
    }

    /**
     * Check if this audit has been missed (date passed without completion).
     */
    public function isMissed(): bool
    {
        return $this->status === 'pending' && $this->audit_date->isPast() && !$this->audit_date->isToday();
    }
}
