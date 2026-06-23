<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurgeOldDepartments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-old-departments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force delete specific departments (ID 1, 2, 3) and their associated users and employees, overriding soft deletes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $departmentIds = [1, 2, 3];
        $this->info("Starting purge of departments: " . implode(', ', $departmentIds));

        DB::transaction(function () use ($departmentIds) {
            foreach ($departmentIds as $id) {
                $dept = Department::find($id);
                if (!$dept) {
                    $this->warn("Department ID {$id} not found. Skipping.");
                    continue;
                }

                $this->info("Processing Department ID {$id} ({$dept->dept_name})...");

                // 1. Force delete all employees in this department (including soft deleted)
                $employeesQuery = Employee::withTrashed()->where('department_id', $id);
                $employeeCount = $employeesQuery->count();
                $employeesQuery->forceDelete();
                $this->line("  -> Force deleted {$employeeCount} employees.");

                // 2. Force delete all users in this department
                $usersQuery = User::where('department_id', $id);
                $userCount = $usersQuery->count();
                $usersQuery->forceDelete();
                $this->line("  -> Force deleted {$userCount} users.");

                // 3. Delete the department itself
                $dept->delete();
                $this->line("  -> Deleted department {$dept->dept_name}.");
            }
        });

        $this->info("Purge completed successfully.");
    }
}
