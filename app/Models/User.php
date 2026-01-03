<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'level',
        'role',
        'line_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'line_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function inspectionSessions()
    {
        return $this->hasMany(InspectionSession::class, 'inspector_id');
    }

    public function signatures()
    {
        return $this->hasMany(Signature::class);
    }

    // --- Helper Methods for Matrix Logic ---

    public function isStaff()
    {
        return $this->role === 'staff' || $this->level <= 3;
    }

    public function isSupervisor()
    {
        return $this->role === 'supervisor' || $this->level === 4;
    }

    public function isManager()
    {
        return $this->role === 'manager' || $this->level >= 5;
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }
}
