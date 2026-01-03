<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

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
}
