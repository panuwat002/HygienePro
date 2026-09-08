<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSchedule extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\Prunable;

    /**
     * Get the prunable model query.
     */
    public function prunable()
    {
        return static::where('date', '<=', now()->subMonths(6));
    }

    protected $fillable = [
        'employee_id',
        'date',
        'shift_id',
        'start_time',
        'end_time',
        'is_day_off',
    ];

    protected $casts = [
        'date' => 'date',
        'is_day_off' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
