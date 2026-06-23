<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class Employee extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'employee_id',
        'prefix',
        'fname',
        'lname',
        'fullname',
        'department_id',
        'shift_id',
        'location_id',
        'level',
        'qr_code_hash',
        'profile_image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function inspectionLogs()
    {
        return $this->hasMany(InspectionLog::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function checkpoints()
    {
        return $this->belongsToMany(Checkpoint::class, 'employee_checkpoint')->withTimestamps();
    }

    public function getMonthlyFailures($month = null, $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        return $this->inspectionLogs()
            ->whereYear('inspected_at', $year)
            ->whereMonth('inspected_at', $month)
            ->where('result', 'fail')
            ->count();
    }

    public function getHygieneScore($month = null, $year = null)
    {
        $failures = $this->getMonthlyFailures($month, $year);
        // Base score 100, deduct 5 per failure. Min score 0.
        return max(0, 100 - ($failures * 5));
    }

    public function getTrafficLightStatus()
    {
        $failures = $this->getMonthlyFailures();

        if ($failures <= 1) return 'green';
        if ($failures <= 3) return 'yellow';
        return 'red';
    }
}
