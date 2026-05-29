<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use App\Models\User;
use App\Models\InspectionLog;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use Bootstrap 5 for pagination globally
        Paginator::useBootstrapFive();
        
        // --- Role Matrix Gate Definitions (PRD Compliance) ---

        /**
         * Can Inspect: QA Staff only
         * - Must be from QA department
         * - Role = staff OR Level <= 3
         */
        Gate::define('inspect', function (User $user) {
            return $user->canInspect() || $user->isAdmin();
        });

        /**
         * Can Verify: QA Supervisor only
         * - Must be from QA department
         * - Role = supervisor OR Level = 4
         */
        Gate::define('verify', function (User $user) {
            return $user->canVerify() || $user->isAdmin();
        });

        /**
         * Can Approve: QA Manager only
         * - Must be from QA department
         * - Role = manager OR Level >= 5
         */
        Gate::define('approve', function (User $user) {
            return $user->canApprove() || $user->isAdmin();
        });

        /**
         * Can Acknowledge: Dept Head only (for their own department)
         * - Must NOT be from QA department
         * - Level >= 4 (Supervisor or Manager of department)
         * - Must be from the same department as the employee who failed
         */
        Gate::define('acknowledge', function (User $user, $departmentId = null) {
            if ($user->isAdmin()) {
                return true;
            }
            
            if ($departmentId === null) {
                // General check: can this user acknowledge their own department?
                return !$user->isQA() && $user->level >= 4;
            }
            
            return $user->canAcknowledge($departmentId);
        });

        /**
         * View All Departments: Users with Global scope
         * - QA users
         * - Admins
         * - Executives
         */
        Gate::define('view-all-departments', function (User $user) {
            return $user->hasGlobalVisibility();
        });

        /**
         * View Dashboard Only: Executives (Read-Only)
         */
        Gate::define('dashboard-only', function (User $user) {
            return $user->isExecutive();
        });

        /**
         * Manage System (Users, Audit Logs): Admins only
         */
        Gate::define('manage-system', function (User $user) {
            return $user->isAdmin();
        });

        /**
         * Manage Master Data: Admins, Managers, and QA Supervisors
         */
        Gate::define('manage-master-data', function (User $user) {
            return $user->isAdmin() || $user->isManager() || ($user->isQA() && $user->isSupervisor());
        });

        /**
         * Manage Employees & Shifts: Any Supervisor or Manager
         * - Isolated scope applies in the controller
         */
        Gate::define('manage-employees', function (User $user) {
            return $user->level >= 4 || $user->role === 'supervisor' || $user->role === 'manager' || $user->isAdmin();
        });

        /**
         * View Reports: Any Manager, QA Supervisor, or Admin
         */
        Gate::define('view-reports', function (User $user) {
            return $user->isManager() || ($user->isQA() && $user->isSupervisor()) || $user->isAdmin();
        });

        /**
         * Verification workspace: Supervisors (verify) and Managers (approve)
         */
        Gate::define('access-verification', function (User $user) {
            return $user->can('verify') || $user->can('approve');
        });
    }
}
