<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InspectionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'location_id',
        'machine_id',
        'checkpoint_id',
        'employee_id',
        'result',
        'note',
        'correction_action',
        'photo_path',
        'inspected_at',
        'checkpoint_title_snapshot',
        'dept_snapshot',
    ];

    protected $casts = [
        'inspected_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function checkpoint()
    {
        return $this->belongsTo(Checkpoint::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function session()
    {
        return $this->belongsTo(InspectionSession::class);
    }

    public function parentLog()
    {
        return $this->belongsTo(InspectionLog::class, 'parent_id');
    }

    public function rechecks()
    {
        return $this->hasMany(InspectionLog::class, 'parent_id');
    }
}
