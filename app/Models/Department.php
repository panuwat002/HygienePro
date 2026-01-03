<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'dept_name',
        'dept_code',
        'visibility_type',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function inspectionSessions()
    {
        return $this->hasMany(InspectionSession::class);
    }

    public function isGlobal()
    {
        return $this->visibility_type === 'global';
    }

    public function isIsolated()
    {
        return $this->visibility_type === 'isolated';
    }
}
