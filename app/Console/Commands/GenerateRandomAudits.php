<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\RandomAudit;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateRandomAudits extends Command
{
    protected $signature = 'audit:generate {--week= : ISO week number (defaults to current + next week)}';
    protected $description = 'Generate random audit schedule for each department (Personnel, Machine, Area)';

    public function handle()
    {
        // ถ้าระบุ --week มา ให้สร้างแค่สัปดาห์นั้น
        // ถ้าไม่ระบุ ให้สร้างทั้ง "สัปดาห์ปัจจุบัน" และ "สัปดาห์หน้า" เพื่อให้ครอบคลุม
        $specificWeek = $this->option('week');

        if ($specificWeek) {
            $weeks = [(int) $specificWeek];
        } else {
            $currentWeek = now()->isoWeek();
            $nextWeek = now()->addWeek()->isoWeek();
            $weeks = array_unique([$currentWeek, $nextWeek]);
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($weeks as $weekNumber) {
            $year = now()->year;

            // Handle year boundary: if week number is less than current week and we didn't specify,
            // it means we've crossed into the next year's weeks
            if (!$specificWeek && $weekNumber < now()->isoWeek()) {
                $year = now()->addYear()->year;
            }

            $result = $this->generateForWeek($weekNumber, $year);
            $totalCreated += $result['created'];
            $totalSkipped += $result['skipped'];
        }

        $this->newLine();
        $this->info("📋 Grand Total: Created {$totalCreated} audit(s), Skipped {$totalSkipped} (already scheduled)");

        Log::info("RandomAudit generation completed", [
            'weeks' => $weeks,
            'created' => $totalCreated,
            'skipped' => $totalSkipped,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Generate random audits for a specific week/year.
     */
    private function generateForWeek(int $weekNumber, int $year): array
    {
        $this->info("🎲 Generating Random Audit Schedule for Week {$weekNumber}, Year {$year}...");

        $departments = Department::all();

        if ($departments->isEmpty()) {
            $this->warn('No departments found.');
            Log::warning('RandomAudit: No departments found in database.');
            return ['created' => 0, 'skipped' => 0];
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

            // Skip if audit date is in the past (don't create audits for days that already passed)
            if ($auditDate->isPast() && !$auditDate->isToday()) {
                // Try to find a future day within the same week
                $found = false;
                for ($day = now()->dayOfWeekIso; $day <= 6; $day++) {
                    $candidateDate = Carbon::now()->setISODate($year, $weekNumber, $day);
                    if ($candidateDate->isFuture() || $candidateDate->isToday()) {
                        $auditDate = $candidateDate;
                        $found = true;
                        break;
                    }
                }
                // If no future date available in this week, still create the record
                // (it will show as 'missed' for tracking purposes)
                if (!$found) {
                    $auditDate = Carbon::now()->setISODate($year, $weekNumber, $randomDayOfWeek);
                }
            }

            // Random shift: morning or afternoon only
            $shifts = ['morning', 'afternoon'];
            $randomShift = $shifts[array_rand($shifts)];

            // Create the audit record
            try {
                RandomAudit::create([
                    'department_id' => $dept->id,
                    'audit_date' => $auditDate->toDateString(),
                    'shift' => $randomShift,
                    'status' => 'pending',
                    'week_number' => $weekNumber,
                    'year' => $year,
                    'sample_size' => 10,
                ]);

                $shiftLabel = $randomShift === 'morning' ? 'เช้า' : 'บ่าย';
                $this->info("  ✅ {$dept->dept_name}: {$auditDate->format('l d/m/Y')} กะ{$shiftLabel}");
                $created++;
            } catch (\Exception $e) {
                $this->error("  ❌ {$dept->dept_name}: Error - {$e->getMessage()}");
                Log::error("RandomAudit creation failed", [
                    'department' => $dept->dept_name,
                    'week' => $weekNumber,
                    'year' => $year,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("📋 Week {$weekNumber}: Created {$created}, Skipped {$skipped}");

        return ['created' => $created, 'skipped' => $skipped];
    }
}
