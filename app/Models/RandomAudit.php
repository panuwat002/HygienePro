<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RandomAudit extends Model
{
    use HasFactory;

    /**
     * An audit is generated 'pending', claimed 'in_progress' by the round that
     * sets out to satisfy it, and ends either 'completed' or 'missed'.
     *
     * 'in_progress' is new. Before it, nothing in the codebase ever wrote this
     * column after the row was created, so every audit ever generated is still
     * sitting at 'pending' on the dashboard.
     */
    public const PENDING = 'pending';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED = 'completed';
    public const MISSED = 'missed';

    /**
     * Rounds started before 06:00 belong to the previous day's work - the same
     * rule InspectionService::startSession() dates a session by. An audit is
     * only overdue once its date is behind THAT day, not behind the calendar.
     */
    public static function currentBusinessDate(): string
    {
        return now()->hour < 6
            ? now()->subDay()->toDateString()
            : now()->toDateString();
    }

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
     * The round that claimed this audit - the evidence that it was actually
     * carried out, and what an external auditor asks to see.
     */
    public function session()
    {
        return $this->hasOne(InspectionSession::class, 'random_audit_id');
    }

    /**
     * The dashboard card has always printed {{ $audit->shift_label }}, and
     * this model has never had one, so the shift line rendered empty.
     */
    public function getShiftLabelAttribute(): string
    {
        return match ($this->shift) {
            'morning' => 'กะเช้า',
            'afternoon' => 'กะบ่าย',
            'night' => 'กะดึก',
            default => (string) $this->shift,
        };
    }

    /**
     * The canonical Shift type this audit asks for. A session stores its shift
     * as free keys ('custom_11,custom_12'), so the two can only be compared
     * once both sides are reduced to กะเช้า/กะบ่าย/กะดึก.
     */
    public function shiftType(): string
    {
        return match ($this->shift) {
            'morning' => Shift::TYPE_MORNING,
            'afternoon' => Shift::TYPE_AFTERNOON,
            'night' => Shift::TYPE_NIGHT,
            default => (string) $this->shift,
        };
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
     * Scope: audits still waiting on somebody - not yet completed or missed.
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::PENDING, self::IN_PROGRESS]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::IN_PROGRESS], true);
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
        return $this->isOpen()
            && $this->audit_date->toDateString() < self::currentBusinessDate();
    }
}
