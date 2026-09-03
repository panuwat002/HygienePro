<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'shift_name',
        'shift_type',
        'start_time',
        'end_time',
        'is_dayoff',
        'department_id',
    ];

    /**
     * Canonical shift_type values.
     *
     * shift_name is free text ("กะบ่าย 17.00-02.00", "กะโอที"), so shift_type is the only
     * field a generic session key ('morning'/'afternoon'/'night') can resolve against.
     * See InspectionSession::getResolvedShifts().
     */
    public const TYPE_MORNING = 'กะเช้า';
    public const TYPE_AFTERNOON = 'กะบ่าย';
    public const TYPE_NIGHT = 'กะดึก';
    public const TYPE_DAYOFF = 'วันหยุด';

    public static function types(): array
    {
        return [self::TYPE_MORNING, self::TYPE_AFTERNOON, self::TYPE_NIGHT, self::TYPE_DAYOFF];
    }

    /**
     * Work out which canonical type a shift belongs to.
     *
     * Prefers the shift_name prefix, since that is what ShiftSeeder writes and what admins
     * type by hand. Falls back to the same start_time buckets ShiftSeeder groups by, so an
     * off-pattern name like "กะโอที 17.00-02.00" still lands in กะบ่าย.
     */
    public static function deriveType(?string $shiftName, ?string $startTime, bool $isDayOff = false): string
    {
        if ($isDayOff) {
            return self::TYPE_DAYOFF;
        }

        $name = mb_strtolower(trim((string) $shiftName));
        $prefixes = [
            self::TYPE_MORNING => ['กะเช้า', 'morning'],
            self::TYPE_AFTERNOON => ['กะบ่าย', 'afternoon'],
            self::TYPE_NIGHT => ['กะดึก', 'night'],
            self::TYPE_DAYOFF => ['วันหยุด', 'dayoff', 'day off'],
        ];

        foreach ($prefixes as $type => $candidates) {
            foreach ($candidates as $prefix) {
                if ($name !== '' && str_starts_with($name, $prefix)) {
                    return $type;
                }
            }
        }

        if (empty($startTime)) {
            return self::TYPE_NIGHT;
        }

        $parts = explode(':', $startTime);
        $decimalTime = (int) ($parts[0] ?? 0) + ((int) ($parts[1] ?? 0) / 60);

        if ($decimalTime >= 6 && $decimalTime <= 12) {
            return self::TYPE_MORNING;
        }
        if ($decimalTime >= 12.5 && $decimalTime <= 18) {
            return self::TYPE_AFTERNOON;
        }

        return self::TYPE_NIGHT;
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Detect the current shift based on the current time, matching against DB records.
     * Returns the Shift model object, or null if no shift matches.
     */
    public static function detectCurrentShift(): ?self
    {
        $time = now()->format('H:i:s');

        // Case 1: Normal shifts where start_time < end_time (e.g. 08:00 - 17:00)
        // Case 2: Overnight shifts where start_time > end_time (e.g. 19:00 - 04:00)
        $shift = static::where(function ($q) use ($time) {
                // Normal shift: start <= current_time <= end
                $q->whereColumn('start_time', '<=', 'end_time')
                  ->where('start_time', '<=', $time)
                  ->where('end_time', '>=', $time);
            })
            ->orWhere(function ($q) use ($time) {
                // Overnight shift: start > end, and (current >= start OR current <= end)
                $q->whereColumn('start_time', '>', 'end_time')
                  ->where(function ($sub) use ($time) {
                      $sub->where('start_time', '<=', $time)
                          ->orWhere('end_time', '>=', $time);
                  });
            })
            ->orderBy('start_time', 'asc')
            ->first();

        \Illuminate\Support\Facades\Log::info('[ShiftDetect] time=' . $time . ' shift=' . ($shift ? $shift->shift_name . ' (id=' . $shift->id . ', start=' . $shift->start_time . ', end=' . $shift->end_time . ')' : 'NULL'));

        return $shift;
    }

    /**
     * Detect the current shift string based on real-time standard 3-shift windows:
     * - 06:00 - 13:00 => 'morning' (กะเช้า)
     * - 13:00 - 19:00 => 'afternoon' (กะบ่าย)
     * - 19:00 - 06:00 => 'night' (กะดึก)
     */
    public static function detectCurrent(string $lastShift = null, string $lastTime = null): string
    {
        $hour = now()->hour;

        if ($hour >= 6 && $hour < 13) {
            return 'morning';
        }
        if ($hour >= 13 && $hour < 19) {
            return 'afternoon';
        }
        return 'night';
    }
}
