<?php

namespace App\Http\Controllers;

use App\Models\EmployeeSchedule;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Department;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EmployeeScheduleController extends Controller
{
    public function index(Department $department, Request $request)
    {
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->hasGlobalVisibility() && $user->department_id !== $department->id) {
            abort(403, 'Unauthorized access to this department roster.');
        }

        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->startOfWeek();
        $endDate = $startDate->copy()->addDays(6);

        $employees = Employee::where('department_id', $department->id)->where('is_active', true)->get();
        $shifts = Shift::where('department_id', $department->id)->orWhereNull('department_id')->get();

        $rawSchedules = EmployeeSchedule::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();
            
        $schedules = [];
        foreach ($rawSchedules as $schedule) {
            $dateStr = is_string($schedule->date) ? $schedule->date : $schedule->date->toDateString();
            $schedules[$schedule->employee_id][$dateStr] = $schedule;
        }

        return view('departments.roster', compact('department', 'employees', 'shifts', 'schedules', 'startDate', 'endDate'));
    }

    public function print(Department $department, Request $request)
    {
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->hasGlobalVisibility() && $user->department_id !== $department->id) {
            abort(403, 'Unauthorized access to this department roster.');
        }

        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->startOfWeek();
        $endDate = $startDate->copy()->addDays(6);

        $employees = Employee::where('department_id', $department->id)->where('is_active', true)->get();
        $shifts = Shift::where('department_id', $department->id)->orWhereNull('department_id')->get();

        $rawSchedules = EmployeeSchedule::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();
            
        $schedules = [];
        foreach ($rawSchedules as $schedule) {
            $dateStr = is_string($schedule->date) ? $schedule->date : $schedule->date->toDateString();
            $schedules[$schedule->employee_id][$dateStr] = $schedule;
        }

        return view('departments.roster-print', compact('department', 'employees', 'shifts', 'schedules', 'startDate', 'endDate'));
    }

    public function store(Request $request, Department $department)
    {
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->hasGlobalVisibility() && $user->department_id !== $department->id) {
            abort(403);
        }

        $data = $request->validate([
            'schedules' => 'required|array',
            'schedules.*.employee_id' => 'required|exists:employees,id',
            'schedules.*.date' => 'required|date',
            'schedules.*.shift_id' => 'nullable|exists:shifts,id',
            'schedules.*.is_day_off' => 'boolean',
        ]);
        
        // Prevent Cross-Department Schedule Hijacking
        $employeeIds = collect($data['schedules'])->pluck('employee_id')->unique();
        $validEmployeeCount = Employee::whereIn('id', $employeeIds)
            ->where('department_id', $department->id)
            ->count();
            
        if ($validEmployeeCount !== $employeeIds->count()) {
            abort(403, 'Unauthorized. One or more employees do not belong to this department.');
        }

        foreach ($data['schedules'] as $sch) {
            if (empty($sch['shift_id']) && empty($sch['is_day_off'])) {
                EmployeeSchedule::where('employee_id', $sch['employee_id'])
                    ->where('date', $sch['date'])
                    ->delete();
                continue;
            }

            EmployeeSchedule::updateOrCreate(
                [
                    'employee_id' => $sch['employee_id'],
                    'date' => $sch['date'],
                ],
                [
                    'shift_id' => $sch['shift_id'] ?? null,
                    'is_day_off' => $sch['is_day_off'] ?? false,
                ]
            );
        }

        return response()->json(['success' => true]);
    }
    public function export(Request $request, Department $department)
    {
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->hasGlobalVisibility() && $user->department_id !== $department->id) {
            abort(403);
        }

        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->startOfWeek();
        $employeeIds = $request->get('employee_ids', []);
        
        $fileName = 'Roster_' . $department->dept_name . '_' . $startDate->format('Y-m-d') . '.xlsx';
        
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\RosterExport($department->id, $startDate, $employeeIds), $fileName);
    }

    public function import(Request $request, Department $department)
    {
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->hasGlobalVisibility() && $user->department_id !== $department->id) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
            'start_date' => 'required|date',
        ]);

        $startDate = Carbon::parse($request->get('start_date'));

        try {
            \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\RosterImport($department->id, $startDate), $request->file('file'));
            return response()->json(['success' => true, 'message' => 'Import successful']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }
}
