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
