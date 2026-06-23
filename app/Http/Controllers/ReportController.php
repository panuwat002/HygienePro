<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InspectionSession;
use App\Models\InspectionLog;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Department;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function offenders(Request $request)
    {
        $user = auth()->user();
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        // 1. Employees
        $empQuery = \App\Models\Employee::with(['department', 'inspectionLogs' => function($q) use ($month, $year) {
            $q->whereYear('inspected_at', $year)
              ->whereMonth('inspected_at', $month)
              ->where('result', 'fail');
        }]);

        if ($user->department && $user->department->visibility_type === 'isolated' && !$user->isAdmin()) {
            $empQuery->where('department_id', $user->department_id);
        }

        $offenders = $this->calculateOffenders($empQuery->get(), 'employee');

        // 2. Machines
        $machQuery = \App\Models\Machine::with(['location', 'inspectionLogs' => function($q) use ($month, $year) {
            $q->whereYear('inspected_at', $year)
              ->whereMonth('inspected_at', $month)
              ->where('result', 'fail');
        }]);
        
        $machineOffenders = $this->calculateOffenders($machQuery->get(), 'machine');

        // 3. Areas (Locations)
        $areaQuery = \App\Models\Location::with(['inspectionLogs' => function($q) use ($month, $year) {
            $q->whereYear('inspected_at', $year)
              ->whereMonth('inspected_at', $month)
              ->where('result', 'fail');
        }]);

        $areaOffenders = $this->calculateOffenders($areaQuery->get(), 'area');

        return view('reports.offenders', compact('offenders', 'machineOffenders', 'areaOffenders', 'month', 'year'));
    }

    private function calculateOffenders($items, $type)
    {
        return $items->map(function($item) use ($type) {
            $failCount = $item->inspectionLogs->count();
            return (object) [
                'info' => $item,
                'type' => $type,
                'fail_count' => $failCount,
                'score' => max(0, 100 - ($failCount * 5)),
                'status' => $failCount <= 1 ? 'green' : ($failCount <= 3 ? 'yellow' : 'red')
            ];
        })->filter(function($item) {
            return $item->fail_count > 0;
        })->sortByDesc('fail_count')->values();
    }

    public function index()
    {
        $user = auth()->user();
        
        // Scope variables
        $departmentId = $user->department_id ?? null;
        $isIsolated = ($user->department && $user->department->visibility_type === 'isolated' && !$user->isAdmin());

        // 1. Top 5 Common Defects (Last 30 Days)
        $commonDefects = \App\Models\InspectionLog::where('result', 'fail')
            ->where('inspected_at', '>=', now()->subDays(30))
            ->when($isIsolated, function($q) use ($departmentId) {
                // Join session to check department
                $q->whereHas('session', function($sq) use ($departmentId) {
                    $sq->where('department_id', $departmentId);
                });
            })
            ->select('checkpoint_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('checkpoint_id')
            ->with('checkpoint')
            ->orderByDesc('total')
            ->take(5)
            ->get()
            ->map(function($item) {
                return [
                    'label' => $item->checkpoint->title ?? 'Unknown',
                    'count' => $item->total
                ];
            });

        // 2. Top 5 Recurring Areas (Locations) (Last 30 Days)
        $recurringAreas = \App\Models\InspectionLog::where('result', 'fail')
            ->where('inspected_at', '>=', now()->subDays(30))
            ->whereNotNull('location_id')
            ->when($isIsolated, function($q) use ($departmentId) {
                $q->whereHas('session', function($sq) use ($departmentId) {
                    $sq->where('department_id', $departmentId);
                });
            })
            ->select('location_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('location_id')
            ->with('location')
            ->orderByDesc('total')
            ->take(5)
            ->get()
            ->map(function($item) {
                return [
                    'label' => $item->location->location_name ?? 'Unknown',
                    'count' => $item->total
                ];
            });
            
        // 3. Monthly Failure Trend (Current Month)
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();
        
        $trendData = \App\Models\InspectionLog::where('result', 'fail')
            ->whereBetween('inspected_at', [$startDate, $endDate])
            ->when($isIsolated, function($q) use ($departmentId) {
                $q->whereHas('session', function($sq) use ($departmentId) {
                    $sq->where('department_id', $departmentId);
                });
            })
            ->select(\Illuminate\Support\Facades\DB::raw('DATE(inspected_at) as date'), \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total', 'date'); // ['2023-10-01' => 5, ...]

        // Fill missing dates with 0
        $filledTrend = collect();
        $current = $startDate->copy();
        while ($current <= now()) {
            $dateStr = $current->toDateString();
            $filledTrend[$dateStr] = $trendData[$dateStr] ?? 0;
            $current->addDay();
        }

        $departments = Department::all();
        if ($isIsolated) {
            $departments = Department::where('id', $departmentId)->get();
        }

        return view('reports.index', [
            'departments' => $departments,
            'machines' => \App\Models\Machine::all(),
            'commonDefects' => $commonDefects,
            'recurringAreas' => $recurringAreas,
            'trendLabels' => $filledTrend->keys(),
            'trendValues' => $filledTrend->values(),
        ]);
    }

    public function daily(Request $request)
    {
        $user = auth()->user();
        $date = $request->input('date', Carbon::today()->toDateString());
        $departmentId = $request->input('department_id');
        $shift = $request->input('shift'); // enum: morning, afternoon, night

        $query = InspectionSession::with(['inspector', 'department', 'logs.checkpoint', 'logs.employee'])
            ->whereDate('inspection_date', $date);

        // Scope Enforcement
        if ($user->department && $user->department->visibility_type === 'isolated' && !$user->isAdmin()) {
            // Force user's department
            $departmentId = $user->department_id; 
            $query->where('department_id', $departmentId);
        } elseif ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        if ($shift) {
            $query->where('shift', $shift);
        }

        // Filter by Report Type
        $reportType = $request->input('report_type', 'all');
        if ($reportType && $reportType !== 'all') {
            $typeMap = [
                'person' => 'personnel',
                'area' => 'area',
                'machine' => 'machine'
            ];
            
            if (isset($typeMap[$reportType])) {
                $query->where('type', $typeMap[$reportType]);
            }
        }
        
        // Filter by Machine ID if type is machine
        $machineId = $request->input('machine_id');
        if ($reportType === 'machine' && $machineId) {
             $query->whereHas('logs', function($q) use ($machineId) {
                 $q->where('machine_id', $machineId);
             });
        }

        $sessions = $query->get();

        return view('reports.daily', compact('sessions', 'date', 'departmentId', 'shift', 'reportType'));
    }

    public function exportDailyPdf(Request $request)
    {
        $user = auth()->user();
        $date = $request->input('date', Carbon::today()->toDateString());
        $departmentId = $request->input('department_id');
        $shift = $request->input('shift');
        $reportType = $request->input('report_type', 'all');
        $machineId = $request->input('machine_id');
        $orientation = $request->input('orientation', 'landscape');

        // Checkpoints
        $personCheckpoints = \App\Models\Checkpoint::where('type', 'person')->orderBy('id')->get();
        $machineCheckpoints = \App\Models\Checkpoint::where('type', 'area')
            ->whereNotIn('title', ['ความสมบูรณ์ของพื้นที่', 'ความสะอาดของพื้นที่'])
            ->orderBy('id')->get();
        $areaCheckpoints = \App\Models\Checkpoint::where('type', 'area')
            ->whereIn('title', ['ความสมบูรณ์ของพื้นที่', 'ความสะอาดของพื้นที่'])
            ->orderBy('id')->get();

        // Base Query
        $query = InspectionSession::with(['inspector', 'department', 'logs.checkpoint', 'logs.employee', 'logs.machine', 'logs.location'])
            ->whereDate('inspection_date', $date);

        // Scope Enforcement
        if ($user->department && $user->department->visibility_type === 'isolated' && !$user->isAdmin()) {
            $departmentId = $user->department_id;
            $query->where('department_id', $departmentId);
        } elseif ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        if ($shift) {
            $query->where('shift', $shift);
        }

        // ... existing scope checks ...

        $sessions = $query->get();

        $employeeMatrix = [];
        $machineMatrix = [];
        $areaMatrix = [];
        
        // 1. We no longer pre-fill with ALL employees. 
        // This ensures the PDF only shows employees who have actually been inspected.

        foreach ($sessions as $session) {
            foreach ($session->logs as $log) {
                // Filter Logic:
                
                // 1. Employee Logs
                if (($reportType === 'all' || $reportType === 'person') && $log->employee_id) {
                    $empId = $log->employee_id;
                    if (!isset($employeeMatrix[$empId])) {
                         // Only add if we didn't pre-fill (should be rare if active)
                             // Load employee relation if specific log found it (it should be eager loaded)
                            $emp = $log->employee; 
                            if (!$emp) continue; 

                        $employeeMatrix[$empId] = [
                            'info' => $emp,
                            'session' => $session,
                            'results' => []
                        ];
                    } else {
                        $employeeMatrix[$empId]['session'] = $session;
                    }
                    $employeeMatrix[$empId]['results'][$log->checkpoint_id] = $log;
                }
                
                // 2. Machine Logs
                elseif (($reportType === 'all' || $reportType === 'machine') && $log->machine_id) {
                    if ($machineId && $log->machine_id != $machineId) continue;

                    $mId = $log->machine_id;
                    if (!isset($machineMatrix[$mId])) {
                        // Load machine if needed
                        $machine = $log->machine;
                        if (!$machine) continue;

                        $machineMatrix[$mId] = [
                            'info' => $machine,
                            'session' => $session,
                            'results' => []
                        ];
                    }
                    $machineMatrix[$mId]['results'][$log->checkpoint_id] = $log;
                }
                
                // 3. Area Logs (Location only, no machine, no employee)
                elseif (($reportType === 'all' || $reportType === 'area') && $log->location_id && !$log->machine_id && !$log->employee_id) {
                    $lId = $log->location_id;
                    if (!isset($areaMatrix[$lId])) {
                         $loc = $log->location;
                         if (!$loc) continue;

                         $areaMatrix[$lId] = [
                            'info' => $loc,
                            'session' => $session,
                            'results' => []
                         ];
                    }
                    $areaMatrix[$lId]['results'][$log->checkpoint_id] = $log;
                }
            }
        }
        
        // Sort matrices by info name for cleaner report presentation
        uasort($employeeMatrix, function($a, $b) {
            return strcmp($a['info']->fullname, $b['info']->fullname); // Sort alphabetically by fullname
        });

        // Collect Signatures (Unique Managers and Supervisors involved)
        // We take the verified_by and approved_by from the SESSIONS, not just logs.
        $verifierIds = $sessions->pluck('verified_by')->unique()->filter();
        $approverIds = $sessions->pluck('approved_by')->unique()->filter();

        $verifiers = \App\Models\User::whereIn('id', $verifierIds)->get();
        $approvers = \App\Models\User::whereIn('id', $approverIds)->get();

        // Chunk data for pagination (25 items per page)
        $perPage = 25;
        $employeeChunks = array_chunk($employeeMatrix, $perPage, true);
        $machineChunks = array_chunk($machineMatrix, $perPage, true);
        $areaChunks = array_chunk($areaMatrix, $perPage, true);

        $pdf = Pdf::loadView('reports.pdf.daily', compact(
            'sessions', 
            'employeeChunks', 
            'machineChunks', 
            'areaChunks',
            'personCheckpoints', 
            'machineCheckpoints',
            'areaCheckpoints', 
            'date', 
            'shift',
            'reportType',
            'verifiers',
            'approvers'
        ));
        
        $pdf->setPaper('a4', $orientation);
        
        return $pdf->stream("daily-report-{$date}.pdf");
    }
    public function exportFmQa22(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $departmentId = $request->input('department_id');
        
        // 1. Fetch ALL Active Machines with Location
        $machines = \App\Models\Machine::where('is_active', true)
            ->with(['location'])
            ->get();
            
        // 2. Fetch Logs for these machines on the date (filtered by department if needed)
        $logsQuery = \App\Models\InspectionLog::whereDate('inspected_at', $date)
            ->whereNotNull('machine_id')
            ->whereIn('checkpoint_id', [1, 2]); // Completeness (1) & Cleanliness (2)

        if ($departmentId) {
            $logsQuery->whereHas('session', function($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }
        
        $logs = $logsQuery->get()->groupBy('machine_id');

        // 3. Group by Location
        $locationGroups = [];

        foreach ($machines as $machine) {
            if (!$machine->location) continue; // Should not happen for active machines usually
            
            $locId = $machine->location_id;
            
            if (!isset($locationGroups[$locId])) {
                $locationGroups[$locId] = [
                    'info' => $machine->location,
                    'machines' => []
                ];
            }

            // Attach logs if exist
            $machineLogs = $logs->get($machine->id, collect());
            
            $results = [];
            foreach ($machineLogs as $log) {
                $results[$log->checkpoint_id] = [
                    'result' => $log->result,
                    'note' => $log->note
                ];
            }

            $locationGroups[$locId]['machines'][$machine->id] = [
                'info' => $machine,
                'results' => $results
            ];
        }
        
        // Sort locations by name for better readability
        // Note: locationGroups keys are IDs, we might want to sort by info->location_name
        uasort($locationGroups, function($a, $b) {
            return strcmp($a['info']->location_name, $b['info']->location_name);
        });

        // Chunk to ensure 1 Location per page (or group of locations if they fit)
        // For FM-QA-22, it seems to be 1 Location per sheet usually.
        $locationChunks = array_chunk($locationGroups, 1, true); 

        $pdf = Pdf::loadView('reports.pdf.fm_qa_22', compact('locationChunks', 'date'));
        $pdf->setPaper('a4', 'landscape');
        
        return $pdf->stream("fm-qa-22-{$date}.pdf");
    }
}
