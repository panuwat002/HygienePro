<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\LogsActivity;

class User extends Authenticatable
{
    use HasFactory, Notifiable, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'employee_code',
        'email',
        'password',
        'department_id',
        'level',
        'role',
        'manager_id',
        'line_token',
        'signature_path',
        // Without this the settings page silently saved nothing: update()
        // dropped the key, redirected with a success message, and left every
        // account on the defaults in wantsEmailFor().
        'notification_preferences',
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
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Check if the user wants to receive an email for a specific event.
     *
     * UAT (Sep 2026): every event defaults to true so testers exercise all of
     * them without editing each account first. The four marked below used to
     * default to false as opt-in noise control — flip them back before
     * production if the volume turns out to be too much. A user who sets a
     * preference explicitly still wins over these defaults either way.
     */
    public function wantsEmailFor(string $event): bool
    {
        $prefs = $this->notification_preferences ?? [];

        // Define default settings
        $defaults = [
            'email_session_started' => true,        // opt-in before UAT
            'email_session_finished_pass' => true,  // opt-in before UAT
            'email_session_finished_fail' => true,
            'email_order_reclean' => true,          // opt-in before UAT
            'email_session_verified' => true,       // opt-in before UAT
            'email_car_new' => true,
            'email_car_resolved' => true,
            'email_car_closed' => true,
            'email_car_overdue' => true,
            'email_awaiting_approval' => true,
        ];

        // If the user has specifically configured it, use their setting
        if (array_key_exists($event, $prefs)) {
            return (bool) $prefs[$event];
        }

        // Otherwise use the default
        return $defaults[$event] ?? true;
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(User::class, 'manager_id');
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

    // --- Role Matrix Methods (PRD Compliance) ---

    /**
     * Check if user belongs to QA department
     * QA users have Global scope and can inspect all departments
     */
    public function isQA(): bool
    {
        if (!$this->department) {
            return false;
        }

        $deptName = strtolower($this->department->dept_name ?? '');
        $deptCode = strtolower($this->department->dept_code ?? '');

        return str_contains($deptName, 'qa') 
            || str_contains($deptName, 'quality')
            || str_contains($deptName, 'คุณภาพ')
            || $deptCode === 'qa';
    }

    /**
     * Is this user confined to their own department's data?
     *
     * Fail closed: a user with no department is confined, never exempt. The report scopes
     * used to start with `$user->department && ...`, so a null department skipped filtering
     * altogether and returned every department's data — and department_id is optional on the
     * user form, so one unfilled field was enough to reach that state.
     */
    public function isRestrictedToOwnDepartment(): bool
    {
        if ($this->isAdmin()) {
            return false;
        }

        if (! $this->department) {
            return true;
        }

        return $this->department->visibility_type === 'isolated';
    }

    /**
     * The department id to confine a restricted user to.
     *
     * Never null. A user without a department has to match no rows rather than every row,
     * and that must not depend on whether the column being filtered happens to be NOT NULL.
     * No department row has id 0.
     */
    public function scopedDepartmentId(): int
    {
        return (int) ($this->department_id ?? 0);
    }

    /**
     * Check if user is Executive (C-Suite) - Read-Only Dashboard access
     */
    public function isExecutive(): bool
    {
        return $this->role === 'executive' 
            || ($this->level >= 6 && !$this->isQA() && !$this->isAdmin());
    }

    /**
     * Check if user has Global visibility (can see all departments)
     * QA, Admin, and Executives have Global visibility
     */
    public function hasGlobalVisibility(): bool
    {
        // Admin always has global scope
        if ($this->isAdmin()) {
            return true;
        }

        // QA department has global scope
        if ($this->isQA()) {
            return true;
        }

        // Executives have global read-only scope
        if ($this->isExecutive()) {
            return true;
        }

        // Check department visibility type
        if ($this->department && $this->department->visibility_type === 'global') {
            return true;
        }

        return false;
    }

    /**
     * Check if user can view data from a specific department
     */
    public function canViewDepartment(int $departmentId): bool
    {
        // Global visibility users can view all departments
        if ($this->hasGlobalVisibility()) {
            return true;
        }

        // Isolated scope users can only view their own department
        return $this->department_id === $departmentId;
    }

    // --- Action Permission Methods ---

    /**
     * Can user perform inspections? (QA Staff only)
     */
    public function canInspect(): bool
    {
        return $this->isQA() && (
            $this->role === 'staff' || $this->level <= 3 || 
            $this->isSupervisor() || 
            $this->isManager()
        );
    }

    /**
     * Can user verify inspections? (QA Supervisor only)
     */
    public function canVerify(): bool
    {
        return $this->isQA() && ($this->role === 'supervisor' || $this->level === 4);
    }

    /**
     * Can user approve inspections? (QA Manager only, Level >= 5)
     */
    public function canApprove(): bool
    {
        return $this->isQA() && ($this->role === 'manager' || $this->level >= 5);
    }

    /**
     * Can user acknowledge for a department? (Dept Head, Level >= 4, own dept only)
     */
    public function canAcknowledge(int $departmentId): bool
    {
        // Must be non-QA
        if ($this->isQA()) {
            return false;
        }

        // Must have Level >= 4 (Supervisor or Manager of department)
        if ($this->level < 4) {
            return false;
        }

        // Admin can acknowledge any department
        if ($this->isAdmin()) {
            return true;
        }

        // Must be from the same department
        return $this->department_id === $departmentId;
    }
}
