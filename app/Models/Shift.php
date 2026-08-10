<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'shift_name',
        'start_time',
        'end_time',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public static function detectCurrent(string $lastShift = null, string $lastTime = null): string
    {
        $time = now()->format('H:i:s');
        $hour = now()->hour;

        // Try AI Smart Detection first
        $aiResult = \App\Services\AIService::detectShift($time, $hour, $lastShift, $lastTime);
        if ($aiResult && isset($aiResult['shift']) && $aiResult['is_smart_detected']) {
            return $aiResult['shift'];
        }

        $dbShift = static::where(function ($q) use ($time) {
            $q->where('start_time', '<=', $time)->where('end_time', '>=', $time);
        })->orWhere(function ($q) use ($time) {
            $q->where('start_time', '>', 'end_time')
              ->where(function ($sub) use ($time) {
                  $sub->where('start_time', '<=', $time)
                      ->orWhere('end_time', '>=', $time);
              });
        })->first();

        if ($dbShift) {
            $name = mb_strtolower($dbShift->shift_name);
            if (in_array($name, ['morning', 'กะเช้า'])) return 'morning';
            if (in_array($name, ['afternoon', 'กะบ่าย'])) return 'afternoon';
            if (in_array($name, ['night', 'กะดึก'])) return 'night';
            return $name; // Fallback to whatever name they put
        }

        $hour = now()->hour;
        if ($hour >= 6 && $hour < 13) return 'morning';
        if ($hour >= 13 && $hour < 19) return 'afternoon';
        return 'night';
    }
}
