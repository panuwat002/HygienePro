<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use App\Models\Machine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image; // Laravel 11/Intervention 3

class InspectionController extends Controller
{
    protected $inspectionService;
    protected $scheduleService;

    public function __construct(\App\Services\InspectionService $inspectionService, \App\Services\ScheduleService $scheduleService)
    {
        $this->inspectionService = $inspectionService;
        $this->scheduleService = $scheduleService;
    }
    /**
     * Throttled, fail-safe trigger that auto-closes stale inspection sessions
     * when the dashboard loads. Runs at most once per configured window and
     * must never throw into the request.
     */
    public function autoCloseHeartbeat(): void
    {
        try {
            $heartbeatMinutes = (int) config('inspection.auto_close.heartbeat_minutes', 10);
            if (config('inspection.auto_close.enabled', true)
                && \Illuminate\Support\Facades\Cache::add('auto_close_heartbeat', 1, now()->addMinutes($heartbeatMinutes))) {
                $this->inspectionService->autoCloseStaleSessions();
            }
        } catch (\Throwable $e) {
            \Log::error('auto-close heartbeat failed: ' . $e->getMessage());
        }
    }

    public function home()
    {
        // Opportunistically auto-close stale sessions on dashboard load (throttled, non-blocking).
        $this->autoCloseHeartbeat();

        $today = now()->toDateString();
        $user = Auth::user();

        // --- SCOPE DEFINITION (Role Matrix Compliance) ---
        // Global scope users (QA, Admin, Executive) see all departments
        // Isolated scope users see only their own department
        $scopeDeptId = null;
        if (!$user->hasGlobalVisibility()) {
            $scopeDeptId = $user->department_id;
        }

        // Helper to apply scope
        $applyScope = function($query) use ($scopeDeptId) {
            if ($scopeDeptId) {
                // Filter logs where (it is an Employee inspection AND they belong to Dept)
                // OR (it is NOT an Employee inspection - e.g. Area/Machine - currently shown to all isolated)
                // TO-DO: strict department check for machines if needed.
                $query->where(function($q) use ($scopeDeptId) {
                    $q->whereHas('employee', function($subQ) use ($scopeDeptId) {
                        $subQ->where('department_id', $scopeDeptId);
                    })->orWhereNull('employee_id');
                });
            }
            return $query;
        };

        // DB-level grouping key for unique inspection entities
        $entityExpr = "CONCAT(session_id, '_', CASE WHEN machine_id IS NOT NULL THEN CONCAT('m', machine_id) WHEN employee_id IS NOT NULL THEN CONCAT('e', employee_id) ELSE CONCAT('l', COALESCE(location_id, 0)) END)";

        // 1. Total unique inspections today
        $todayBase = InspectionLog::whereDate('inspected_at', $today);
        $applyScope($todayBase);

        $row = (clone $todayBase)->selectRaw("COUNT(DISTINCT {$entityExpr}) as cnt")->first();
        $inspectionsToday = $row ? (int) $row->cnt : 0;

        // 2. Pending Verification (unverified logs have NULL verification_status)
        $pendingBase = InspectionLog::whereNull('verification_status')
                                    ->whereNotIn('result', ['no_production', 'absent']);
        $applyScope($pendingBase);
        $row = $pendingBase->selectRaw("COUNT(DISTINCT {$entityExpr}) as cnt")->first();
        $pendingVerificationCount = $row ? (int) $row->cnt : 0;

        // 3. Outstanding Re-cleans
        $recleanBase = InspectionLog::where('verification_status', 'reclean');
        $applyScope($recleanBase);
        $row = $recleanBase->selectRaw("COUNT(DISTINCT {$entityExpr}) as cnt")->first();
        $recleanCount = $row ? (int) $row->cnt : 0;

        // 4. Daily Pass Rate (DB-level counts)
        $totalPass = (clone $todayBase)->where('result', 'pass')->count();
        $totalFail = (clone $todayBase)->where('result', 'fail')->count();
        $passRate = ($totalPass + $totalFail) > 0 ? round(($totalPass / ($totalPass + $totalFail)) * 100) : 100;

        // 5. Recent Activity
        $recentQuery = InspectionLog::with(['employee', 'location', 'machine', 'checkpoint', 'session.inspector'])
            ->orderBy('id', 'desc')
            ->take(80); // Fetch more to filter post-grouping if needed, but we filter DB side now
        
        $recentLogs = $applyScope($recentQuery)
            ->get()
            ->unique(function($log) {
                 return $log->session_id . '-' . ($log->employee_id ?? $log->machine_id ?? $log->location_id);
            })
            ->take(5);

        // 6. CAR Statistics (Executive View)
        $startOfMonth = now()->startOfMonth();
        $carQuery = \App\Models\CorrectiveAction::with(['log.session.department'])
            ->where('created_at', '>=', $startOfMonth);
            
        $carsThisMonth = $carQuery->get();
        $totalCars = $carsThisMonth->count();
        
        // Group by Department
        $carsByDept = $carsThisMonth->groupBy(fn($c) => $c->log?->session?->department?->dept_name ?? 'Unknown')
                        ->map->count();
                        
        // SLA Status
        $onTimeCount = $carsThisMonth->filter(fn($c) => 
            in_array($c->status, ['resolved', 'closed']) && 
            ($c->resolved_at <= $c->due_date || !$c->due_date)
        )->count();
        
        // Overdue = Currently Open & Late OR Resolved Late
        $overdueCount = $carsThisMonth->filter(fn($c) => 
            ($c->due_date && $c->due_date < now() && !in_array($c->status, ['resolved', 'closed'])) ||
            ($c->due_date && in_array($c->status, ['resolved', 'closed']) && $c->resolved_at > $c->due_date)
        )->count();

        // In Progress (Not overdue)
        $inProgressCount = $totalCars - $onTimeCount - $overdueCount;
        if ($inProgressCount < 0) $inProgressCount = 0; // Guard

        // AI Tag Insights
        // Pluck tags, flatten them to a single list, remove empties, count occurrences, and get top 5
        $aiTagCounts = $carsThisMonth->pluck('ai_tags')->flatten()->filter()->countBy()->sortDesc()->take(5);

        // 7. Today's Scheduled Inspections
        $scheduleDeptId = $user->isAdmin() || $user->hasGlobalVisibility() ? null : $user->department_id;
        $scheduledTasks = collect();
        if ($scheduleDeptId || $user->isAdmin() || $user->hasGlobalVisibility()) {
            $todaysSchedules = $this->scheduleService->getDailySchedules($scheduleDeptId, $today);
            $todaysSchedules->load('department');
            $scheduledTasks = $this->scheduleService->getComplianceStatus($todaysSchedules, $today);
        }

        // 8. Active Sessions (For Admins)
        $activeSessions = collect();
        if ($user->isAdmin() || $user->hasGlobalVisibility()) {
            $activeSessions = InspectionSession::with(['inspector', 'department'])
                ->where('inspection_date', $today)
                ->where('status', 'in_progress')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // 9. Today's Random Audits (For Supervisors/Managers)
        $todayAudits = collect();
        if ($user->level >= 4 || $user->isAdmin()) { // Supervisor (level 4) and above
            $auditQuery = \App\Models\RandomAudit::with('department')
                ->where('audit_date', $today)
                ->where('status', 'pending');
            
            // Non-admin supervisors only see their own department
            if (!$user->isAdmin() && !$user->hasGlobalVisibility()) {
                $auditQuery->where('department_id', $user->department_id);
            }
            
            $todayAudits = $auditQuery->get();
        }

        return view('dashboard', compact(
            'inspectionsToday',
            'pendingVerificationCount',
            'recleanCount',
            'passRate',
            'recentLogs',
            'totalCars',
            'carsByDept',
            'onTimeCount',
            'overdueCount',
            'inProgressCount',
            'aiTagCounts',
            'scheduledTasks',
            'activeSessions',
            'todayAudits'
        ));
    }

    public function dashboard(Request $request, $type = 'personnel')
    {
        $user = Auth::user();
        
        // 1. Determine Departments
        if ($user->hasGlobalVisibility()) {
            $departments = Department::all();
        } elseif ($user->department) {
            $departments = Department::where('id', $user->department_id)->get();
        } else {
            $departments = collect();
        }

        // 2. Fetch Pending Re-cleans (Global for the type)
        $pendingReCleans = InspectionLog::with(['employee', 'location', 'machine', 'checkpoint', 'session.department', 'session.inspector', 'verifier'])
            ->where('verification_status', 'reclean')
            ->whereDoesntHave('rechecks')
            ->whereHas('session', function($q) use ($type) {
                $q->where('type', $type);
            })
            ->orderBy('verified_at', 'desc')
            ->get()
            ->groupBy(function($log) {
                if ($log->employee_id) return $log->session_id . '_e' . $log->employee_id;
                if ($log->machine_id) return $log->session_id . '_m' . $log->machine_id;
                return $log->session_id . '_l' . ($log->location_id ?? 0);
            });

        // 3. Current Session Status & Remaining Items Logic
        $currentAutoShift = $this->getAutoShift();
        // Loop Engineering Fix: Cross-Midnight Bug. Hours 0-5 belong to yesterday's shift.
        $today = now()->hour < 6 ? now()->subDay()->toDateString() : now()->toDateString();
        $currentSession = InspectionSession::where('inspector_id', Auth::id())
            ->where('inspection_date', $today)
            ->where('type', $type)
            // เงื่อนไขพิเศษ: ไม่กรองตาม $currentAutoShift เพื่อให้กะเช้าที่ยังตรวจไม่เสร็จ โชว์ขึ้นมาให้ทำต่อได้
            // ->where('shift', $currentAutoShift) 
            ->whereIn('status', ['in_progress', 'paused'])
            ->latest('id')
            ->first();

        // --- NEW: FETCH SCHEDULED TASKS ---
        $scheduledTasks = collect();
        // Admin/Global users see all departments; others see their own
        $scheduleDeptId = $user->isAdmin() || $user->hasGlobalVisibility() ? null : $user->department_id;
        if ($scheduleDeptId || $user->isAdmin() || $user->hasGlobalVisibility()) {
             $todaysSchedules = $this->scheduleService->getDailySchedules($scheduleDeptId, $today);
             // Eager load department for display
             $todaysSchedules->load('department');
             // Filter by type (Area/Machine)
             $filteredSchedules = $todaysSchedules->filter(function($s) use ($type) {
                 if ($type === 'area') return $s->targetable_type === \App\Models\Location::class;
                 if ($type === 'machine') return $s->targetable_type === \App\Models\Machine::class;
                 return false;
             });
             
             // Get Status for each
             $scheduledTasks = $this->scheduleService->getComplianceStatus($filteredSchedules, $today);
        }
        // ----------------------------------

        $remainingCount = 0;
        $totalTargets = 0;
        $progressPercent = 0;
        $remainingList = collect();

        if ($currentSession) {
            if ($type === 'personnel') {
                // Find Target Employees in the current shift
                // Loop Engineering Fix: Match target employees to the session's shift
                $shiftNames = match($currentSession->shift) {
                    'morning' => ['morning', 'กะเช้า'],
                    'afternoon' => ['afternoon', 'กะบ่าย'],
                    'night' => ['night', 'กะดึก'],
                    default => [$currentSession->shift]
                };
                
                $baseQuery = Employee::where('department_id', $currentSession->department_id)
                    ->where('is_active', true)
                    ->whereHas('shift', fn($q) => $q->whereIn('shift_name', $shiftNames));
                
                $targetEmployeeIds = $baseQuery->pluck('id')->toArray();
                $totalTargets = count($targetEmployeeIds);
                
                // Loop Engineering Fix: Count ANYONE inspected in this session (even if they swapped shifts)
                $inspectedCount = InspectionLog::where('session_id', $currentSession->id)
                    ->distinct('employee_id')
                    ->count('employee_id');

                $remainingCount = max(0, $totalTargets - $inspectedCount);
                
                // The remaining list allows inspecting ANYONE in the department who hasn't been inspected
                if($remainingCount > 0 || $totalTargets === 0) {
                     $inspectedIds = InspectionLog::where('session_id', $currentSession->id)
                        ->pluck('employee_id')->toArray();
                     $remainingList = Employee::where('department_id', $currentSession->department_id)
                        ->where('is_active', true)
                        ->whereNotIn('id', $inspectedIds)
                        ->get();
                }

            } elseif ($type === 'area') {
                // Area Mode: Only count Locations that HAVE active checkpoints
                $totalTargets = Location::whereHas('checkpoints', function($q) {
                    $q->where('type', 'area')->where('is_active', true);
                })->count();

                // Count Inspected (filtered by valid targets logic implicitly, but logs exist)
                // We should only count logs for VALID targets or assume logs imply validity?
                // Safest: Count unique location_id in logs
                $inspectedCount = InspectionLog::where('session_id', $currentSession->id)
                    ->whereNull('machine_id')
                    ->distinct('location_id')
                    ->count('location_id');
                    
                $remainingCount = max(0, $totalTargets - $inspectedCount);

            } elseif ($type === 'machine') {
                // Machine Mode: Only count Active Machines that HAVE active checkpoints
                $totalTargets = \App\Models\Machine::where('is_active', true)
                    ->whereHas('checkpoints', function($q) {
                        $q->where('is_active', true);
                    })->count();

                $inspectedCount = InspectionLog::where('session_id', $currentSession->id)
                    ->whereNotNull('machine_id')
                    ->distinct('machine_id')
                    ->count('machine_id');
                    
                $remainingCount = max(0, $totalTargets - $inspectedCount);
            }

            $progressPercent = $totalTargets > 0 ? round((($totalTargets - $remainingCount) / $totalTargets) * 100) : 0;
            
            // Loop Engineering: Removed auto-complete logic here so it doesn't force close sessions 
            // if an inspector wants to inspect outside their shift. They can use the "Finish" button.
        }
        
        // Fetch active sessions today
        // TEMPORARY: Commented out the inspector_id filter so you can see it with your own session!
        $activeOtherSessions = InspectionSession::with(['inspector', 'department'])
            ->where('inspection_date', $today)
            ->where('type', $type)
            ->where('status', 'in_progress')
            // ->where('inspector_id', '!=', Auth::id()) 
            ->get();

        return view('inspections.dashboard', compact(
            'departments', 
            'pendingReCleans', 
            'type', 
            'currentSession', 
            'currentAutoShift', // Pass to view
            'remainingCount', 
            'totalTargets', 
            'progressPercent',
            'remainingList',
            'scheduledTasks',
            'activeOtherSessions'
        ));
    }

    public function getDepartmentStats($type, $department, Request $request)
    {
        try {
            $shift = $request->query('shift') ?? $this->getAutoShift(); 
            // Loop Engineering Fix: Cross-Midnight Bug
            $today = now()->hour < 6 ? now()->subDay()->toDateString() : now()->toDateString();

            // 1. Get locations and optionally machines
            $query = Location::query()
                ->withCount(['checkpoints', 'machines'])
                ->havingRaw('checkpoints_count > 0 OR machines_count > 0');
            
            if ($type === 'machine') {
                $query->whereHas('machines', function($q) {
                    $q->where('is_active', true);
                })->with(['machines' => function($q) {
                    $q->where('is_active', true);
                }]);
            }
            // For 'area', we just need locations, no need to eager load machines
            // For 'area', we just need locations, no need to eager load machines

            if ($department !== 'all') {
                $deptModel = is_numeric($department) ? Department::find($department) : $department;
                if (!$deptModel) throw new \Exception("Department not found");
                
                $query->withCount(['employees' => function ($q) use ($deptModel) {
                    $q->where('department_id', $deptModel->id)->where('is_active', true);
                }]);

                $session = InspectionSession::where('department_id', $deptModel->id)
                            ->where('inspection_date', $today)
                            ->where('shift', $shift)
                            ->where('type', $type)
                            ->latest('id')
                            ->first();
            } else {
                // For 'all' mode (Area/Machine)
                // We don't need employee counts per location usually, or we count ALL actives
                $query->withCount(['employees' => function ($q) {
                    $q->where('is_active', true);
                }]);

                // Find ANY active session for this inspector today matching type/shift
                // We shouldn't restrict by department if we are doing a global inspection
                $session = InspectionSession::where('inspector_id', Auth::id())
                            ->where('inspection_date', $today)
                            ->where('shift', $shift)
                            ->where('type', $type)
                            ->latest('id')
                            ->first();

                $sessionIds = [];
                if ($session) {
                    $sessionIds = [$session->id];
                }
            }

            if ($type === 'personnel') {
                $shiftCards = [];
                
                if ($department !== 'all') {
                    // Get all shifts from DB and map to keys
                    $dbShifts = \App\Models\Shift::all();
                    $shiftLabels = [];
                    $shiftMappings = [];
                    foreach ($dbShifts as $s) {
                        $name = mb_strtolower(trim($s->shift_name));
                        if (in_array($name, ['morning', 'กะเช้า'])) {
                            $shiftKey = 'morning';
                            $shiftLabels['morning'] = 'กะเช้า (Morning)';
                        } elseif (in_array($name, ['afternoon', 'กะบ่าย'])) {
                            $shiftKey = 'afternoon';
                            $shiftLabels['afternoon'] = 'กะบ่าย (Afternoon)';
                        } elseif (in_array($name, ['night', 'กะดึก'])) {
                            $shiftKey = 'night';
                            $shiftLabels['night'] = 'กะดึก (Night)';
                        } else {
                            $shiftKey = 'custom_' . $s->id;
                            $shiftLabels[$shiftKey] = $s->shift_name;
                        }
                        if (!isset($shiftMappings[$shiftKey])) {
                            $shiftMappings[$shiftKey] = [];
                        }
                        $shiftMappings[$shiftKey][] = $s->id;
                    }
                    if (empty($shiftLabels)) {
                        $shiftLabels = ['morning' => 'กะเช้า (Morning)', 'night' => 'กะดึก (Night)'];
                        $shiftMappings = ['morning' => [], 'night' => []];
                    }

                    // Get all today's sessions for this department
                    $allTodaySessions = InspectionSession::where('department_id', $deptModel->id)
                                        ->where('inspection_date', $today)
                                        ->where('type', 'personnel')
                                        ->get();

                    // Get all today's logs for this department
                    $allTodaySessionIds = $allTodaySessions->pluck('id')->toArray();
                    $allTodayLogs = collect();
                    if (!empty($allTodaySessionIds)) {
                        $allTodayLogs = InspectionLog::whereIn('session_id', $allTodaySessionIds)
                                        ->whereNotNull('employee_id')
                                        ->select('employee_id', 'session_id')
                                        ->get();
                    }

                    // Get inspected employee IDs grouped by shift of the session
                    $inspectedByShift = [];
                    foreach ($allTodaySessions as $sess) {
                        $sessShift = $sess->shift;
                        if (!isset($inspectedByShift[$sessShift])) {
                            $inspectedByShift[$sessShift] = collect();
                        }
                        $sessLogEmployees = $allTodayLogs->where('session_id', $sess->id)->pluck('employee_id');
                        $inspectedByShift[$sessShift] = $inspectedByShift[$sessShift]->merge($sessLogEmployees);
                    }

                    // All inspected employee IDs across all shifts (for dedup)
                    $allInspectedIds = $allTodayLogs->pluck('employee_id')->unique()->toArray();

                    // Count employees with NO shift assigned (for fallback if all are unassigned)
                    $unassignedCount = \App\Models\Employee::where('department_id', $deptModel->id)
                                        ->where('is_active', true)
                                        ->whereNull('shift_id')
                                        ->count();
                    $totalDeptEmployees = \App\Models\Employee::where('department_id', $deptModel->id)
                                        ->where('is_active', true)
                                        ->count();
                    $hasAnyShiftAssigned = ($totalDeptEmployees > $unassignedCount);

                    foreach ($shiftLabels as $shiftKey => $shiftLabel) {
                        $shiftIds = $shiftMappings[$shiftKey] ?? [];
                        
                        $empCount = 0;
                        if (!empty($shiftIds)) {
                            $empCount = \App\Models\Employee::where('department_id', $deptModel->id)
                                        ->where('is_active', true)
                                        ->whereIn('shift_id', $shiftIds)
                                        ->count();
                        }
                        
                        // Count unique inspected employees for this shift
                        $inspectedInShift = isset($inspectedByShift[$shiftKey]) 
                            ? $inspectedByShift[$shiftKey]->unique()->count() 
                            : 0;
                            
                        $isCurrentShift = ($shiftKey === $shift);

                        // Loop Engineering Fix: Only fallback to totalDeptEmployees if NO employees in the entire department have shifts assigned.
                        // In fallback mode the same person appears in every shift's total, so we must deduct people
                        // already inspected in other shifts to avoid double-counting. When shifts ARE assigned
                        // each employee belongs to exactly one shift and no deduction is needed — deducting there
                        // wrongly zeros out shifts that other shifts have already inspected.
                        $inspectedInThisShiftIds = isset($inspectedByShift[$shiftKey]) ? $inspectedByShift[$shiftKey]->toArray() : [];
                        if (!$hasAnyShiftAssigned && $totalDeptEmployees > 0) {
                            $empCount = $totalDeptEmployees;
                            $inspectedInOtherShiftsIds = array_diff($allInspectedIds, $inspectedInThisShiftIds);
                            $empCount -= count($inspectedInOtherShiftsIds);
                            if ($empCount < 0) {
                                $empCount = 0;
                            }
                        }

                        // Also count employees from THIS shift that were inspected in ANY session
                        $shiftEmployeeIds = [];
                        if (!empty($shiftIds)) {
                            $shiftEmployeeIds = \App\Models\Employee::where('department_id', $deptModel->id)
                                            ->where('is_active', true)
                                            ->whereIn('shift_id', $shiftIds)
                                            ->pluck('id')->toArray();
                        }
                        
                        // If we used the fallback for empCount, we should count all inspected employees in this shift's session
                        $inspectedFromThisShift = count(array_intersect($shiftEmployeeIds, $allInspectedIds));
                        if (empty($shiftEmployeeIds)) {
                            $inspectedFromThisShift = $inspectedInShift;
                        }

                        $shiftCards[] = [
                            'id' => 'shift_' . $shiftKey,
                            'location_name' => $shiftLabel,
                            'employees_count' => $empCount,
                            'inspected_count' => $inspectedFromThisShift,
                            'description' => $isCurrentShift 
                                ? '🎯 กะที่กำลังตรวจอยู่ตอนนี้' 
                                : 'จำนวนพนักงานที่ตรวจแล้วในกะนี้',
                            'has_checkpoints' => true,
                            'is_current_shift' => $isCurrentShift,
                        ];
                    }
                }

                return response()->json([
                    'success' => true,
                    'locations' => $shiftCards,
                    'shift' => $shift,
                    'session_exists' => ($department !== 'all' ? !!$session : !empty($sessionIds)),
                    'session_status' => $session ? $session->status : null,
                    'session_round' => $session ? $session->round : null,
                ]);
            }

            $locations = $query->get();

            // BUG-012 Fix: Removed unused variable initialization when not needed
            // FIXED (Revert): Variable IS needed for closure below if no session exists
            $inspectedEmployeeIds = [];
            $inspectedLocationIds = [];
            $inspectedMachineIds = [];
            $noProductionLocationIds = [];
            $noProductionMachineIds = [];

            if ($department !== 'all' && $session) {
                $logs = InspectionLog::where('session_id', $session->id)->get();
                if ($type === 'personnel') {
                    $inspectedEmployeeIds = $logs->pluck('employee_id')->unique()->filter()->values()->toArray();
                } else {
                    $inspectedLocationIds = $logs->whereNull('machine_id')->pluck('location_id')->unique()->filter()->values()->toArray();
                    $inspectedMachineIds = $logs->whereNotNull('machine_id')->pluck('machine_id')->unique()->filter()->values()->toArray();
                    
                    $noProductionLocationIds = $logs->whereNull('machine_id')->where('result', 'no_production')->pluck('location_id')->unique()->filter()->values()->toArray();
                    $noProductionMachineIds = $logs->whereNotNull('machine_id')->where('result', 'no_production')->pluck('machine_id')->unique()->filter()->values()->toArray();
                }
            } else if ($department === 'all' && !empty($sessionIds)) {
                $logs = InspectionLog::whereIn('session_id', $sessionIds)->get();
                if ($type === 'personnel') {
                    $inspectedEmployeeIds = $logs->pluck('employee_id')->unique()->filter()->values()->toArray();
                } else {
                    $inspectedLocationIds = $logs->whereNull('machine_id')->pluck('location_id')->unique()->filter()->values()->toArray();
                    $inspectedMachineIds = $logs->whereNotNull('machine_id')->pluck('machine_id')->unique()->filter()->values()->toArray();
                    
                    $noProductionLocationIds = $logs->whereNull('machine_id')->where('result', 'no_production')->pluck('location_id')->unique()->filter()->values()->toArray();
                    $noProductionMachineIds = $logs->whereNotNull('machine_id')->where('result', 'no_production')->pluck('machine_id')->unique()->filter()->values()->toArray();
                }
            }

            // 3. Pre-fetch employee IDs by location to avoid N+1 queries
            $employeeIdsByLocation = collect();
            if ($type === 'personnel') {
                $empQuery = \App\Models\Employee::where('is_active', true)->whereNotNull('location_id');
                if ($department !== 'all' && isset($deptModel)) {
                    $empQuery->where('department_id', $deptModel->id);
                }
                $employeeIdsByLocation = $empQuery->select('id', 'location_id')->get()
                    ->groupBy('location_id')
                    ->map(fn($group) => $group->pluck('id')->toArray());
            }

            // 4. Map Inspection Data
            $locations->transform(function ($loc) use ($inspectedEmployeeIds, $inspectedLocationIds, $inspectedMachineIds, $noProductionLocationIds, $noProductionMachineIds, $type, $employeeIdsByLocation) {
                if ($type === 'personnel') {
                    $locEmployees = $employeeIdsByLocation->get($loc->id, []);
                    $loc->inspected_count = count(array_intersect($locEmployees, $inspectedEmployeeIds));
                    $loc->has_checkpoints = true;
                } else {
                    // For Area
                    $loc->inspected_count = in_array($loc->id, $inspectedLocationIds) ? 1 : 0;
                    $loc->is_no_production = in_array($loc->id, $noProductionLocationIds);
                    
                    // Check area checkpoints
                    $loc->has_checkpoints = false;
                    if ($type === 'area') {
                         $loc->has_checkpoints = $loc->checkpoints()->where('type', 'area')->where('is_active', true)->exists();
                    }
                    
                    // Machine inspection status
                    if ($loc->machines) {
                        foreach ($loc->machines as $m) {
                            $m->is_inspected = in_array($m->id, $inspectedMachineIds);
                            $m->is_no_production = in_array($m->id, $noProductionMachineIds);
                            // Check machine checkpoints
                            $m->has_checkpoints = $m->checkpoints()->where('is_active', true)->exists();
                        }
                    }
                }
                return $loc;
            });

            return response()->json([
                'success' => true,
                'locations' => $locations,
                'shift' => $shift,
                'session_exists' => ($department !== 'all' ? !!$session : !empty($sessionIds)),
                'session_status' => $session ? $session->status : null,
                'session_round' => $session ? $session->round : null,
            ]);
        } catch (\Exception $e) {
            \Log::error('getDepartmentStats error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'เกิดข้อผิดพลาดในการโหลดข้อมูล',
            ], 500);
        }
    }

