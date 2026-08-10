<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\RandomAudit;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRandomAudits extends Command
{
    protected $signature = 'audit:generate {--week= : ISO week number (defaults to next week)}';
    protected $description = 'Generate random audit schedule for each department (1 random day Mon-Sat, 1 random shift morning/afternoon)';

    public function handle()
    {
        $weekNumber = $this->option('week') ?? now()->addWeek()->isoWeek();
        $year = now()->year;

        // If the specified week is in the past year context, adjust
        if ($weekNumber < now()->isoWeek() && !$this->option('week')) {
            $year = now()->addYear()->year;
        }

        $this->info("🎲 Generating Random Audit Schedule for Week {$weekNumber}, Year {$year}...");

        $departments = Department::all();

        if ($departments->isEmpty()) {
            $this->warn('No departments found.');
            return Command::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($departments as $dept) {
            // Check if audit already exists for this dept/week/year
            $existing = RandomAudit::where('department_id', $dept->id)
                ->where('week_number', $weekNumber)
                ->where('year', $year)
                ->first();

            if ($existing) {
                $this->line("  ⏭️  {$dept->dept_name}: Already scheduled on {$existing->audit_date->format('D d/m')} ({$existing->shift})");
                $skipped++;
                continue;
            }

            // Random day: Monday(1) to Saturday(6)
            $randomDayOfWeek = rand(1, 6);

            // Calculate the actual date from ISO week + day
            $auditDate = Carbon::now()
                ->setISODate($year, $weekNumber, $randomDayOfWeek);

            // Random shift: morning or afternoon only
            $shifts = ['morning', 'afternoon'];
            $randomShift = $shifts[array_rand($shifts)];

            // Create the audit record
            RandomAudit::create([
                'department_id' => $dept->id,
                'audit_date' => $auditDate->toDateString(),
                'shift' => $randomShift,
                'status' => 'pending',
                'week_number' => $weekNumber,
                'year' => $year,
                'sample_size' => 10, // Default: audit 10 employees
            ]);

            $shiftLabel = $randomShift === 'morning' ? 'เช้า' : 'บ่าย';
            $this->info("  ✅ {$dept->dept_name}: {$auditDate->format('l d/m/Y')} กะ{$shiftLabel}");
            $created++;
        }

        $this->newLine();
        $this->info("📋 Summary: Created {$created} audit(s), Skipped {$skipped} (already scheduled)");

        return Command::SUCCESS;
    }
}
