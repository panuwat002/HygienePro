<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'location_id',
        'name',
        'code',
        'description',
        'image',
        'is_active',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function checkpoints()
    {
        return $this->belongsToMany(Checkpoint::class, 'machine_checkpoint');
    }

    public function inspectionLogs()
    {
        return $this->hasMany(InspectionLog::class);
    }
}