    public function startSession($type, Request $request)
    {
        $rules = [
            'department_id' => ($type === 'personnel') ? 'required|exists:departments,id' : 'nullable',
            'targets' => 'nullable|array',
            'shift' => 'nullable|in:morning,night'
        ];
        
        $request->validate($rules);

        // For Area/Machine, if no department selected, use User's department or First available
        // This is a workaround if DB requires department_id. Ideally schema should allow null.
        // Assuming DB requires it based on previous code.
        $deptId = $request->department_id;
        if (empty($deptId) && ($type === 'area' || $type === 'machine')) {
            $deptId = Auth::user()->department_id ?? Department::first()?->id;
        }

        if (empty($deptId)) {
            return redirect()->route('inspection.dashboard', $type)
                ->with('error', 'ไม่พบแผนกในระบบ กรุณาสร้างแผนกก่อน (No department found)');
        }

        try {
            $session = $this->inspectionService->startSession(
                Auth::user(),
                (int) ($request->department_id ?? $deptId),
                $type,
                $request->boolean('force_new_round'),
                $request->input('shift')
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('inspection.dashboard', $type)
                ->with('error', $e->getMessage());
        }

        if ($type === 'personnel') {
            return redirect()->route('inspection.scan', $session->id);
        } else {
            // For Area/Machine, redirect to bulk checklist with targets
            $targetList = is_array($request->targets) ? $request->targets : [];

            // If resuming and no targets selected, load targets that were already inspected in this session
            if (empty($targetList) && $session) {
                 // Query existing logs for this session to get locations and machines
                 $existingLogs = \App\Models\InspectionLog::where('session_id', $session->id)->get();
                 
                 $locIds = $existingLogs->pluck('location_id')->filter()->unique();
                 foreach ($locIds as $lid) {
                     $targetList[] = "loc:{$lid}";
                 }
                 
                 $machineIds = $existingLogs->pluck('machine_id')->filter()->unique();
                 foreach ($machineIds as $mid) {
                     $targetList[] = "machine:{$mid}";
                 }

                 // If still empty (e.g., started session but no logs saved), return error
                 if (empty($targetList)) {
                     return redirect()->route('inspection.dashboard', $type)
                         ->with('error', 'กรุณาเลือกพื้นที่หรือเครื่องจักรที่ต้องการตรวจบนหน้า Dashboard ก่อนเริ่ม (Please select targets)');
                 }
            }

            $targets = implode(',', $targetList);

            return redirect()->route('inspection.area.bulk', [
                'department' => $session->department_id,
                'session' => $session->id,
                'targets' => $targets
            ]);
        }
    }

    public function markAbsent(InspectionSession $session, Employee $employee)
    {
        $this->authorizeSessionOwner($session);

        if ($session->isLocked()) {
            return redirect()->back()
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        if ($session->status === 'completed') {
            return redirect()->back()
                ->with('error', 'เซสชันนี้ถูกบันทึกจบงานไปแล้ว (Session already completed)');
        }

        // Verify employee belongs to this session's department
        if ($employee->department_id !== $session->department_id) {
            return redirect()->back()
                ->with('error', 'พนักงานไม่ได้อยู่ในแผนกของเซสชันนี้');
        }

        $checkpointType = match($session->type) {
            'personnel' => 'person',
            'area' => 'area',
            'machine' => 'machine',
            default => null,
        };

        $checkpointQuery = Checkpoint::where('is_active', true);
        if ($checkpointType) {
            $checkpointQuery->where('type', $checkpointType);
        }

        $employeeLocation = $employee->location_id ? $employee->location : null;
        if ($employeeLocation) {
            $checkpoints = $employeeLocation->checkpoints()
                ->where('is_active', true)
                ->when($checkpointType, fn($q) => $q->where('type', $checkpointType))
                ->get();

            if ($checkpoints->isEmpty()) {
                $checkpoints = $checkpointQuery->get();
            }
        } else {
            $checkpoints = $checkpointQuery->get();
        }

        if ($checkpoints->isEmpty()) {
            return redirect()->back()->with('error', 'ไม่พบหัวข้อการตรวจสำหรับพนักงานคนนี้');
        }

        DB::beginTransaction();
        try {
            foreach ($checkpoints as $cp) {
                // Check if log already exists
                $exists = InspectionLog::where('session_id', $session->id)
                    ->where('employee_id', $employee->id)
                    ->where('checkpoint_id', $cp->id)
                    ->exists();

                if (!$exists) {
                    InspectionLog::create([
                        'session_id' => $session->id,
                        'employee_id' => $employee->id,
                        'checkpoint_id' => $cp->id,
                        'result' => 'absent',
                        'checkpoint_title_snapshot' => $cp->title,
                        'dept_snapshot' => $employee->department->dept_name ?? null,
                    ]);
                }
            }
            DB::commit();
            return redirect()->route('inspection.browse', $session->id)
                ->with('success', "บันทึกสถานะลางานสำหรับ {$employee->fullname} เรียบร้อยแล้ว");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    
    public function browseEmployees(InspectionSession $session, Request $request)
    {
        $this->authorizeSessionOwner($session);

        if ($session->isLocked()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        if ($session->status === 'completed' && !$session->hasPendingRecleans()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกบันทึกจบงานไปแล้ว (Session already completed)');
        }

        // Get active employees in this department
        $showAll = $request->boolean('show_all');
        $baseQuery = Employee::with(['location', 'department', 'shift'])
            ->where('department_id', $session->department_id)
            ->where('is_active', true);

        if (!$showAll) {
            $shiftNames = match($session->shift) {
                'morning' => ['morning', 'กะเช้า'],
                'night' => ['night', 'กะดึก'],
                default => [$session->shift]
            };
            $baseQuery->whereHas('shift', function ($q) use ($shiftNames) {
                $q->whereIn('shift_name', $shiftNames);
            });
        }

        $allEmployeeIds = $baseQuery->pluck('id')->toArray();
        $totalEmployees = count($allEmployeeIds);

        // Search filter
        $search = $request->input('q', '');
        if ($search) {
            $baseQuery->where(function($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $employees = $baseQuery->orderBy('location_id')
            ->orderBy('fullname')
            ->get();

        // Get inspected TODAY across ALL sessions for this department
        $todaySessions = InspectionSession::where('department_id', $session->department_id)
            ->where('inspection_date', $session->inspection_date)
            ->where('type', 'personnel')
            ->pluck('id')
            ->toArray();

        $allTodayLogs = InspectionLog::whereIn('session_id', $todaySessions)
            ->whereNotNull('employee_id')
            ->select('employee_id', 'result', 'session_id')
            ->get();

        // 1. Logs in CURRENT session
        $currentSessionLogs = $allTodayLogs->where('session_id', $session->id);
        $inspectedCurrentIds = $currentSessionLogs->whereIn('result', ['pass', 'fail'])->pluck('employee_id')->unique()->toArray();
        $absentIds = $currentSessionLogs->where('result', 'absent')->pluck('employee_id')->unique()->toArray();
        
        // 2. Logs in OTHER sessions today
        $otherSessionLogs = $allTodayLogs->where('session_id', '!=', $session->id);
        $inspectedOtherShiftIds = $otherSessionLogs->whereIn('result', ['pass', 'fail'])->pluck('employee_id')->unique()->toArray();

        // Merge: treat employees inspected in ANY session today as "inspected" (non-clickable)
        $inspectedIds = array_unique(array_merge($inspectedCurrentIds, $inspectedOtherShiftIds));

        // All IDs that are either done or absent across ALL sessions today are considered "completed" for progress bar
        $completedIds = array_unique(array_merge($inspectedIds, $absentIds));

        // Fetch employees with pending Re-cleans
        $pendingRecleanEmployeeIds = InspectionLog::where('verification_status', 'reclean')
            ->whereDoesntHave('rechecks')
            ->whereIn('employee_id', $allEmployeeIds)
            ->pluck('employee_id')
            ->unique()
            ->toArray();

        // Group employees clearly
        $grouped = collect();
        $currentShiftEmployees = collect();
        $inspectedPreviousShiftEmployees = collect();
        $otherShiftEmployees = collect();

        $currentShiftNames = match($session->shift) {
            'morning' => ['morning', 'กะเช้า'],
            'afternoon' => ['afternoon', 'กะบ่าย'],
            'night' => ['night', 'กะดึก'],
            default => [$session->shift]
        };

        foreach ($employees as $emp) {
            $shiftName = optional($emp->shift)->shift_name;
            if (in_array($shiftName, $currentShiftNames)) {
                $currentShiftEmployees->push($emp);
            } elseif (in_array($emp->id, $inspectedOtherShiftIds)) {
                $inspectedPreviousShiftEmployees->push($emp);
            } else {
                $otherShiftEmployees->push($emp);
            }
        }

        if ($currentShiftEmployees->isNotEmpty()) {
            $grouped->put('เป้าหมายกะปัจจุบัน (' . ucfirst($session->shift) . ')', $currentShiftEmployees);
        }
        if ($inspectedPreviousShiftEmployees->isNotEmpty()) {
            $grouped->put('ถูกตรวจแล้วในกะก่อนหน้า', $inspectedPreviousShiftEmployees);
        }
        if ($otherShiftEmployees->isNotEmpty()) {
            $grouped->put('พนักงานกะอื่น (นอกกะ)', $otherShiftEmployees);
        }

        // Progress stats
        $inspectedCount = count(array_intersect($allEmployeeIds, $completedIds));
        $progressPercent = $totalEmployees > 0 ? round(($inspectedCount / $totalEmployees) * 100) : 0;

        return view('inspections.browse', compact(
            'session', 'grouped', 'inspectedIds', 'inspectedCurrentIds', 'absentIds', 'search',
            'totalEmployees', 'inspectedCount', 'progressPercent', 'showAll',
            'pendingRecleanEmployeeIds', 'inspectedOtherShiftIds'
        ));
    }

    public function scan(InspectionSession $session)
    {
        $this->authorizeSessionOwner($session);

        if ($session->isLocked()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        if ($session->status === 'completed' && !$session->hasPendingRecleans()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกบันทึกจบงานไปแล้ว ไม่สามารถตรวจเพิ่มได้ (Session already completed)');
        }

        $failedCount = \App\Models\InspectionLog::where('session_id', $session->id)
            ->where('result', 'fail')
            ->distinct('employee_id')
            ->count('employee_id');

        return view('inspections.scan', compact('session', 'failedCount'));
    }

    public function pauseSession(InspectionSession $session)
    {
        $this->authorizeSessionOwner($session);

        if ($session->isLocked()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        if ($session->status === 'completed') {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้จบงานไปแล้ว ไม่สามารถพักได้ (Session already completed)');
        }

        $session->update(['status' => 'paused']);

        return redirect()->route('inspection.dashboard', $session->type)
            ->with('success', 'พักการตรวจชั่วคราวแล้ว (Session Paused)');
    }

    public function finishSession(InspectionSession $session)
    {
        $this->authorizeSessionOwner($session);

        if ($session->isLocked()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        // Fails with photo + correction are the normal flow — Supervisor verifies them
        // and decides re-clean / accept / escalate to CAR. Blocking finish here would trap
        // any inspection that legitimately failed a checkpoint.

        $this->inspectionService->finishSession($session);

        return redirect()->route('inspection.dashboard', $session->type)
            ->with('success', 'บันทึกจบงานสรุปยอดการตรวจเรียบร้อยแล้ว (Session Completed)');
    }

    /**
     * Bulk Pass: Mark all remaining (uninspected) employees as "pass" for all checkpoints.
     */
    public function bulkPassPersonnel(InspectionSession $session, Request $request)
    {
        $this->authorizeSessionOwner($session);

        if ($session->isLocked()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)'], 403);
            }
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        try {
            $count = $this->inspectionService->bulkPassRemaining($session);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "บันทึกผ่านทุกคนที่เหลือเรียบร้อย! ({$count} คน)",
                    'count' => $count
                ]);
            }

            if ($count === 0) {
                return redirect()->route('inspection.dashboard', $session->type)
                    ->with('info', 'ไม่มีพนักงานที่เหลือให้ Bulk Pass แล้ว (No remaining employees)');
            }

            return redirect()->route('inspection.dashboard', $session->type)
                ->with('success', "✅ ผ่านทุกคนที่เหลือเรียบร้อย! ({$count} คน) - Bulk Pass Completed");
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
            }
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    public function showChecklist(InspectionSession $session, $hash, Request $request)
    {
        $this->authorizeSessionOwner($session);

        if ($session->isLocked()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        // Allow lookup by QR Hash OR Human Readable Employee ID
        $employee = Employee::where('qr_code_hash', $hash)
                    ->orWhere('employee_id', $hash)
                    ->first();

        if (!$employee) {
            return redirect()->route('inspection.scan', $session->id)
                ->with('error', 'ไม่พบพนักงานสำหรับรหัส: ' . e($hash));
        }

        // Security Check: Is employee in the session's department?
        if ($employee->department_id !== $session->department_id) {
            return redirect()->route('inspection.scan', $session->id)
                ->with('error', "Employee ({$employee->fullname}) belongs to " . ($employee->department->dept_name ?? 'another dept') . ", not here!");
        }

        // Map session type to checkpoint type
        $checkpointType = match($session->type) {
            'personnel' => 'person',
            'area' => 'area',
            'machine' => 'machine',
            default => null,
        };

        // Dynamic Loading: If employee has a location, load only those checkpoints.
        // If no location (or no mappings), fall back to all active checkpoints of the correct type.
        $checkpointQuery = Checkpoint::where('is_active', true)->orderBy('sort_order')->orderBy('id');
        if ($checkpointType) {
            $checkpointQuery->where('type', $checkpointType);
        }

        // Loop Engineering Fix: Dynamic Location Selector
        $currentLocationId = $request->query('location_id', $employee->location_id);
        $employeeLocation = $currentLocationId ? \App\Models\Location::find($currentLocationId) : null;
        
        // Fetch all locations for the override dropdown
        $departmentLocations = \App\Models\Location::orderBy('location_name')->get();
        
        if ($employeeLocation) {
            $checkpoints = $employeeLocation->checkpoints()
                ->where('is_active', true)
                ->when($checkpointType, fn($q) => $q->where('type', $checkpointType))
                ->orderBy('sort_order')
                ->orderBy('checkpoints.id')
                ->get();

            // If the location has NO checkpoints mapped, fall back to global ones
            if ($checkpoints->isEmpty()) {
                $checkpoints = $checkpointQuery->get();
            }
        } else {
            $checkpoints = $checkpointQuery->get();
        }
        
        // Load existing logs for today (if re-checking)
    $existingLogs = InspectionLog::where('session_id', $session->id)
        ->where('employee_id', $employee->id)
        ->get()
        ->keyBy('checkpoint_id');

    // Load active Re-clean requests
    // FEATURE: Active Alert for Pending Re-cleans
    $shouldLoadRecleans = !$request->has('ignore_reclean');
    $recleanRequests = collect();
    $previousRecleanCount = 0;

    if ($shouldLoadRecleans) {
        $recleanQuery = InspectionLog::where('employee_id', $employee->id)
            ->where('verification_status', 'reclean')
            ->whereDoesntHave('rechecks');
        
        $recleanRequests = $recleanQuery->get();
        
        // Check if any come from a DIFFERENT (previous) session
        $previousRecleanCount = $recleanRequests->where('session_id', '!=', $session->id)->count();

        $recleanRequests = $recleanRequests->keyBy('checkpoint_id');
    }

    // 10% Randomized Verification Photo requirement
    // Use crc32 for deterministic pseudo-randomness based on session and employee ID
    $requiresRandomPhoto = false;
    $randomEvidencePhoto = null;

    if ($session->type === 'personnel') {
        $hashValue = crc32($session->id . '-' . $employee->id);
        $requiresRandomPhoto = ($hashValue % 10) === 0; // 10% chance
        
        $sessionKey = "random_photo_{$session->id}_{$employee->id}";

        // Handle POST submission from Scan layer
        if ($request->isMethod('post') && $request->has('random_evidence_photo_base64')) {
            $randomEvidencePhoto = $request->input('random_evidence_photo_base64');
            session()->put($sessionKey, $randomEvidencePhoto);
        } else {
            // Check if we already took it recently (e.g. page refresh)
            $randomEvidencePhoto = session()->get($sessionKey);
        }
    }

    return view('inspections.form', compact('session', 'employee', 'checkpoints', 'existingLogs', 'recleanRequests', 'previousRecleanCount', 'requiresRandomPhoto', 'randomEvidencePhoto', 'departmentLocations', 'currentLocationId'));
    }

    public function storeLog(Request $request, InspectionSession $session)
    {
        $this->authorizeSessionOwner($session);

        if ($session->isLocked()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        // Anti-Cheat (Speed Trap): Level 1
        // Check if the same inspector has submitted another personnel inspection too quickly (< 15 seconds)
        // Only applies if the previous inspection was for a DIFFERENT employee (prevent rapid swiping)
        $lastLog = \App\Models\InspectionLog::whereHas('session', function($q) use ($session) {
            $q->where('inspector_id', $session->inspector_id);
        })
        ->whereNotNull('employee_id')
        ->where('employee_id', '!=', $request->employee_id)
        ->latest('created_at')
        ->first();

        if ($lastLog && $lastLog->created_at->diffInSeconds(now()) < 3) {
            \Illuminate\Support\Facades\Log::warning('Speed Trap Triggered (Personnel)', [
                'inspector_id' => $session->inspector_id,
                'session_id' => $session->id,
                'employee_id' => $request->employee_id,
                'time_diff' => $lastLog->created_at->diffInSeconds(now()),
                'ip' => $request->ip()
            ]);

            return redirect()->back()
                ->with('error', 'คุณทำรายการเร็วเกินไป กรุณารอสักครู่ (ประมาณ 3 วินาที) แล้วกดบันทึกใหม่อีกครั้ง');
        }

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'random_evidence_photo_base64' => 'nullable|string',
            'logs' => 'required|array',
            'logs.*.checkpoint_id' => 'required|exists:checkpoints,id',
            'logs.*.result' => 'required|in:pass,fail',
        ]);

        $employee = Employee::findOrFail($request->employee_id);
        if ($employee->department_id !== $session->department_id) {
            return redirect()->route('inspection.scan', $session->id)
                ->with('error', 'พนักงานไม่ได้อยู่ในแผนกของเซสชันนี้ (Employee not in session department)');
        }

        try {
            // Process random evidence photo from Base64
            $randomPhotoPath = null;
            if ($request->filled('random_evidence_photo_base64')) {
                $base64String = $request->input('random_evidence_photo_base64');
                // Remove data URI scheme prefix if present
                if (preg_match('/^data:image\/(.*?);base64,/', $base64String, $matches)) {
                    $base64String = substr($base64String, strpos($base64String, ',') + 1);
                }
                
                $imageBinary = base64_decode($base64String);
                $filename = 'evidence_random_' . uniqid() . '_' . $session->id . '_' . $request->employee_id . '.webp';
                $path = 'evidence/' . $filename;
                
                try {
                    $image = Image::read($imageBinary);
                    $image->scale(width: 800);
                    $encoded = $image->toWebp(quality: 75);
                    Storage::disk('public')->put($path, (string) $encoded);
                    $randomPhotoPath = $path;
                } catch (\Exception $e) {
                    // Fallback if image manipulation fails
                    Storage::disk('public')->put($path, $imageBinary);
                    $randomPhotoPath = $path;
                }
                
                // Clear the temporary random photo from session once it is saved
                session()->forget("random_photo_{$session->id}_{$request->employee_id}");
            }

            // Pre-process images and correction actions
            $isFirstLog = true;
            foreach ($request->logs as $checkpointId => $data) {
                // Validation for Fail actions
                if ($data['result'] === 'fail') {
                    if (empty($data['correction'])) {
                         return back()->with('error', 'กรุณาระบุวิธีการแก้ไข (Correction Action) สำหรับรายการที่ไม่ผ่าน');
                    }
                    // Photo validation could be dynamic based on rules, keeping simple here
                    if (!$request->hasFile("logs.$checkpointId.photo")) {
                         $cpTitle = Checkpoint::find($checkpointId)?->title ?? 'รายการที่ไม่ผ่าน';
                         return back()->with('error', "กรุณาถ่ายรูปหลักฐาน (Evidence Photo) สำหรับ: $cpTitle");
                    }
                }

                $photoPath = null;
                if ($request->hasFile("logs.$checkpointId.photo")) {
                    $file = $request->file("logs.$checkpointId.photo");
                    $filename = 'evidence_' . uniqid() . '_' . $session->id . '_' . $checkpointId . '.webp';
                    $path = 'evidence/' . $filename;

                    try {
                        $image = Image::read($file);
                        $image->scale(width: 800);
                        $encoded = $image->toWebp(quality: 75);
                        Storage::disk('public')->put($path, $encoded);
                        $photoPath = $path;
                    } catch (\Exception $e) {
                        $photoPath = $file->store('evidence', 'public');
                    }
                }
                // Attach random evidence photo to the FIRST checkpoint log if it exists
                if ($isFirstLog && $randomPhotoPath && !$photoPath) {
                    $photoPath = $randomPhotoPath;
                }
                $isFirstLog = false;

                $this->inspectionService->storeLog(
                    $session,
                    $request->employee_id,
                    $checkpointId,
                    $data,
                    $photoPath
                );
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
             return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', $e->getMessage());
        }

        $employee = Employee::find($request->employee_id);

        // Auto-update employee's master shift to match the latest inspected session's shift
        $shiftModel = \App\Models\Shift::whereRaw('LOWER(shift_name) = ?', [strtolower($session->shift)])->first();
        if ($shiftModel && $employee->shift_id !== $shiftModel->id) {
            $employee->update(['shift_id' => $shiftModel->id]);
        }

        // Loop Engineering Fix: Auto-update Master Data for Location Override
        if ($request->has('location_id') && !empty($request->location_id)) {
            if ($employee->location_id != $request->location_id) {
                $employee->update(['location_id' => $request->location_id]);
            }
        }

        if ($session->status === 'completed') {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('success', 'บันทึกการแก้ไขเรียบร้อยแล้ว (Saved correction for ' . $employee->fullname . ')');
        }

        $queryParams = $request->query();
        if (isset($queryParams['from']) && $queryParams['from'] === 'browse') {
            unset($queryParams['from']);
            return redirect()->route('inspection.browse', ['session' => $session->id] + $queryParams)
                ->with('success', 'Saved inspection for ' . $employee->fullname);
        }

        return redirect()->route('inspection.scan', $session->id)
            ->with('success', 'Saved inspection for ' . $employee->fullname);
    }
    public function verification(Request $request)
    {
        $activeTab = $request->input('tab', 'pending');
        
        // Date Range Logic
        // If no date is provided, 'completed' defaults to today. Actionable tabs default to empty (All time).
        $defaultDate = ($activeTab === 'completed') ? date('Y-m-d') : '';
        $dateStr = $request->input('date', $defaultDate);
        
        // Parse Flatpickr Range "YYYY-MM-DD to YYYY-MM-DD"
        $startDate = null;
        $endDate = null;
        if (!empty($dateStr)) {
            // Support both English ' to ' and Thai ' ถึง ' from Flatpickr localization
            $separator = str_contains($dateStr, ' ถึง ') ? ' ถึง ' : ' to ';
            $dates = explode($separator, $dateStr);
            $startDate = trim($dates[0]);
            $endDate = trim($dates[1] ?? $dates[0]); // If single date selected, end = start
        }

        $user = Auth::user();

        // --- SCOPE DEFINITION (Role Matrix Compliance) ---
        // Global scope users (QA, Admin, Executive) see all departments
        // Isolated scope users see only their own department  
        $scopeDeptId = null;
        if (!$user->hasGlobalVisibility()) {
            $scopeDeptId = $user->department_id;
        }

        $query = InspectionLog::with(['employee', 'checkpoint', 'employee.department', 'location', 'machine', 'session.inspector', 'verifier', 'correctiveAction.approvals']) 
            ->where(function($q) use ($startDate, $endDate, $activeTab) {
                if ($startDate && $endDate) {
                    // If user EXPLICITLY selected a date range, apply it to ALL items
                    // We must fetch all statuses in this range so the tab badges calculate correctly
                    $q->whereBetween('inspected_at', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59'
                    ]);
                } else {
                    // No date filter (Inbox Mode Default)
                    // Fetch all actionable items regardless of date (Pending QA, Reclean, Pending Manager Approval)
                    $q->whereNull('verified_at')
                      ->orWhereIn('verification_status', ['reclean', 'verified']);
                    
                    // For fully completed items, limit to today to prevent overloading
                    $q->orWhere(function($subQ) {
                        $subQ->whereIn('verification_status', ['approved', 'auto_verified'])
                             ->whereDate('inspected_at', date('Y-m-d'));
                    });
                }
            })
            ->whereDoesntHave('location', function ($q) {
                $q->doesntHave('checkpoints')
                  ->doesntHave('machines');
            })
            ->orderBy('inspected_at', 'desc');

        if ($scopeDeptId) {
            $query->where(function($q) use ($scopeDeptId) {
                $q->whereHas('employee', fn($subQ) => $subQ->where('department_id', $scopeDeptId))
                  ->orWhere(function($q2) use ($scopeDeptId) {
                      $q2->whereNull('employee_id')
                         ->whereHas('session', fn($sq) => $sq->where('department_id', $scopeDeptId));
                  });
            });
        }

        $logs = $query->get();

        // Pre-calculate which employees failed to group them together
        $failedEmployeeIdsInSession = collect($logs)->where('result', 'fail')->pluck('employee_id')->unique()->filter()->values()->toArray();

        $groupedInspections = $logs->groupBy(function($log) use ($failedEmployeeIdsInSession) {
            if ($log->employee_id) {
                $statusType = in_array($log->employee_id, $failedEmployeeIdsInSession) ? 'failed' : 'passed';
                return $log->session_id . '_personnel_' . $statusType;
            } else {
                $locId = $log->location_id ?? ($log->machine->location_id ?? 'unknown');
                
                // แยกกลุ่มตามสถานะการตรวจสอบ (รอดำเนินการ vs สั่งแก้ไข)
                // เพื่อให้รายการผ่าน (pending) ไปอยู่แท็บรอทวนสอบ และรายการไม่ผ่าน (reclean) ไปอยู่แท็บสั่งแก้ไข
                $statusType = ($log->verification_status === 'reclean') ? 'failed' : 'passed';
                
                return $log->session_id . '_loc_' . $locId . '_' . $statusType;
            }
        });

        // Pre-fetch monthly failure counts to avoid N+1 queries in the map loop
        $month = now()->month;
        $year = now()->year;
        $employeeIds = $logs->pluck('employee_id')->unique()->filter()->values();
        $failureCounts = collect();
        if ($employeeIds->isNotEmpty()) {
            $failureCounts = InspectionLog::whereIn('employee_id', $employeeIds)
                ->whereYear('inspected_at', $year)
                ->whereMonth('inspected_at', $month)
                ->where('result', 'fail')
                ->selectRaw('employee_id, COUNT(*) as fail_count')
                ->groupBy('employee_id')
                ->pluck('fail_count', 'employee_id');
        }

        $groupedInspections = $groupedInspections->map(function ($logsInGroup) use ($dateStr, $failureCounts) {
            $firstLog = $logsInGroup->first();
            $session = $firstLog->session;
            
            // BUG-009 Fix: Inconsistent Type Check
            $type = $firstLog->machine_id ? 'machine' : 'area'; 
            if ($firstLog->employee_id) {
                 $type = 'person';
            }

            $shift = $session->shift;
            $round = $session->round ?? 1; 
            $inspectorName = $session->inspector->name ?? 'Unknown';
            $shiftLabel = ucfirst($firstLog->session->shift ?? '-');

            $employee = $firstLog->employee;
            $location = $firstLog->location;
            $machine = $firstLog->machine;
            
            $failedLogs = $logsInGroup->filter(function ($log) {
                return $log->result === 'fail';
            });
            
            // Check if all failures are essentially "resolved" (approved)
            // If so, we can visually show the group as "Pass" (or at least not active Fail)
            $outstandingFailures = $failedLogs->filter(function($log) {
                return !in_array($log->verification_status, ['approved', 'auto_verified']);
            });

            $isPass = $outstandingFailures->isEmpty();

            // Check for 100% no_production or absent
            $isAllNoProduction = $logsInGroup->every(fn($l) => $l->result === 'no_production');
            $isAllAbsent = $logsInGroup->every(fn($l) => $l->result === 'absent');
            
            $status = 'pass';
            $isActionRequired = true;

            if ($isAllNoProduction) {
                $status = 'no_production';
                $isActionRequired = true; // Loop Engineering: User wants to manually verify N/A to keep logs
            } elseif ($isAllAbsent) {
                $status = 'absent';
                $isActionRequired = true; // Loop Engineering: User wants to manually verify Absent to keep logs
            } elseif (!$isPass) {
                $status = 'fail';
            }

            if ($firstLog->employee_id) {
                $type = 'person';
                $employeeCount = $logsInGroup->pluck('employee_id')->unique()->count();
                $isFailedGroup = $logsInGroup->contains('result', 'fail');
                
                $name = "ตรวจพนักงาน จำนวน {$employeeCount} คน";
                if ($isFailedGroup) {
                    $name .= " (พบข้อบกพร่อง)";
                } else {
                    $name .= " (ผ่านทั้งหมด)";
                }

                $subtext = $firstLog->session->department->dept_name ?? '-';
                
                $statusType = $isFailedGroup ? 'failed' : 'passed';
                $modalId = 'sess_personnel_' . $firstLog->session_id . '_' . $statusType;
                
                $imagePath = null;
                $employee = null; // Unset so UI treats it as a group

                $monthlyFailures = 0;
                $hygieneScore = 100;
                $trafficLight = 'green';
            } else {
                $machineCount = $logsInGroup->pluck('machine_id')->unique()->filter()->count();
                $hasArea = $logsInGroup->contains(fn($l) => is_null($l->machine_id));
                $type = 'machine'; // Default to machine so it shows the machine icon, or area if only area
                if ($machineCount === 0) $type = 'area';
                
                // Find the best representation of location
                $location = $firstLog->location ?? ($firstLog->machine->location ?? null);
                
                $name = $location ? $location->location_name : 'พื้นที่ไม่ระบุ';
                if ($machineCount > 0 && $hasArea) {
                     $name .= " (พื้นที่ + อุปกรณ์ {$machineCount} ชิ้น)";
                     $subtext = 'การตรวจสอบพื้นที่และเครื่องจักร';
                } elseif ($machineCount > 0) {
                     $name .= " (ตรวจอุปกรณ์ {$machineCount} ชิ้น)";
                     $subtext = 'การตรวจสอบเครื่องจักร/อุปกรณ์';
                } else {
                     $subtext = 'การตรวจสอบพื้นที่';
                }
                
                $modalId = 'loc_' . ($location->id ?? rand()) . '_sess_' . $firstLog->session_id;
                $imagePath = $location->image ?? null;
                
                $monthlyFailures = 0;
                $hygieneScore = 100;
                $trafficLight = 'green';
            }

            $hasReclean = $logsInGroup->contains('verification_status', 'reclean');
            $hasPending = $logsInGroup->contains(fn($l) => is_null($l->verified_at));

            // Check Session Approval (Manager Level)
            // Check Group Approval (Log Level)
            $isGroupApproved = $logsInGroup->every(fn($l) => $l->verification_status === 'approved');
            $isGroupAutoVerified = $logsInGroup->every(fn($l) => $l->verification_status === 'auto_verified');
            
            // Mix of approved and auto_verified
            if (!$isGroupApproved && !$isGroupAutoVerified) {
                $isGroupApprovedMix = $logsInGroup->every(fn($l) => in_array($l->verification_status, ['approved', 'auto_verified']));
                if ($isGroupApprovedMix) {
                    $isGroupApproved = true;
                }
            }

            // Determine Group Status Priority: Approved > Auto-verified > Re-clean > Pending > Verified
            if ($isGroupApproved) {
                $groupStatus = 'approved';
            } elseif ($isGroupAutoVerified) {
                $groupStatus = 'auto_verified';
            } elseif ($hasReclean) {
                $groupStatus = 'reclean';
            } elseif ($hasPending) {
                $groupStatus = 'pending'; 
            } else {
                $groupStatus = 'verified';
            }

            return (object) [
                'type' => $type,
                'name' => $name,
                'subtext' => $subtext,
                'modal_id' => $modalId,
                'image_path' => str_replace('/storage/', '', $imagePath),
                'employee' => $employee,
                'machine' => $machine,
                'location' => $location,
                'inspector_name' => $inspectorName,
                'shift' => $shiftLabel,
                'round' => $round,
                'session_id' => $session->id, // Important for Approval
                'date' => $logsInGroup->max('inspected_at')?->format('d/m/Y') ?? '-',
                'time' => $logsInGroup->max('inspected_at')?->format('H:i') ?? '-',
                'status' => $status,
                'is_action_required' => $isActionRequired,
                'findings' => $failedLogs->values(),
                'all_logs' => $logsInGroup->values(),
                'is_verified' => !$hasPending && !$hasReclean,
                'is_approved' => $isGroupApproved || $isGroupAutoVerified,
                'is_acknowledged' => $logsInGroup->every(fn($l) => !is_null($l->acknowledged_at)), // Gap 3: Check if acknowledged
                'department_id' => $employee ? $employee->department_id : null, // Gap 3: For Acknowledge permission check
                'log_ids' => $logsInGroup->pluck('id')->toArray(),
                'monthly_failures' => $monthlyFailures,
                'hygiene_score' => $hygieneScore,
                'traffic_light' => $trafficLight,
                'verification_status' => $groupStatus, 
                'verification_comment' => $firstLog->verification_comment,
                'verifier_name' => $firstLog->verifier->name ?? null,
                'verified_at' => $firstLog->verified_at ? $firstLog->verified_at->format('d/m/Y H:i') : null,
                'approved_by_name' => $session->approvedBy->name ?? null,
                'approved_at' => $session->approved_at ? $session->approved_at->format('d/m/Y H:i') : null,
            ];
        });

        // Filter out groups that do not require any action (e.g. 100% no_production)
        $groupedInspections = $groupedInspections->filter(function($g) {
            return $g->is_action_required;
        });

        // Filter the full list by activeTab first to calculate context-aware typeCounts
        $activeTab = $request->input('tab', 'pending');
        $tabFilteredInspections = $groupedInspections->filter(function($g) use ($activeTab) {
            if ($activeTab === 'pending') {
                return $g->verification_status === 'pending';
            } elseif ($activeTab === 'completed') {
                return in_array($g->verification_status, ['verified', 'approved', 'auto_verified']);
            } elseif ($activeTab === 'reclean') {
                return $g->verification_status === 'reclean';
            }
            return true;
        });

        // 1. Calculate Type Counts (Only for the current active tab status)
        $typeCounts = [
            'person' => $tabFilteredInspections->filter(fn($g) => $g->type === 'person')->count(),
            'machine' => $tabFilteredInspections->filter(fn($g) => in_array($g->type, ['machine', 'area']))->count(),
        ];

        // 2. Type Filtering (Default to 'person')
        $filterType = $request->input('filter_type', 'person');
        if (!in_array($filterType, ['person', 'area', 'machine'])) {
            $filterType = 'person';
        }
        
        // Filter the main list by selected type (Combine area and machine for 'machine' filter)
        $groupedInspections = $groupedInspections->filter(function($g) use ($filterType) {
            if ($filterType === 'machine') {
                return in_array($g->type, ['machine', 'area']);
            }
            return $g->type === $filterType;
        });

        // 3. Calculate Status Counts (For the selected type)
        $counts = [
            'pending' => $groupedInspections->filter(fn($g) => $g->verification_status === 'pending')->count(),
            'completed' => $groupedInspections->filter(fn($g) => in_array($g->verification_status, ['verified', 'approved', 'auto_verified']))->count(),
            'reclean' => $groupedInspections->filter(fn($g) => $g->verification_status === 'reclean')->count(),
            'total' => $groupedInspections->count(),
        ];

        // 4. Status Tab Filtering (We already did it conceptually, now apply to the actual list)
        $groupedInspections = $tabFilteredInspections->filter(function($g) use ($filterType) {
            if ($filterType === 'machine') {
                return in_array($g->type, ['machine', 'area']);
            }
            return $g->type === $filterType;
        });

        // 5. Manual Pagination (20 items per page)
        $perPage = 20;
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $groupedInspections->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $paginatedInspections = new \Illuminate\Pagination\LengthAwarePaginator($currentItems, $groupedInspections->count(), $perPage, $currentPage, [
            'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);

        return view('inspections.verification', [
            'groupedInspections' => $paginatedInspections,
            'date' => $dateStr,
            'activeTab' => $activeTab,
            'counts' => $counts,
            'filterType' => $filterType,
            'typeCounts' => $typeCounts
        ]);
    }

    /**
     * Verify (Supervisor Action)
     */
    public function verify(Request $request)
    {
        \Log::info('verify: started');
        if (!Auth::user()->can('verify')) {
            abort(403, 'Unauthorized. Requires Supervisor verify permission.');
        }

        // E-Signature check relaxed per user request
        // if (empty(Auth::user()->signature_path)) {
        //     return back()->with('error', 'กรุณาตั้งค่าลายเซ็นต์อิเล็กทรอนิกส์ในหน้าโปรไฟล์ก่อนทำการตรวจสอบ/อนุมัติ (E-Signature Required)');
        // }

        \Log::info('verify: auth passed');
        
        // Handle JSON encoded IDs to bypass max_input_vars
        if (is_string($request->ids)) {
            $request->merge(['ids' => json_decode($request->ids, true)]);
        }

        $request->validate([
            'ids' => 'required|array',
            'status' => 'required|string|in:verified,reclean', // BUG-011 Fix: Added validation
            'comment' => 'nullable|string'
        ]);

        \Log::info('verify: validation passed');
        $ids = $request->ids;
        $status = $request->status; // 'verified' or 'reclean'
        $comment = $request->input('comment');

        $logs = InspectionLog::with('session')->whereIn('id', $ids)->get();
        if ($logs->isEmpty()) {
            return back()->with('error', 'ไม่พบรายการที่เลือก');
        }

        \Log::info('verify: logs fetched');
        if ($logs->contains(fn ($log) => $log->session?->isLocked())) {
            return back()->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถตรวจสอบ/แก้ไขได้ (Session Locked)');
        }

        \Log::info('verify: locking check passed');

        \Log::info('Inspection Verification (Supervisor)', [
            'ids' => $ids,
            'status' => $status,
            'verifier_id' => Auth::id(),
            'status_type' => gettype($status),
            'status_len' => strlen($status)
        ]);

        // Supervisor can verify logs
        if ($status == 'reclean') {
            // Smart Re-clean: Only items that FAILED are sent for re-clean
            // Items that PASSED are auto-verified
            
            // 1. Handle Failed Items (Set to 'reclean')
            $failedLogs = InspectionLog::whereIn('id', $ids)->where('result', 'fail')->get();
            $failedIds = $failedLogs->pluck('id')->toArray();

            if (!empty($failedIds)) {
                InspectionLog::whereIn('id', $failedIds)->update([
                    'verified_at' => now(),
                    'verifier_id' => Auth::id(),
                    'verification_status' => 'reclean',
                    'verification_comment' => $comment
                ]);

                // Create or Update Corrective Action so it shows up in Manager's Issues dashboard
                foreach ($failedLogs as $fLog) {
                    \Log::info('verify: creating/updating corrective action for log ' . $fLog->id);
                    $action = \App\Models\CorrectiveAction::updateOrCreate([
                        'inspection_log_id' => $fLog->id,
                    ], [
                        'status' => 'open', // Re-open the CAR if it was resolved/verified
                        'escalated_by' => Auth::id(),
                        'root_cause' => $comment ?? 'สั่งแก้ไข/ทำความสะอาดใหม่',
                    ]);

                    // Generate AI Tags if missing (e.g. created by mobile app earlier)
                    if (empty($action->ai_tags)) {
                        $action->ai_tags = \App\Services\AIService::getTagsFromFinding($fLog->correction_action ?? $comment ?? '');
                        $action->save();
                    }
                }

                // Notify Department Manager (Only about the failed items)
                try {
                    $session = $failedLogs->first()->session;
                    $departmentId = $session->department_id;

                    // Find Manager and Supervisor (Level >= 4 or role manager/supervisor) in that department
                    $managers = \App\Models\User::where('department_id', $departmentId)
                                ->where(function($q) {
                                    $q->whereIn('role', ['supervisor', 'manager'])
                                      ->orWhere('level', '>=', 4);
                                })
                                ->get();

                    $emails = [];
                    foreach ($managers as $manager) {
                        $manager->notify(new \App\Notifications\NewCARNotification($action ?? new \App\Models\CorrectiveAction())); // In verify, action is created in the loop.
                        if ($manager->email) {
                            $emails[] = $manager->email;
                        }
                    }

                    if (count($emails) > 0) {
                        \Illuminate\Support\Facades\Mail::to($emails)
                            ->send(new \App\Mail\OrderRecleanNotification($session, $failedLogs, $comment));
                    }
                } catch (\Throwable $e) {
                    \Log::error('Failed to send Re-clean notification: ' . $e->getMessage());
                }
            }

            // 2. Handle Passed Items (Set to 'verified')
            // If the supervisor clicked "Order Re-clean", they likely accept the Passed items effectively, 
            // or at least we don't want to make the user re-do them.
            $passedIds = InspectionLog::whereIn('id', $ids)->where('result', 'pass')->pluck('id')->toArray();
            
            if (!empty($passedIds)) {
                InspectionLog::whereIn('id', $passedIds)->update([
                    'verified_at' => now(),
                    'verifier_id' => Auth::id(), 
                    'verification_status' => 'verified',
                    'verification_comment' => null // No comment needed for passed items
                ]);
            }

        } else {
            \Log::info('verify: processing verified');
            // Normal Verify: Mark all provided IDs as Verified
            InspectionLog::whereIn('id', $ids)->update([
                'verified_at' => now(),
                'verifier_id' => Auth::id(), 
                'verification_status' => $status,
                'verification_comment' => $comment
            ]);

            \Log::info('verify: log updated');
            // Loop Engineering: When QA verifies, we trigger Manager Approval
            if ($status === 'verified') {
                $cars = \App\Models\CorrectiveAction::whereIn('inspection_log_id', $ids)
                    ->whereIn('status', ['open', 'assigned', 'resolved'])
                    ->get();
                
                \Log::info('verify: cars fetched ' . $cars->count());
                foreach ($cars as $car) {
                    $car->update([
                        'status' => 'verified',
                    ]);
                    $car->startApprovalFlow();
                }
            }
        }

        \Log::info('verify: ready for email');
        // 3. Notify QA Manager if this was a normal verification
        if ($status !== 'reclean') {
            try {
                $session = $logs->first()->session;
                
                // Check if all logs in this session are now verified/reclean/approved 
                // i.e., no pending logs left.
                $pendingLogsCount = \App\Models\InspectionLog::where('session_id', $session->id)
                    ->whereNull('verification_status')
                    ->count();

                // If no pending logs left, the supervisor has finished verifying this session.
                if ($pendingLogsCount === 0) {
                    $qaManagers = \App\Models\User::where('level', '>=', 5)->get()->filter(function($u) {
                        return $u->isQA();
                    });

                    foreach ($qaManagers as $manager) {
                        if ($manager->email) {
                            \Illuminate\Support\Facades\Mail::to($manager->email)
                                ->send(new \App\Mail\InspectionVerified($session, Auth::user()));
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('Failed to send Verification email to QA Manager: ' . $e->getMessage());
            }
        }

        \Log::info('verify: completed, returning back');
        $message = ($status === 'reclean') ? 'ส่งกลับแก้ไข (เฉพาะรายการที่ไม่ผ่าน) เรียบร้อย (Sent for Re-clean)' : 'ตรวจสอบเรียบร้อย (Verified)';

        return back()->with('success', $message);
    }

    /**
     * Gap 1: Reject (Supervisor Action) - ตีกลับให้ Staff แก้ไข
     */
    public function reject(Request $request)
    {
        if (!Auth::user()->can('verify')) {
            abort(403, 'Unauthorized. Requires Supervisor verify permission.');
        }

        // Handle JSON encoded IDs to bypass max_input_vars
        if (is_string($request->ids)) {
            $request->merge(['ids' => json_decode($request->ids, true)]);
        }

        $request->validate([
            'ids' => 'required|array',
            'comment' => 'required|string|max:500', // บังคับใส่เหตุผล
        ]);

        $logs = InspectionLog::with('session')->whereIn('id', $request->ids)->get();
        if ($logs->isEmpty()) {
            return redirect()->route('inspection.verification', ['tab' => 'pending'])->with('error', 'ไม่พบรายการที่เลือก');
        }

        if ($logs->contains(fn ($log) => $log->session?->isLocked())) {
            return redirect()->route('inspection.verification', ['tab' => 'pending'])->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถตีกลับได้ (Session Locked)');
        }

        // Update logs to rejected status
        InspectionLog::whereIn('id', $logs->pluck('id'))->update([
            'verified_at' => now(),
            'verifier_id' => Auth::id(),
            'verification_status' => 'rejected',
            'verification_comment' => $request->comment,
        ]);

        // Reset ALL affected sessions to allow editing (not just the first)
        $affectedSessions = $logs->pluck('session')->filter()->unique('id');
        foreach ($affectedSessions as $affectedSession) {
            if (!$affectedSession->isLocked()) {
                $affectedSession->update(['status' => 'in_progress']);
            }
        }

        // Send Notification to Inspector(s)
        $log = $logs->first();
        $inspector = $log?->session?->inspector;
        if ($inspector) {
             $inspector->notify(new \App\Notifications\InspectionRejectedNotification($log->session, $request->comment));
        }

        // Bug Fix: Return success with alert
        return back()->with('success', 'ตีกลับรายการให้แก้ไขเรียบร้อยแล้ว (Rejected)');
    }

    /**
     * Gap 3: Acknowledge (Department Head Action)
     */
    public function acknowledge(Request $request)
    {
        if (!Auth::user()->can('acknowledge')) {
            abort(403, 'Unauthorized. Requires acknowledge permission.');
        }

        $request->validate([
            'ids' => 'required|array',
        ]);

        $user = Auth::user();

        // Admin can acknowledge any department; Dept Head scoped via session's department
        if ($user->isAdmin()) {
            $logs = InspectionLog::whereIn('id', $request->ids)->get();
        } else {
            $logs = InspectionLog::with('session')->whereIn('id', $request->ids)
                ->where(function ($q) use ($user) {
                    $q->whereHas('employee', fn($eq) => $eq->where('department_id', $user->department_id))
                      ->orWhereHas('session', fn($sq) => $sq->where('department_id', $user->department_id));
                })
                ->get();
        }

        if ($logs->isEmpty()) {
            return back()->with('error', 'ไม่พบรายการในแผนกของคุณ (No items found in your department)');
        }

        InspectionLog::whereIn('id', $logs->pluck('id'))->update([
            'acknowledged_by' => $user->id,
            'acknowledged_at' => now(),
        ]);

        return back()->with('success', 'รับทราบเรียบร้อย (Acknowledged) - Closed Loop');
    }
    
    /**
     * Approve (Manager Action)
     * Gap 2: Lock session after approval
     * Gap 4: Send Line Notify to affected Dept Heads
     */
    public function managerApprove(Request $request)
    {
        if (!Auth::user()->can('approve')) {
            abort(403, 'Unauthorized. Requires Manager approve permission.');
        }

        // E-Signature check relaxed per user request
        // if (empty(Auth::user()->signature_path)) {
        //     return back()->with('error', 'กรุณาตั้งค่าลายเซ็นต์อิเล็กทรอนิกส์ในหน้าโปรไฟล์ก่อนทำการอนุมัติ (E-Signature Required)');
        // }

        // Handle JSON encoded IDs to bypass max_input_vars
        if (is_string($request->ids)) {
            $request->merge(['ids' => json_decode($request->ids, true)]);
        }

        $request->validate([
            'ids' => 'required|array',
        ]);

        $user = Auth::user();

        // Department scope: non-admin managers can only approve their own department
        $logsQuery = InspectionLog::with('session')->whereIn('id', $request->ids);
        if (!$user->isAdmin() && !$user->hasGlobalVisibility()) {
            $logsQuery->whereHas('session', fn($q) => $q->where('department_id', $user->department_id));
        }
        $approvedLogs = $logsQuery->get();

        if ($approvedLogs->isEmpty()) {
            return back()->with('error', 'ไม่พบรายการในแผนกของคุณ (No items found in your department)');
        }

        if ($approvedLogs->contains(fn ($log) => $log->session?->isLocked())) {
            return back()->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถอนุมัติเพิ่มได้ (Session Locked)');
        }

        InspectionLog::whereIn('id', $approvedLogs->pluck('id'))->update([
            'verification_status' => 'approved',
            'verified_at' => now(), // Treat approval as a stamp
            'verifier_id' => Auth::id(),
        ]);

        // Loop Engineering: Close the associated CorrectiveActions and approve their requests
        $cars = \App\Models\CorrectiveAction::whereIn('inspection_log_id', $approvedLogs->pluck('id'))
            ->whereIn('status', ['verified', 'open', 'assigned', 'resolved'])
            ->get();
        
        foreach ($cars as $car) {
            $car->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            // If there's a pending approval request, mark it as approved
            $pendingRequest = $car->approvals()->where('status', 'pending')->first();
            if ($pendingRequest) {
                $pendingRequest->update(['status' => 'approved']);
            }
        }

        // Lock session only when every log in the session is approved
        $sessionIds = $approvedLogs->pluck('session_id')->unique()->filter();
        $fullyLockedSessions = 0;

        foreach ($sessionIds as $sessionId) {
            $totalLogs = InspectionLog::where('session_id', $sessionId)->count();
            if ($totalLogs === 0) {
                continue;
            }

            $approvedCount = InspectionLog::where('session_id', $sessionId)
                ->whereIn('verification_status', ['approved', 'auto_verified'])
                ->count();

            if ($approvedCount === $totalLogs) {
                InspectionSession::where('id', $sessionId)->update([
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'is_locked' => true,
                ]);
                $fullyLockedSessions++;
            }
        }

        $message = $fullyLockedSessions > 0
            ? 'อนุมัติเรียบร้อย และล็อคเซสชันที่ครบทุกรายการแล้ว (Approved & Locked)'
            : 'อนุมัติรายการที่เลือกเรียบร้อย (ยังมีรายการอื่นในเซสชันที่รออนุมัติ)';

        return back()->with('success', $message);
    }

    private function authorizeSessionOwner(InspectionSession $session): void
    {
        if ($session->inspector_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงเซสชันนี้ (Unauthorized: not session owner)');
        }
    }

    private function getAutoShift()
    {
        return \App\Models\Shift::detectCurrent();
    }
}