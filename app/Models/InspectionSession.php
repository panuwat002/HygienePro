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
        'is_sampling', // Sampling inspection mode
        'sample_size', // Sample target count
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'locked_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'is_locked' => 'boolean', // Gap 2: Lock flag
        'is_audit' => 'boolean', // Bulk Pass: Audit flag
        'is_sampling' => 'boolean',
        'sample_size' => 'integer',
    ];

    /**
     * Check if session is editable by given user (enforces Same-Day Lock)
     */
    public function isEditableBy(User $user): bool
    {
        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if ($this->is_locked) {
            return false;
        }

        // Cross-midnight date logic (hours 0-5 belong to yesterday session)
        $today = now()->hour < 6 ? now()->subDay()->toDateString() : now()->toDateString();
        $sessionDate = $this->inspection_date ? $this->inspection_date->toDateString() : null;

        // Same-Day Restriction for Inspectors: Cannot edit sessions from past days
        return $sessionDate === $today;
    }

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

    /**
     * Parse the session's shift attribute into a list of shift IDs and shift names in DB.
     * Handles single shifts ('morning'), custom shifts ('custom_11'), and comma-separated combinations.
     *
     * @return array{shift_ids: array<int>, shift_names: array<string>, shifts: \Illuminate\Database\Eloquent\Collection}
     */
    public function getResolvedShifts(): array
    {
        $sessionShifts = array_filter(array_map('trim', explode(',', (string) $this->shift)));
        
        $shiftNames = [];
        $customShiftIds = [];

        foreach ($sessionShifts as $s) {
            $names = match(strtolower($s)) {
                'morning' => ['morning', 'กะเช้า'],
                'afternoon' => ['afternoon', 'กะบ่าย'],
                'night' => ['night', 'กะดึก'],
                default => str_starts_with($s, 'custom_') ? [] : [$s]
            };
            $shiftNames = array_merge($shiftNames, $names);

            if (str_starts_with($s, 'custom_')) {
                $id = (int) str_replace('custom_', '', $s);
                if ($id > 0) {
                    $customShiftIds[] = $id;
                }
            }
        }

        $validShiftIdsQuery = \App\Models\Shift::query();
        $hasCondition = false;
        if (!empty($shiftNames)) {
            $validShiftIdsQuery->whereIn('shift_name', $shiftNames);
            $hasCondition = true;
        }
        if (!empty($customShiftIds)) {
            if ($hasCondition) {
                $validShiftIdsQuery->orWhereIn('id', $customShiftIds);
            } else {
                $validShiftIdsQuery->whereIn('id', $customShiftIds);
                $hasCondition = true;
            }
        }

        try {
            $shifts = $hasCondition ? $validShiftIdsQuery->get() : collect();
        } catch (\Throwable $e) {
            $shifts = collect();
        }
        $shiftIds = $shifts->pluck('id')->toArray();
        $allShiftNames = array_unique(array_merge($shiftNames, $shifts->pluck('shift_name')->toArray()));

        return [
            'shift_ids' => $shiftIds,
            'shift_names' => $allShiftNames,
            'shifts' => $shifts,
        ];
    }

    /**
     * Get active employees in this session's department matching the session's shift(s),
     * taking into account EmployeeSchedule for today first, then falling back to Employee default shift.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTargetEmployees()
    {
        $resolved = $this->getResolvedShifts();
        $validShiftIds = $resolved['shift_ids'];
        $shiftNames = $resolved['shift_names'];

        $allEmployees = \App\Models\Employee::with(['location', 'department', 'shift'])
            ->where('department_id', $this->department_id)
            ->where('is_active', true)
            ->get();

        if (empty($validShiftIds) && empty($shiftNames)) {
            return $allEmployees;
        }

        $schedules = \App\Models\EmployeeSchedule::where('date', $this->inspection_date ? $this->inspection_date->startOfDay() : now()->startOfDay())
            ->whereIn('employee_id', $allEmployees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        return $allEmployees->filter(function ($emp) use ($schedules, $validShiftIds, $shiftNames) {
            $sch = $schedules->get($emp->id);
            if ($sch) {
                return in_array($sch->shift_id, $validShiftIds) && !$sch->is_day_off;
            }
            return in_array($emp->shift_id, $validShiftIds) || in_array(optional($emp->shift)->shift_name, $shiftNames);
        })->values();
    }

    /**
     * Get human-readable label for the session shift.
     * Smartly combines common prefixes (e.g., "กะเช้า (07.00-16.00, 08.00-17.00)") to avoid redundancy.
     */
    public function getShiftLabelAttribute(): string
    {
        $resolved = $this->getResolvedShifts();
        $shifts = $resolved['shifts'];

        if ($shifts->isNotEmpty()) {
            $rawNames = $shifts->pluck('shift_name')->toArray();
            return $this->formatShiftNames($rawNames);
        }

        // Fallback formatting if shifts model not matched directly
        $sessionShifts = array_filter(array_map('trim', explode(',', (string) $this->shift)));
        $rawNames = array_map(function($s) {
            return match(strtolower($s)) {
                'morning' => 'กะเช้า',
                'afternoon' => 'กะบ่าย',
                'night' => 'กะดึก',
                default => $s,
            };
        }, $sessionShifts);

        return $this->formatShiftNames($rawNames);
    }

    /**
     * Smartly format a list of shift names to eliminate redundant prefixes like "กะเช้า 07... กะเช้า 08...".
     */
    protected function formatShiftNames(array $names): string
    {
        if (count($names) <= 1) {
            return implode(', ', $names);
        }

        // Group by common prefix (e.g., "กะเช้า", "กะบ่าย", "กะดึก")
        $grouped = [];
        foreach ($names as $name) {
            $parts = explode(' ', trim($name), 2);
            $prefix = $parts[0];
            $suffix = $parts[1] ?? '';
            $grouped[$prefix][] = $suffix;
        }

        $formatted = [];
        foreach ($grouped as $prefix => $suffixes) {
            $cleanSuffixes = array_filter(array_map('trim', $suffixes));
            if (!empty($cleanSuffixes) && count($cleanSuffixes) === count($suffixes) && count($suffixes) > 1) {
                $formatted[] = $prefix . ' (' . implode(', ', $cleanSuffixes) . ')';
            } else {
                foreach ($suffixes as $suffix) {
                    $formatted[] = $suffix !== '' ? "$prefix $suffix" : $prefix;
                }
            }
        }

        return implode(', ', $formatted);
    }
}
