<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InspectionSession extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\Prunable;

    /**
     * Get the prunable model query.
     */
    public function prunable()
    {
        return static::where('created_at', '<=', now()->subYears(2));
    }

    /**
     * Prepare the model for pruning.
     */
    protected function pruning()
    {
        // ลบไฟล์รูปภาพออกจาก Disk ก่อนที่ DB จะทำ Cascade Delete ข้อมูล InspectionLog
        $logsWithPhotos = $this->logs()->whereNotNull('photo_path')->get();
        foreach ($logsWithPhotos as $log) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($log->photo_path);
        }
    }

    protected $fillable = [
        'inspector_id',
        'type', // Added type
        'department_id',
        'inspection_date',
        'shift',
        'round',
        'status',
        'locked_at',
        // These three are "we already told someone" markers, written with
        // update(). Left out of $fillable they were silently dropped, so the
        // session stayed eligible and the hourly commands renotified the same
        // people about the same session on every tick, forever.
        'notified_at',
        'reminded_at',
        'escalated_at',
        'finished_notified_at',
        'audit_escalated_at',
        'verified_by',
        'verified_at',
        'approved_by',
        'approved_at',
        'is_locked', // Gap 2: Lock flag after final approval
        'is_audit', // Bulk Pass: Flag for audit sessions
        'random_audit_id', // The scheduled random audit this round was opened to satisfy
        'is_sampling', // Sampling inspection mode
        'sample_size', // Sample target count
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'locked_at' => 'datetime',
        'notified_at' => 'datetime',
        'reminded_at' => 'datetime',
        'escalated_at' => 'datetime',
        'finished_notified_at' => 'datetime',
        'audit_escalated_at' => 'datetime',
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

    /**
     * What this round inspected, in the words the report dropdown uses.
     *
     * 'machine' and the legacy 'area' are one bucket on every screen that
     * offers a choice, so they read the same here. Nothing printed the type
     * at all before: a daily-report row showed only the department, the shift
     * and a count, so somebody picking rounds to export could not tell a
     * personnel round from an area one until the PDF came out.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'personnel' => 'พนักงาน',
            'machine', 'area' => 'พื้นที่ / เครื่องจักร',
            default => (string) $this->type,
        };
    }

    /**
     * Set when this round was opened on a day the schedule had drawn this
     * department for a random audit. Null for an ordinary round.
     */
    public function randomAudit()
    {
        return $this->belongsTo(RandomAudit::class, 'random_audit_id');
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

    /**
     * The verification page filters by person|area|machine, while a session
     * calls its own type 'personnel'. Link builders go through here so an
     * area round does not quietly open on the person tab.
     */
    public function verificationFilterType(): string
    {
        return $this->type === 'personnel' ? 'person' : $this->type;
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

        $hasCondition = !empty($shiftNames) || !empty($customShiftIds);

        try {
            // Matched against the cached table rather than re-queried: this runs
            // once per session, and a page of 40 sessions paid for 40 identical
            // SELECTs on a reference table of a dozen rows.
            //
            // Shift rows are named "<type> <HH.mm>-<HH.mm>" (e.g. "กะบ่าย 17.00-02.00"),
            // so a generic key like 'afternoon' never matches shift_name exactly.
            // shift_type holds the canonical กะเช้า/กะบ่าย/กะดึก value it must match on.
            $shifts = $hasCondition
                ? \App\Models\Shift::cachedAll()->filter(function ($shift) use ($shiftNames, $customShiftIds) {
                    return (! empty($shiftNames) && (
                            in_array($shift->shift_name, $shiftNames, true)
                            || in_array($shift->shift_type, $shiftNames, true)
                        ))
                        || (! empty($customShiftIds) && in_array((int) $shift->id, $customShiftIds, true));
                })->values()
                : collect();
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

        $namedShifts = ! empty(array_filter(array_map('trim', explode(',', (string) $this->shift))));

        // A round that named no shift at all (legacy rows saved before one was required)
        // means the whole department.
        if (! $namedShifts) {
            return $allEmployees;
        }

        // A round that DID name shifts but resolved none — the shift row was deleted, say —
        // must target nobody rather than silently falling back to the whole department.
        // Bulk pass runs off this list, so the open version would pass every employee in the
        // department, day-offs included.
        if (empty($validShiftIds) && empty($shiftNames)) {
            return $allEmployees->take(0);
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
     * Get target employees specifically for this session.
     * When is_sampling is true, returns the deterministic sampled subset matching sample_size.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSessionTargetEmployees()
    {
        $targetEmployees = $this->getTargetEmployees();

        if ($this->is_sampling && $this->sample_size > 0) {
            $targetEmployees = $targetEmployees
                ->sortBy(fn($emp) => md5($this->id . '-' . $emp->id))
                ->values()
                ->take($this->sample_size);
        }

        return $targetEmployees;
    }

    /**
     * Get human-readable label for the session shift.
     * Smartly combines common prefixes (e.g., "กะเช้า (07.00-16.00, 08.00-17.00)") to avoid redundancy.
     */
    public function getShiftLabelAttribute(): string
    {
        $shiftsById = $this->getResolvedShifts()['shifts']->keyBy('id');
        $sessionShifts = array_filter(array_map('trim', explode(',', (string) $this->shift)));

        $rawNames = [];
        foreach ($sessionShifts as $s) {
            // A generic key stands for a whole shift_type group, so it keeps its generic
            // label — spelling out every "กะบ่าย HH.mm-HH.mm" variant would flood the header.
            $generic = match (strtolower($s)) {
                'morning' => 'กะเช้า',
                'afternoon' => 'กะบ่าย',
                'night' => 'กะดึก',
                default => null,
            };

            if ($generic !== null) {
                $rawNames[] = $generic;
            } elseif (str_starts_with($s, 'custom_')) {
                $id = (int) str_replace('custom_', '', $s);
                $rawNames[] = $shiftsById->get($id)?->shift_name ?? $s;
            } else {
                $rawNames[] = $s;
            }
        }

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
