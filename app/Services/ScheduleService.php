<?php

namespace App\Services;

use App\Models\InspectionSchedule;
use App\Models\InspectionLog;
use App\Models\Location;
use App\Models\Machine;
use Carbon\Carbon;

class ScheduleService
{
    /**
     * Get compliance status for a set of schedules on a specific date.
     * 
     * @param \Illuminate\Support\Collection $schedules
     * @param string $date (Y-m-d)
     * @return \Illuminate\Support\Collection
     */
    public function getComplianceStatus($schedules, $date)
    {
        return $schedules->map(function ($schedule) use ($date) {
            return $this->calculateSingleStatus($schedule, $date);
        });
    }

    /**
     * Calculate status for a single schedule.
     */
    public function calculateSingleStatus(InspectionSchedule $schedule, $date)
    {
        $status = 'pending'; // pending, completed, missed, in_progress (if applicable)
        
        // Define time window for this specific date
        // start_time/end_time may be Carbon objects (datetime cast) or strings
        $startTime = $schedule->start_time instanceof \Carbon\Carbon 
            ? $schedule->start_time->format('H:i:s') 
            : $schedule->start_time;
        $endTime = $schedule->end_time instanceof \Carbon\Carbon 
            ? $schedule->end_time->format('H:i:s') 
            : $schedule->end_time;
        
        $startDateTime = Carbon::parse("$date $startTime");
        $endDateTime = Carbon::parse("$date $endTime");
        
        if ($endDateTime->lt($startDateTime)) {
            $endDateTime->addDay();
        }

        // Find logs
        $logQuery = InspectionLog::whereDate('inspected_at', $date);
        
        if ($schedule->targetable_type === Location::class) {
            $logQuery->where('location_id', $schedule->targetable_id);
        } elseif ($schedule->targetable_type === Machine::class) {
            $logQuery->where('machine_id', $schedule->targetable_id);
        }

        // Check if ANY log exists within reasonable window or just "today"
        // Using "Today" broadly for now to be forgiving, or strictly window?
        // Let's stick to the controller's original logic: Window Check.
        $hasLog = $logQuery->whereBetween('inspected_at', [$startDateTime, $endDateTime])
                           ->exists();

        if ($hasLog) {
            $status = 'completed';
        } else {
            // If we are strictly PAST the end time
            if (now()->gt($endDateTime)) {
                $status = 'missed';
            }
        }

        return [
            'schedule' => $schedule,
            'status' => $status,
            'window_start' => $startDateTime,
            'window_end' => $endDateTime,
            'formatted_window' => $startDateTime->format('H:i') . ' - ' . $endDateTime->format('H:i'),
        ];
    }

    /**
     * Get active schedules for a specific department and date.
     */
    public function getDailySchedules($departmentId, $date)
    {
        $dayOfWeek = Carbon::parse($date)->format('D'); // Mon, Tue...

        $query = InspectionSchedule::with(['targetable'])
            ->where('is_active', true)
            ->where(function($q) use ($dayOfWeek) {
                $q->where('frequency', 'daily')
                  ->orWhere(function($sub) use ($dayOfWeek) {
                      $sub->where('frequency', 'weekly')
                          ->whereJsonContains('days_of_week', $dayOfWeek);
                  });
            });

        // If departmentId is provided, filter by it; otherwise return all departments
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        return $query->get();
    }
}
