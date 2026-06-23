<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'department_id',
        'targetable_type',
        'targetable_id',
        'frequency',
        'days_of_week',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'is_active' => 'boolean',
        'start_time' => 'datetime:H:i', // Cast to Carbon but format as H:i
        'end_time' => 'datetime:H:i',   // Cast to Carbon but format as H:i
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the parent targetable model (location or machine).
     */
    public function targetable()
    {
        return $this->morphTo();
    }
}
