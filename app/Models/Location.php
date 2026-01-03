<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Location extends Model
{
    protected $fillable = [
        'location_name',
        'description',
        'image',
    ];

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
}
