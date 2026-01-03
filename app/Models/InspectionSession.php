<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InspectionSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'inspector_id',
        'department_id',
        'inspection_date',
        'shift',
        'round',
        'status',
        'locked_at',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'locked_at' => 'datetime',
    ];

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

    public function isLocked()
    {
        return !is_null($this->locked_at);
    }
}
