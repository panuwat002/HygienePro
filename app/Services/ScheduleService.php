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
        if ($schedules->isEmpty()) {
            return collect();
        }

        // Pre-fetch all logs for the given date that match the schedules' targets
        $locationIds = [];
        $machineIds = [];
        
        foreach ($schedules as $schedule) {
            if ($schedule->targetable_type === Location::class) {
                $locationIds[] = $schedule->targetable_id;
            } elseif ($schedule->targetable_type === Machine::class) {
                $machineIds[] = $schedule->targetable_id;
            }
        }

        // Fetch logs for the specific date window and targets in one query
        $logs = collect();
        if (!empty($locationIds) || !empty($machineIds)) {
            // Include next day to account for cross-midnight schedules
            $minDate = Carbon::parse($date)->startOfDay();
            $maxDate = Carbon::parse($date)->addDay()->endOfDay();

            $logQuery = InspectionLog::whereBetween('inspected_at', [$minDate, $maxDate])
                ->select('id', 'location_id', 'machine_id', 'inspected_at');
            
            $logQuery->where(function ($q) use ($locationIds, $machineIds) {
                if (!empty($locationIds)) {
                    $q->orWhereIn('location_id', array_unique($locationIds));
                }
                if (!empty($machineIds)) {
                    $q->orWhereIn('machine_id', array_unique($machineIds));
                }
            });
            
            $logs = $logQuery->get();
        }

        return $schedules->map(function ($schedule) use ($date, $logs) {
            return $this->calculateSingleStatusWithLogs($schedule, $date, $logs);
        });
    }

    /**
     * Calculate status for a single schedule using pre-fetched logs in memory.
     */
    public function calculateSingleStatusWithLogs(InspectionSchedule $schedule, $date, $logs)
    {
        $status = 'pending'; // pending, completed, missed, in_progress (if applicable)
        
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

        $hasLog = false;
        
        foreach ($logs as $log) {
            $matchTarget = false;
            if ($schedule->targetable_type === Location::class && $log->location_id == $schedule->targetable_id) {
                $matchTarget = true;
            } elseif ($schedule->targetable_type === Machine::class && $log->machine_id == $schedule->targetable_id) {
                $matchTarget = true;
            }
            
            if ($matchTarget) {
                $inspectedAt = Carbon::parse($log->inspected_at);
                if ($inspectedAt->between($startDateTime, $endDateTime)) {
                    $hasLog = true;
                    break;
                }
            }
        }

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
