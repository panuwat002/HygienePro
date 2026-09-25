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

        if ($user->isRestrictedToOwnDepartment()) {
            $empQuery->where('department_id', $user->scopedDepartmentId());
        }

        $offenders = $this->calculateOffenders($empQuery->get(), 'employee');

        // 2. Machines
        $machQuery = \App\Models\Machine::with(['location', 'inspectionLogs' => function($q) use ($month, $year, $user) {
            $q->whereYear('inspected_at', $year)
              ->whereMonth('inspected_at', $month)
              ->where('result', 'fail');
            
            if ($user->isRestrictedToOwnDepartment()) {
                $q->whereHas('session', function($sq) use ($user) {
                    $sq->where('department_id', $user->scopedDepartmentId());
                });
            }
        }]);
        
        $machineOffenders = $this->calculateOffenders($machQuery->get(), 'machine');

        // 3. Areas (Locations)
        $areaQuery = \App\Models\Location::with(['inspectionLogs' => function($q) use ($month, $year, $user) {
            $q->whereYear('inspected_at', $year)
              ->whereMonth('inspected_at', $month)
              ->where('result', 'fail');
            
            if ($user->isRestrictedToOwnDepartment()) {
                $q->whereHas('session', function($sq) use ($user) {
                    $sq->where('department_id', $user->scopedDepartmentId());
                });
            }
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
        $isIsolated = $user->isRestrictedToOwnDepartment();
        $departmentId = $isIsolated ? $user->scopedDepartmentId() : ($user->scopedDepartmentId() ?? null);

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
            ->select('location_id', 'checkpoint_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('location_id', 'checkpoint_id')
            ->with(['location', 'checkpoint'])
            ->orderByDesc('total')
            ->take(5)
            ->get()
            ->map(function($item) {
                $locName = $item->location->location_name ?? 'Unknown';
                $cpName = $item->checkpoint->title ?? 'Unknown';
                // Truncate checkpoint name if too long
                if (mb_strlen($cpName) > 20) {
                    $cpName = mb_substr($cpName, 0, 20) . '...';
                }
                return [
                    'label' => $locName . ' (' . $cpName . ')',
                    'count' => $item->total
                ];
            });
            
        // Top 5 Recurring Personnel (Employees) (Last 30 Days)
        $recurringPersonnel = \App\Models\InspectionLog::where('result', 'fail')
            ->where('inspected_at', '>=', now()->subDays(30))
            ->whereNotNull('employee_id')
            ->when($isIsolated, function($q) use ($departmentId) {
                $q->whereHas('session', function($sq) use ($departmentId) {
                    $sq->where('department_id', $departmentId);
                });
            })
            ->select('employee_id', 'checkpoint_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('employee_id', 'checkpoint_id')
            ->with(['employee', 'checkpoint'])
            ->orderByDesc('total')
            ->take(5)
            ->get()
            ->map(function($item) {
                $name = $item->employee ? ($item->employee->fullname ?? ($item->employee->fname . ' ' . $item->employee->lname)) : 'Unknown';
                $cpName = $item->checkpoint->title ?? 'Unknown';
                if (mb_strlen($cpName) > 20) {
                    $cpName = mb_substr($cpName, 0, 20) . '...';
                }
                return [
                    'label' => $name . ' (' . $cpName . ')',
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
            'recurringPersonnel' => $recurringPersonnel,
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
        if ($user->isRestrictedToOwnDepartment()) {
            // Force user's department
            $departmentId = $user->scopedDepartmentId();
        }

        $this->scopeSessionsToDepartment($query, $departmentId);

        if ($shift) {
            $query->where('shift', $shift);
        }

        $reportType = $request->input('report_type', 'all');
        $this->scopeSessionsToReportType($query, $reportType);

        // Filter by Machine ID if the merged machine/area bucket is picked.
        $machineId = $request->input('machine_id');
        if (in_array($reportType, ['machine', 'area'], true) && $machineId) {
             $query->whereHas('logs', function($q) use ($machineId) {
                 $q->where('machine_id', $machineId);
             });
        }

        $sessions = $query->get();

        // A round reached only because its staff have since moved into the
        // department being asked for. Saying so beats letting someone wonder
        // why Production is on a page they opened for ห้องแคะ.
        $hasBorrowedSessions = $departmentId !== null && $departmentId !== ''
            && $sessions->contains(fn ($session) => (int) $session->department_id !== (int) $departmentId);

        return view('reports.daily', compact('sessions', 'date', 'departmentId', 'shift', 'reportType', 'hasBorrowedSessions'));
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
            
        $areaMachineCheckpoints = \App\Models\Checkpoint::with('category')->where('type', 'area')->orderBy('id')->get()
            ->sortBy(function ($checkpoint) {
                $catName = $checkpoint->category ? $checkpoint->category->name : '';
                $title = $checkpoint->title;
                
                if (str_contains($title, 'ความสะอาด') && str_contains($catName, 'พื้นที่')) return 1;
                if (str_contains($title, 'ความสะอาด') && str_contains($catName, 'เครื่องจักร')) return 2;
                if (str_contains($title, 'ความสมบูรณ์') && str_contains($catName, 'เครื่องจักร')) return 3;
                
                return $checkpoint->sort_order > 0 ? $checkpoint->sort_order + 10 : 99;
            })->values();

        // Base Query
        $query = InspectionSession::with(['inspector', 'department', 'logs.checkpoint', 'logs.employee', 'logs.machine', 'logs.location', 'logs.correctiveAction'])
            ->whereDate('inspection_date', $date);

        // Scope Enforcement
        if ($user->isRestrictedToOwnDepartment()) {
            $departmentId = $user->scopedDepartmentId();
        }

        $this->scopeSessionsToDepartment($query, $departmentId);
        $this->scopeSessionsToReportType($query, $reportType);

        if ($shift) {
            $query->where('shift', $shift);
        }

        // Filter by specific session IDs if provided
        $sessionIds = $request->input('session_ids');
        if ($sessionIds) {
            $ids = is_array($sessionIds) ? $sessionIds : explode(',', $sessionIds);
            $query->whereIn('id', $ids);
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
                    // A round reached through its staff brings all its rows with
                    // it, so the ones who stayed behind have to be dropped here -
                    // otherwise asking for ห้องแคะ prints Production's 81 people.
                    if (! $this->personRowBelongsToDepartment(
                        $departmentId,
                        $session->department_id,
                        $log->employee?->department_id
                    )) {
                        continue;
                    }

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
                
                // 2. Machine Logs — 'machine' is the merged "เครื่องจักร / พื้นที่"
                // bucket after commit 40bafb3, so it covers both machine-attached
                // logs (this branch) and location-only logs (next branch). 'area'
                // is kept as a legacy alias for old saved URLs.
                elseif (in_array($reportType, ['all', 'machine', 'area'], true) && $log->machine_id) {
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
                // Same bucket as machine after the dropdown merge — see comment above.
                elseif (in_array($reportType, ['all', 'area', 'machine'], true) && $log->location_id && !$log->machine_id && !$log->employee_id) {
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

        // Cross-session area log fallback.
        //
        // A daily report scoped by session_ids (or by a machine-only session)
        // used to leave the Location's own area checkpoints as "-" if that
        // specific session never carried a loc: target — even when a colleague
        // had inspected the same room's floors/walls/ceiling in a DIFFERENT
        // session on the same date. Area status is a property of the room,
        // not of who happened to open it, so we widen the query for area logs
        // only: still constrained to the same date + department + shift + type
        // bucket, but not narrowed by session_ids. Any area log already picked
        // up above is preserved (isset guard), so per-session results still
        // win over cross-session ones.
        if (in_array($reportType, ['all', 'area', 'machine'], true)) {
            $areaFallbackQuery = \App\Models\InspectionLog::with(['checkpoint', 'location', 'correctiveAction'])
                ->whereHas('session', function ($q) use ($date, $departmentId, $shift, $reportType) {
                    $q->whereDate('inspection_date', $date);
                    if ($departmentId !== null && $departmentId !== '') {
                        $q->where('department_id', $departmentId);
                    }
                    if ($shift) {
                        $q->where('shift', $shift);
                    }
                    if ($reportType !== 'all') {
                        // Merged machine + area session bucket (matches the outer $query above).
                        $q->whereIn('type', ['machine', 'area']);
                    }
                })
                ->whereNotNull('location_id')
                ->whereNull('machine_id')
                ->whereNull('employee_id');

            foreach ($areaFallbackQuery->get() as $log) {
                $lId = $log->location_id;
                if (!isset($areaMatrix[$lId])) {
                    $loc = $log->location;
                    if (!$loc) continue;
                    $areaMatrix[$lId] = [
                        'info' => $loc,
                        'session' => $log->session,
                        'results' => []
                    ];
                }
                // Do not clobber a per-session result already captured above.
                if (!isset($areaMatrix[$lId]['results'][$log->checkpoint_id])) {
                    $areaMatrix[$lId]['results'][$log->checkpoint_id] = $log;
                }
            }
        }

        // Filter out items that only have no_production or absent (no action required)
        $filterNoAction = function($matrix) {
            return array_filter($matrix, function($item) {
                foreach ($item['results'] as $log) {
                    if (!in_array($log->result, ['no_production', 'absent'])) {
                        return true;
                    }
                }
                return false;
            });
        };

        $employeeMatrix = $filterNoAction($employeeMatrix);
        $machineMatrix = $filterNoAction($machineMatrix);
        $areaMatrix = $filterNoAction($areaMatrix);
        
        // Sort matrices by info name for cleaner report presentation
        uasort($employeeMatrix, function($a, $b) {
            return strcmp($a['info']->fullname ?? $a['info']->fname ?? '', $b['info']->fullname ?? $b['info']->fname ?? ''); // Sort alphabetically by fullname
        });

        // Resolve individual Employee Schedule (Roster) shift for each employee for $date
        $empIds = array_keys($employeeMatrix);
        $schedules = \App\Models\EmployeeSchedule::with('shift')
            ->whereIn('employee_id', $empIds)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('employee_id');

        foreach ($employeeMatrix as $empId => &$data) {
            $sch = $schedules->get($empId);
            if ($sch && $sch->shift) {
                $data['roster_shift'] = $sch->shift->shift_name;
            } elseif (!empty($data['info']->shift)) {
                $data['roster_shift'] = $data['info']->shift->shift_name;
            } else {
                $data['roster_shift'] = $data['session'] ? $data['session']->shift_label : '-';
            }
        }
        unset($data);

        // Collect Signatures (Unique Managers and Supervisors involved)
        $verifierIds = $sessions->flatMap(fn($s) => $s->logs->pluck('verifier_id'))
            ->concat($sessions->pluck('verified_by'))
            ->unique()
            ->filter();
        // approved_by, not verifier_id: approval has its own column now, and
        // reading the approver out of the verifier column is what put the
        // manager's name in the QA Supervisor signature box.
        $approverIds = $sessions->pluck('approved_by')
            ->concat($sessions->flatMap(fn($s) => $s->logs)->pluck('approved_by'))
            ->unique()
            ->filter();

        $verifiers = \App\Models\User::whereIn('id', $verifierIds)->get();
        $approvers = \App\Models\User::whereIn('id', $approverIds)->get();

        // Exact date & time calculation for all 3 roles:
        $recordedAt = $sessions->pluck('created_at')->filter()->max() 
            ?? $sessions->flatMap(fn($s) => $s->logs)->max('inspected_at');

        $verifiedAt = $sessions->pluck('verified_at')->filter()->max() 
            ?? $sessions->flatMap(fn($s) => $s->logs)->whereNotNull('verified_at')->max('verified_at');

        $approvedAt = $sessions->pluck('approved_at')->filter()->max() 
            ?? $sessions->flatMap(fn($s) => $s->logs)->where('verification_status', 'approved')->max('verified_at');

        // Combine Area and Machine Matrices by Location for unified table
        $areaMachineCombined = [];
        
        foreach ($areaMatrix as $lId => $data) {
            $loc = $data['info'];
            $areaMachineCombined[$lId] = [
                'location' => $loc,
                'location_data' => $data,
                'machines' => []
            ];
        }
        
        foreach ($machineMatrix as $mId => $data) {
            $machine = $data['info'];
            $lId = $machine->location_id;
            
            if (!$lId) {
                $lId = 'no_loc_' . $mId;
                $areaMachineCombined[$lId] = [
                    'location' => null,
                    'location_data' => null,
                    'machines' => [$data]
                ];
                continue;
            }
            
            if (!isset($areaMachineCombined[$lId])) {
                $loc = $machine->location;
                $areaMachineCombined[$lId] = [
                    'location' => $loc,
                    'location_data' => null,
                    'machines' => []
                ];
            }
            $areaMachineCombined[$lId]['machines'][] = $data;
        }

        // Flatten for the PDF rows
        $flattenedAreaMachine = [];
        foreach ($areaMachineCombined as $lId => $group) {
            if ($group['location_data']) {
                $flattenedAreaMachine[] = [
                    'type' => 'area',
                    'info' => $group['location_data']['info'],
                    'session' => $group['location_data']['session'],
                    'results' => $group['location_data']['results'],
                ];
            } elseif ($group['location']) {
                $flattenedAreaMachine[] = [
                    'type' => 'area_header',
                    'info' => $group['location'],
                    'session' => null,
                    'results' => [],
                ];
            }
            
            foreach ($group['machines'] as $mData) {
                $flattenedAreaMachine[] = [
                    'type' => 'machine',
                    'info' => $mData['info'],
                    'session' => $mData['session'],
                    'results' => $mData['results'],
                ];
            }
        }

        // Which checks each person in this report is actually required to pass.
        //
        // Production walks through an air shower on the way in and ห้องแคะ does
        // not, so the two carry different checkpoint sets. The form used to
        // print the master list either way, which gave the room a column it can
        // never satisfy and a "-" in #eee - invisible on paper - where an
        // auditor needs to see either a result or a reason.
        $assignedCheckpointsByEmployee = collect();
        if (! empty($employeeMatrix)) {
            $assignedCheckpointsByEmployee = \Illuminate\Support\Facades\DB::table('employee_checkpoint')
                ->whereIn('employee_id', array_keys($employeeMatrix))
                ->get()
                ->groupBy('employee_id')
                ->map(fn ($rows) => $rows->pluck('checkpoint_id')->all());
        }

        $personCheckpoints = $this->personColumnsForReport(
            $personCheckpoints,
            $employeeMatrix,
            $assignedCheckpointsByEmployee
        );

        // Measured across the whole report, not per page, so the columns line up
        // when the pages are laid side by side.
        $personColumnWidths = $this->nameAndDepartmentWidths($employeeMatrix);

        // Chunk data for pagination (prevent overflow into signatures by reducing perPage)
        $perPage = 18;
        $employeeChunks = array_chunk($employeeMatrix, $perPage, true);
        $areaMachineChunks = array_chunk($flattenedAreaMachine, $perPage, true);

        $pdf = Pdf::loadView('reports.pdf.daily', compact(
            'sessions', 
            'employeeChunks', 
            'areaMachineChunks',
            'personCheckpoints',
            'personColumnWidths',
            'assignedCheckpointsByEmployee',
            'areaMachineCheckpoints',
            'date', 
            'shift',
            'reportType',
            'verifiers',
            'approvers',
            'recordedAt',
            'verifiedAt',
            'approvedAt'
        ));
        
        $pdf->setPaper('a4', $orientation);

        return $pdf->stream("daily-report-{$date}.pdf");
    }

    /**
     * The checkpoint columns a personnel form should actually carry.
     *
     * A column belongs on the form when somebody in the report is required to
     * pass it, or when a result was recorded against it - dropping the second
     * case would hide an inspection that really happened.
     *
     * Run the form for ห้องแคะ and the air shower column is gone, because
     * nobody in that room is asked to walk through one; run it for the line and
     * it is there. A report that still mixes both keeps the column, and the
     * cell says "ไม่เกี่ยวข้อง" for the people it does not apply to.
     *
     * Older rows have no checkpoint assignments at all. Narrowing to an empty
     * set there would print a form with no columns, so it falls back whole.
     *
     * @param  \Illuminate\Support\Collection  $allPersonCheckpoints
     * @param  array  $employeeMatrix  keyed by employee id, each with a 'results' map
     * @param  \Illuminate\Support\Collection  $assignedByEmployee  employee id => checkpoint ids
     * @return \Illuminate\Support\Collection
     */
    /**
     * How to split the name and department columns on the personnel form.
     *
     * They were fixed at 22% and 6.5%, set when every department was called PD.
     * "Production (ห้องแคะ)" wrapped onto two lines in its 6.5% while the name
     * column beside it held names half that long.
     *
     * Each column asks for what its longest entry needs and no more; whatever
     * neither uses goes to the checkpoint columns, whose headers wrap onto two
     * lines when they are squeezed. An earlier version split a fixed budget
     * between the two, which kept the department on one line but left a third
     * of the name column empty on a report full of short names.
     *
     * Thai combining marks - the vowels and tones that sit above and below -
     * are not counted: they take no horizontal space, and counting them made
     * every Thai name read as a quarter longer than it prints.
     *
     * @return array{name: float, department: float} percentages of table width
     */
    private function nameAndDepartmentWidths(array $employeeMatrix): array
    {
        // Roughly one character of the 8px table font, as a share of the page.
        $perCharacter = 0.75;
        $padding = 1.5;

        $minName = 10.0;
        $minDepartment = 7.0;
        // A pathological entry must not crush the checkpoints, which are the
        // part somebody actually has to tick.
        $maxTogether = 34.0;

        $longest = function (callable $pick) use ($employeeMatrix): int {
            $max = 0;
            foreach ($employeeMatrix as $row) {
                $text = trim((string) $pick($row));
                // U+0E31, U+0E34-U+0E3A, U+0E47-U+0E4E render above or below the
                // preceding consonant rather than beside it.
                $spacing = preg_replace('/[\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}]/u', '', $text);
                $max = max($max, mb_strlen($spacing));
            }

            return max($max, 1);
        };

        $needed = fn (int $characters) => $characters * $perCharacter + $padding;

        $name = max($minName, $needed($longest(
            fn ($row) => $row['info']->fullname ?? $row['info']->fname ?? ''
        )));

        $department = max($minDepartment, $needed($longest(
            fn ($row) => $row['session']->department->dept_name
                ?? $row['info']->department->dept_name
                ?? ''
        )));

        // Scale both back together if they overrun, so neither is singled out.
        if ($name + $department > $maxTogether) {
            $scale = $maxTogether / ($name + $department);
            $name *= $scale;
            $department *= $scale;
        }

        return [
            'name' => round($name, 2),
            'department' => round($department, 2),
        ];
    }

    /**
     * Narrow a session query to a department, following staff who have moved.
     *
     * A round walked before a work area was split out carries the parent's
     * department, so filtering on that column alone hid the new department's
     * whole history. Matching the session's department OR the current
     * department of the staff inspected in it brings it back.
     *
     * Additive on purpose: asking for the parent still returns everything it
     * returned before, so a form printed and signed last month regenerates the
     * same way. Moving an employee must not quietly rewrite a signed document.
     */
    /**
     * Narrow a session query to the ประเภท the report was asked for.
     *
     * The dropdown was consolidated to three options — ทั้งหมด / พนักงาน /
     * "เครื่องจักร / พื้นที่" — because the app only creates sessions of type
     * 'personnel' or 'machine' now; legacy 'area' sessions still exist in old
     * data and belong in the merged bucket, as do old bookmarks that pass
     * ?report_type=area.
     *
     * Shared by the list page and the PDF export because only the list page
     * ever had it. Asking for เครื่องจักร/พื้นที่ listed one round and then
     * exported four, and the export takes the form's ผู้บันทึก signature from
     * $sessions->first() — so the area form came out signed by whoever walked
     * a personnel round that morning.
     */
    private function scopeSessionsToReportType($query, ?string $reportType): void
    {
        if (! $reportType || $reportType === 'all') {
            return;
        }

        $typeMap = [
            'person'  => ['personnel'],
            'machine' => ['machine', 'area'],
            'area'    => ['machine', 'area'], // legacy alias for old saved URLs
        ];

        if (isset($typeMap[$reportType])) {
            $query->whereIn('type', $typeMap[$reportType]);
        }
    }

    private function scopeSessionsToDepartment($query, $departmentId): void
    {
        if ($departmentId === null || $departmentId === '') {
            return;
        }

        $query->where(function ($q) use ($departmentId) {
            $q->where('department_id', $departmentId)
              ->orWhereHas('logs.employee', fn ($e) => $e->where('department_id', $departmentId));
        });
    }

    /**
     * Whether one personnel row belongs on a given department's form.
     *
     * Either that department walked the round, or the person is theirs today.
     * Without the second half, asking for ห้องแคะ would pull in its parent's
     * round and then print all 81 of the parent's people with it.
     */
    private function personRowBelongsToDepartment($departmentId, $sessionDepartmentId, $employeeDepartmentId): bool
    {
        if ($departmentId === null || $departmentId === '') {
            return true;
        }

        return (int) $sessionDepartmentId === (int) $departmentId
            || (int) $employeeDepartmentId === (int) $departmentId;
    }

    private function personColumnsForReport($allPersonCheckpoints, array $employeeMatrix, $assignedByEmployee)
    {
        $required = collect($assignedByEmployee)->flatten();

        $recorded = collect($employeeMatrix)
            ->flatMap(fn ($row) => array_keys($row['results'] ?? []));

        $relevant = $required->concat($recorded)->unique();

        if ($relevant->isEmpty()) {
            return $allPersonCheckpoints;
        }

        return $allPersonCheckpoints
            ->filter(fn ($checkpoint) => $relevant->contains($checkpoint->id))
            ->values();
    }
    public function exportFmQa22(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $departmentId = $request->input('department_id');
        
        $user = auth()->user();
        if ($user->isRestrictedToOwnDepartment()) {
            $departmentId = $user->scopedDepartmentId();
        }
        
        // 1. Fetch ALL Active Machines with Location
        $machines = \App\Models\Machine::where('is_active', true)
            ->with(['location'])
            ->get();
            
        // 2. Fetch Logs for these machines on the date (filtered by department if needed)
        $logsQuery = \App\Models\InspectionLog::whereDate('inspected_at', $date)
            ->whereNotNull('machine_id')
            ->whereIn('checkpoint_id', [1, 2]); // Completeness (1) & Cleanliness (2)

        if ($departmentId !== null && $departmentId !== '') {
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
            $hasValidAction = false;
            $hasAnyResult = false;
            foreach ($machineLogs as $log) {
                $hasAnyResult = true;
                $results[$log->checkpoint_id] = [
                    'result' => $log->result,
                    'note' => $log->note
                ];
                if (!in_array($log->result, ['no_production', 'absent'])) {
                    $hasValidAction = true;
                }
            }

            // Skip if this machine was explicitly checked but ALL results were no_production or absent
            if ($hasAnyResult && !$hasValidAction) {
                continue;
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

    public function exportMonthlyPdf(Request $request)
    {
        $month = $request->input('month', date('Y-m'));
        $departmentId = $request->input('department_id');
        
        $user = auth()->user();
        if ($user->isRestrictedToOwnDepartment()) {
            $departmentId = $user->scopedDepartmentId();
        }
        
        $startDate = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = \App\Models\InspectionLog::whereBetween('inspected_at', [$startDate, $endDate]);
        
        if ($departmentId !== null && $departmentId !== '') {
            $query->whereHas('session', function($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $totalInspections = (clone $query)->count();
        $totalFails = (clone $query)->where('result', 'fail')->count();
        
        $rawScore = $totalInspections > 0 ? (($totalInspections - $totalFails) / $totalInspections) * 100 : 100;
        if ($rawScore == 100) {
            $healthScore = 100;
        } else {
            $healthScore = round($rawScore, 2);
            // Prevent rounding up to 100 if there is at least 1 failure
            if ($healthScore == 100 && $totalFails > 0) {
                $healthScore = 99.99;
            }
        }

        $topDefectsRaw = (clone $query)->where('result', 'fail')
            ->join('checkpoints', 'inspection_logs.checkpoint_id', '=', 'checkpoints.id')
            ->select('checkpoints.title', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('checkpoints.id', 'checkpoints.title')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
            
        $topDefects = [];
        foreach($topDefectsRaw as $defect) {
            $topDefects[$defect->title] = $defect->total;
        }

        // Read the target-type selection from the form. The value used to be
        // hard-coded to 'machine', which meant the PDF header always said
        // "เครื่องจักร" regardless of what the user picked. Now honour the
        // selection so the "พนักงาน" report actually says พนักงาน. Any legacy
        // 'area' value maps to the merged machine/area bucket.
        $targetType = $request->input('target_type', 'machine');
        if ($targetType === 'area') {
            $targetType = 'machine';
        }

        $topOffendersRaw = (clone $query)->where('inspection_logs.result', 'fail');
        if ($targetType === 'person') {
            $topOffendersRaw = $topOffendersRaw->whereNotNull('inspection_logs.employee_id')
                ->join('employees', 'inspection_logs.employee_id', '=', 'employees.id')
                ->select(\Illuminate\Support\Facades\DB::raw("CONCAT(COALESCE(employees.fname, ''), ' ', COALESCE(employees.lname, '')) as target_name"), \Illuminate\Support\Facades\DB::raw('count(*) as total'))
                ->groupBy('employees.id', 'target_name');
        } else {
            $topOffendersRaw = $topOffendersRaw->whereNotNull('inspection_logs.machine_id')
                ->join('machines', 'inspection_logs.machine_id', '=', 'machines.id')
                ->select('machines.name as target_name', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
                ->groupBy('machines.id', 'machines.name');
        }

        $topOffenders = $topOffendersRaw->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'target_name')
            ->toArray();

        $data = [
            'month' => $month,
            'targetType' => $targetType,
            'healthScore' => $healthScore,
            'totalInspections' => $totalInspections,
            'totalFails' => $totalFails,
            'topDefects' => $topDefects,
            'topOffenders' => $topOffenders,
        ];

        $pdf = Pdf::loadView('reports.pdf.monthly_summary', $data);
        $pdf->setPaper('a4', 'landscape');
        return $pdf->stream("monthly-report-{$month}.pdf");
    }

    public function moneyReport(Request $request)
    {
        $user = auth()->user();
        $month = $request->input('month', date('Y-m'));
        $departmentId = $request->input('department_id');

        $startDate = Carbon::parse($month . '-01')->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = InspectionLog::with(['checkpoint.category', 'session.department', 'correctiveAction'])
            ->where('result', 'fail')
            ->whereBetween('inspected_at', [$startDate, $endDate]);

        if ($user->isRestrictedToOwnDepartment()) {
            $departmentId = $user->scopedDepartmentId();
            $query->whereHas('session', fn($q) => $q->where('department_id', $departmentId));
        } elseif ($departmentId) {
            $query->whereHas('session', fn($q) => $q->where('department_id', $departmentId));
        }

        $logs = $query->get();

        $totalLoss = 0;
        $deptLosses = [];
        $categoryLosses = [];

        foreach ($logs as $log) {
            $loss = ($log->correctiveAction && $log->correctiveAction->financial_loss > 0)
                ? (float) $log->correctiveAction->financial_loss
                : ((float) ($log->checkpoint->default_cost_impact ?? 500));

            $totalLoss += $loss;

            $deptName = $log->session->department->dept_name ?? 'Unassigned';
            $deptLosses[$deptName] = ($deptLosses[$deptName] ?? 0) + $loss;

            $catName = $log->checkpoint->category->name ?? 'ทั่วไป';
            $categoryLosses[$catName] = ($categoryLosses[$catName] ?? 0) + $loss;
        }

        arsort($deptLosses);
        arsort($categoryLosses);

        $departments = Department::all();

        return view('reports.money', compact('logs', 'month', 'departmentId', 'totalLoss', 'deptLosses', 'categoryLosses', 'departments'));
    }

    public function exportMoneyPdf(Request $request)
    {
        $month = $request->input('month', date('Y-m'));
        $departmentId = $request->input('department_id');
        
        $user = auth()->user();
        if ($user->isRestrictedToOwnDepartment()) {
            $departmentId = $user->scopedDepartmentId();
        }

        $startDate = Carbon::parse($month . '-01')->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = InspectionLog::with(['checkpoint.category', 'session.department', 'correctiveAction', 'employee', 'location', 'machine'])
            ->where('result', 'fail')
            ->whereBetween('inspected_at', [$startDate, $endDate]);

        if ($departmentId !== null && $departmentId !== '') {
            $query->whereHas('session', fn($q) => $q->where('department_id', $departmentId));
        }

        $logs = $query->get();

        $totalLoss = 0;
        foreach ($logs as $log) {
            $loss = ($log->correctiveAction && $log->correctiveAction->financial_loss > 0)
                ? (float) $log->correctiveAction->financial_loss
                : ((float) ($log->checkpoint->default_cost_impact ?? 500));
            $log->calculated_loss = $loss;
            $totalLoss += $loss;
        }

        $pdf = Pdf::loadView('reports.pdf.money_summary', compact('logs', 'month', 'totalLoss', 'departmentId'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream("money-report-{$month}.pdf");
    }
}
