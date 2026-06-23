<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalFlow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'target_model',
        'is_active',
    ];

    public function steps()
    {
        return $this->hasMany(ApprovalFlowStep::class)->orderBy('step_order');
    }

    public function requests()
    {
        return $this->hasMany(ApprovalRequest::class);
    }
}
