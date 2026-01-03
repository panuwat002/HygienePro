<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckpointCategory extends Model
{
    protected $fillable = [
        'name',
        'icon',
    ];

    public function checkpoints(): HasMany
    {
        return $this->hasMany(Checkpoint::class, 'category_id');
    }
}
