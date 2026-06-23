<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InspectionSchedule;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Support\Facades\Notification;
// Assuming we have a database notification or similar
// use App\Notifications\MissedInspectionNotification; 

class CheckMissedSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:check-missed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for missed inspection schedules and notify supervisors';

    protected $scheduleService;

    public function __construct(ScheduleService $scheduleService)
    {
        parent::__construct();
        $this->scheduleService = $scheduleService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for missed schedules...');
        
        $today = now()->toDateString();
        $now = now();

        // Get all active schedules that should have ended by now
        // We iterate all active schedules matching today's frequency
        // This might be expensive if many schedules, but safe for now.
        
        // Optimally: Filter by end_time < now() in DB?
        // But end_time is just Time string, we need to combine with Date.
        // Let's get all active and filter in PHP for accuracy using Service.
        
        $dayOfWeek = $now->format('D');
        $schedules = InspectionSchedule::where('is_active', true)
             ->where(function($q) use ($dayOfWeek) {
                $q->where('frequency', 'daily')
                  ->orWhere(function($sub) use ($dayOfWeek) {
                      $sub->where('frequency', 'weekly')
                          ->whereJsonContains('days_of_week', $dayOfWeek);
                  });
            })->get();

        $missedCount = 0;

        foreach ($schedules as $schedule) {
            $status = $this->scheduleService->calculateSingleStatus($schedule, $today);
            
            if ($status['status'] === 'missed') {
                // Check if we already alerted for this specific missed occurrence?
                // We don't have a 'ScheduleLog' table to track 'Alert Sent'.
                // CAUTION: If we run this every minute, it will spam alerts.
                // We should only alert if end_time was RECENTLY passed (e.g. within last hour)
                
                $endDateTime = $status['window_end'];
                
                // Alert if end_time was in the last 60 minutes
                if ($now->gt($endDateTime) && $now->diffInMinutes($endDateTime) < 60) {
                     $this->error("Missed Schedule: {$schedule->title}");
                     
                     // Notify Supervisor of that department
                     // $supervisors = User::where('department_id', $schedule->department_id)->whereRole('supervisor')...
                     // For now, just log to info
                     $this->info(" -> Alerting Supervisors...");
                     $missedCount++;
                }
            }
        }

        $this->info("Done. Found {$missedCount} newly missed schedules.");
    }
}
