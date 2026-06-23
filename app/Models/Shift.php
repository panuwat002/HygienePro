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

    public static function detectCurrent(): string
    {
        $time = now()->format('H:i:s');

        $dbShift = static::where(function ($q) use ($time) {
            $q->where('start_time', '<=', $time)->where('end_time', '>=', $time);
        })->orWhere(function ($q) use ($time) {
            $q->where('start_time', '>', 'end_time')
              ->where(function ($sub) use ($time) {
                  $sub->where('start_time', '<=', $time)
                      ->orWhere('end_time', '>=', $time);
              });
        })->first();

        if ($dbShift && in_array(strtolower($dbShift->shift_name), ['morning', 'afternoon', 'night'])) {
            return strtolower($dbShift->shift_name);
        }

        $hour = now()->hour;
        if ($hour >= 6 && $hour < 13) return 'morning';
        if ($hour >= 13 && $hour < 19) return 'afternoon';
        return 'night';
    }
}
