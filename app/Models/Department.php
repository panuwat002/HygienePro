<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\LogsActivity;

class Department extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'dept_name',
        'dept_code',
        'visibility_type',
        'parent_department_id',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function manager()
    {
        return $this->hasOne(User::class)->where(function ($q) {
            $q->where('role', 'manager')->orWhere('level', '>=', 5);
        });
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function inspectionSessions()
    {
        return $this->hasMany(InspectionSession::class);
    }

    /**
     * The department this one sits under, if it is a work area rather than a
     * department in its own right - ห้องแคะ under Production, say.
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_department_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_department_id');
    }

    /**
     * Who answers for this department.
     *
     * A sub-department usually has no user of its own - the room is staffed by
     * operators, not by a head - so the list would print a blank where the
     * parent's head belongs, which reads as "nobody owns this". Falls back up
     * one level rather than inventing a user.
     */
    public function responsibleManager(): ?User
    {
        return $this->manager ?? $this->parent?->manager;
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
