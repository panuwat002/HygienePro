<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\LogsActivity;

class Location extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'location_name',
        'department_id', // the department that runs this area and owns its findings
        'description',
        'image',
    ];

    /**
     * Who runs this area, and therefore who a finding raised in it belongs to.
     *
     * Null until somebody says. Every read of it falls back to the round's own
     * department, which is what the system used before areas had an owner at
     * all - so an unassigned room behaves exactly as it did rather than
     * breaking.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function checkpoints(): BelongsToMany
    {
        return $this->belongsToMany(Checkpoint::class, 'location_checkpoint');
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    public function inspectionLogs(): HasMany
    {
        return $this->hasMany(InspectionLog::class);
    }
}
