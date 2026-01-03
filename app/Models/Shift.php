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
}
