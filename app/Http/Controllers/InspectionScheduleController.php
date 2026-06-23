<?php

namespace App\Http\Controllers;

use App\Models\InspectionSchedule;
use App\Models\Department;
use App\Models\Location;
use App\Models\Machine;
use Illuminate\Http\Request;

class InspectionScheduleController extends Controller
{
    /**
     * Display compliance dashboard.
     */
    public function compliance(Request $request)
    {
        $user = auth()->user();
        $date = $request->input('date', now()->format('Y-m-d'));
        $dayOfWeek = \Carbon\Carbon::parse($date)->format('D'); // Mon, Tue...
        
        // 1. Get Active Schedules relevant for this date
        $scheduleQuery = InspectionSchedule::with(['department', 'targetable'])
            ->where('is_active', true)
            ->where(function($q) use ($dayOfWeek) {
                $q->where('frequency', 'daily')
                  ->orWhere(function($sub) use ($dayOfWeek) {
                      $sub->where('frequency', 'weekly')
                          ->whereJsonContains('days_of_week', $dayOfWeek);
                  });
            });

        if (!$user->isAdmin()) {
            if ($user->department_id) {
                $scheduleQuery->where('department_id', $user->department_id);
            }
        }

        $schedules = $scheduleQuery->get();

        // 2. Check Inspection Logs for each schedule
        $complianceData = $schedules->map(function ($schedule) use ($date) {
            $status = 'pending'; // pending, completed, missed
            
            // Define time window for this specific date
            $startDateTime = \Carbon\Carbon::parse("$date {$schedule->start_time}");
            $endDateTime = \Carbon\Carbon::parse("$date {$schedule->end_time}");
            
            if ($endDateTime->lt($startDateTime)) {
                // Handle overnight shift if needed, for simplicity assume same day or next day end
                // If end < start, it probably means ends next day. 
                $endDateTime->addDay();
            }

            // Find logs
            // We need to look for logs that match the target (location or machine)
            // AND were created within the window (or inspected_at)
            
            $logQuery = \App\Models\InspectionLog::whereDate('inspected_at', $date); // Broad filter first
            
            if ($schedule->targetable_type === \App\Models\Location::class) {
                $logQuery->where('location_id', $schedule->targetable_id);
            } elseif ($schedule->targetable_type === \App\Models\Machine::class) {
                $logQuery->where('machine_id', $schedule->targetable_id);
            }

            // Refine by time window? 
            // The inspection time should be roughly within the window (or we accept any time that day?)
            // Strict compliance: Must be within window.
            // Soft compliance: Anytime that day.
            // Let's go with Strict window check for now.
            
            $hasLog = $logQuery->whereBetween('inspected_at', [$startDateTime, $endDateTime])
                               ->exists();

            if ($hasLog) {
                $status = 'completed';
            } else {
                // If now is past end_time, it is missed
                if (now()->gt($endDateTime)) {
                    $status = 'missed';
                }
            }

            return [
                'schedule' => $schedule,
                'status' => $status,
                'window' => $startDateTime->format('H:i') . ' - ' . $endDateTime->format('H:i'),
            ];
        });

        // 3. Aggregate Stats
        $stats = [
            'total' => $complianceData->count(),
            'completed' => $complianceData->where('status', 'completed')->count(),
            'missed' => $complianceData->where('status', 'missed')->count(),
            'pending' => $complianceData->where('status', 'pending')->count(),
        ];

        return view('admin.schedules.compliance', compact('complianceData', 'stats', 'date'));
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Manager can see only their department, Admin sees all
        $user = auth()->user();
        
        $query = InspectionSchedule::with(['department', 'targetable']);

        if (!$user->isAdmin()) {
            if ($user->department_id) {
                $query->where('department_id', $user->department_id);
            }
        }

        $schedules = $query->orderBy('is_active', 'desc')
                           ->orderBy('frequency')
                           ->get();

        return view('admin.schedules.index', compact('schedules'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $departments = Department::all();
        // Just load all for now, maybe use AJAX for dependency dropdown later
        $locations = Location::all(); 
        $machines = Machine::all();

        return view('admin.schedules.create', compact('departments', 'locations', 'machines'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'target_type' => 'required|in:location,machine',
            'target_id' => 'required',
            'frequency' => 'required|in:daily,weekly,monthly',
            'start_time' => 'required',
            'end_time' => 'required',
            'days_of_week' => 'nullable|array',
        ]);

        $targetClass = $request->target_type === 'machine' ? Machine::class : Location::class;

        InspectionSchedule::create([
            'title' => $request->title,
            'department_id' => $request->department_id,
            'targetable_type' => $targetClass,
            'targetable_id' => $request->target_id,
            'frequency' => $request->frequency,
            'days_of_week' => $request->days_of_week,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'is_active' => true,
        ]);

        return redirect()->route('schedules.index')->with('success', 'Schedule created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(InspectionSchedule $schedule)
    {
        $departments = Department::all();
        $locations = Location::all();
        $machines = Machine::all();

        return view('admin.schedules.edit', compact('schedule', 'departments', 'locations', 'machines'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InspectionSchedule $schedule)
    {
         $request->validate([
            'title' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'target_type' => 'required|in:location,machine',
            'target_id' => 'required',
            'frequency' => 'required|in:daily,weekly,monthly',
            'start_time' => 'required',
            'end_time' => 'required',
            'days_of_week' => 'nullable|array',
        ]);

        $targetClass = $request->target_type === 'machine' ? Machine::class : Location::class;

        $schedule->update([
            'title' => $request->title,
            'department_id' => $request->department_id,
            'targetable_type' => $targetClass,
            'targetable_id' => $request->target_id,
            'frequency' => $request->frequency,
            'days_of_week' => $request->days_of_week,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('schedules.index')->with('success', 'Schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InspectionSchedule $schedule)
    {
        $schedule->delete();
        return back()->with('success', 'Schedule deleted.');
    }
}
