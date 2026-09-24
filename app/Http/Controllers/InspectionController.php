<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use App\Models\Machine;
use App\Support\VerificationGroups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image; // Laravel 11/Intervention 3

class InspectionController extends Controller
{
    protected $inspectionService;
    protected $scheduleService;
    protected VerificationGroups $verificationGroups;

    public function __construct(
        \App\Services\InspectionService $inspectionService,
        \App\Services\ScheduleService $scheduleService,
        VerificationGroups $verificationGroups
    ) {
        $this->inspectionService = $inspectionService;
        $this->scheduleService = $scheduleService;
        $this->verificationGroups = $verificationGroups;
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
        // Diagnostic instrumentation for the /dashboard slowness report.
        // Turn on by setting DASHBOARD_DEBUG_QUERIES=true in .env. When enabled
        // this logs every SQL query fired during home() with its wall-clock time
        // plus a checkpoint per section to storage/logs/dashboard-debug.log so
        // we can see WHICH block is slow before proposing a fix. Zero cost when
        // the flag is off (env() read + one boolean check).
        $debug = env('DASHBOARD_DEBUG_QUERIES', false);
        $debugChannel = null;
        $debugStart = 0.0;
        $debugMark = null;
        if ($debug) {
            $debugStart = microtime(true);
            $debugChannel = \Illuminate\Support\Facades\Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/dashboard-debug.log'),
            ]);
            $debugChannel->info('--- dashboard render start ---', ['user_id' => Auth::id()]);
            \Illuminate\Support\Facades\DB::listen(function ($q) use ($debugChannel) {
                $debugChannel->info(sprintf('[%.1fms] %s', $q->time, $q->sql), [
                    'bindings' => $q->bindings,
                ]);
            });
            $debugMark = function (string $label) use ($debugChannel, &$debugStart) {
                $elapsed = (microtime(true) - $debugStart) * 1000;
                $debugChannel->info(sprintf('[+%.0fms] checkpoint: %s', $elapsed, $label));
            };
        }

        // Opportunistically auto-close stale sessions on dashboard load (throttled, non-blocking).
        $this->autoCloseHeartbeat();
        if ($debugMark) $debugMark('after autoCloseHeartbeat');

        $today = now()->toDateString();
        $user = Auth::user();

        // --- SCOPE DEFINITION (Role Matrix Compliance) ---
        // Global scope users (QA, Admin, Executive) see all departments
        // Isolated scope users see only their own department
        // scopedDepartmentId() returns 0 for a departmentless user, which matches no
        // row. Reading department_id directly gave null, and the `if ($scopeDeptId)`
        // below then skipped the filter entirely — so a user with no department saw
        // every department's data instead of none.
        $scopeDeptId = null;
        if (!$user->hasGlobalVisibility()) {
            $scopeDeptId = $user->scopedDepartmentId();
        }

        $applyScope = function($query) use ($scopeDeptId) {
            if ($scopeDeptId !== null) {
                // Filter logs where it is an Employee inspection AND they belong to Dept
                // OR it is an Area/Machine inspection AND the session belongs to Dept
                $query->where(function($q) use ($scopeDeptId) {
                    $q->whereHas('employee', function($subQ) use ($scopeDeptId) {
                        $subQ->where('department_id', $scopeDeptId);
                    })->orWhere(function($q2) use ($scopeDeptId) {
                        $q2->whereNull('employee_id')
                           ->whereHas('session', function($sq) use ($scopeDeptId) {
                               $sq->where('department_id', $scopeDeptId);
                           });
                    });
                });
            }
            return $query;
        };

        // DB-level grouping key for unique inspection entities
        $dailyStatsCacheKey = "dashboard_daily_stats_{$today}_" . ($scopeDeptId ?? 'all');
        $dailyStats = \Illuminate\Support\Facades\Cache::remember($dailyStatsCacheKey, 60, function () use ($today, $applyScope, $scopeDeptId) {
            // CONCAT is MySQL's; SQLite spells it ||, and there is no portable
            // form. Hard-coding CONCAT made this whole method unrunnable under
            // the test database, so the dashboard had no coverage at all.
            $cat = fn (array $parts) => \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite'
                ? '(' . implode(' || ', $parts) . ')'
                : 'CONCAT(' . implode(', ', $parts) . ')';

            $entityExpr = $cat([
                'session_id',
                "'_'",
                'CASE WHEN machine_id IS NOT NULL THEN ' . $cat(["'m'", 'machine_id'])
                    . ' WHEN employee_id IS NOT NULL THEN ' . $cat(["'e'", 'employee_id'])
                    . ' ELSE ' . $cat(["'l'", 'COALESCE(location_id, 0)']) . ' END',
            ]);

            $todayBase = InspectionLog::whereDate('inspected_at', $today);
            $applyScope($todayBase);

            $row = (clone $todayBase)->selectRaw("COUNT(DISTINCT {$entityExpr}) as cnt")->first();
            $inspectionsToday = $row ? (int) $row->cnt : 0;

            $pendingBase = InspectionLog::whereNull('verification_status')
                                        ->whereNotIn('result', ['no_production', 'absent']);
            $applyScope($pendingBase);
            $row = $pendingBase->selectRaw("COUNT(DISTINCT {$entityExpr}) as cnt")->first();
            $pendingVerificationCount = $row ? (int) $row->cnt : 0;

            $recleanBase = InspectionLog::where('verification_status', 'reclean');
            $applyScope($recleanBase);
            $row = $recleanBase->selectRaw("COUNT(DISTINCT {$entityExpr}) as cnt")->first();
            $recleanCount = $row ? (int) $row->cnt : 0;

            $totalPass = (clone $todayBase)->where('result', 'pass')->count();
            $totalFail = (clone $todayBase)->where('result', 'fail')->count();
            $passRate = $this->passRate($totalPass, $totalFail);

            // Calculate Monthly Pass Rate for the big Hygiene Index dial
            $monthlyBase = InspectionLog::where('inspected_at', '>=', now()->startOfMonth());
            $applyScope($monthlyBase);
            $monthlyTotalPass = (clone $monthlyBase)->where('result', 'pass')->count();
            $monthlyTotalFail = (clone $monthlyBase)->where('result', 'fail')->count();
            $monthlyPassRate = $this->passRate($monthlyTotalPass, $monthlyTotalFail);

            // Counted the way the verification page counts, so the card and the
            // tab it links to show the same number: one per group, where every
            // log in the group is verified and none of it is approved yet.
            $awaitingRows = $this->verificationGroupSummary(
                $this->verificationLogQuery(null, null, $scopeDeptId)
            )->where('group_status', 'verified');

            $awaitingApprovalCount = $awaitingRows->count();
            // Split the way the verification page splits, so each half of the
            // card can link at the category its number actually describes.
            $awaitingApprovalPersonCount = $awaitingRows->filter(fn ($row) => $row->is_person)->count();
            $awaitingApprovalAreaCount = $awaitingApprovalCount - $awaitingApprovalPersonCount;

            return compact(
                'inspectionsToday',
                'pendingVerificationCount',
                'awaitingApprovalCount',
                'awaitingApprovalPersonCount',
                'awaitingApprovalAreaCount',
                'recleanCount',
                'passRate',
                'monthlyPassRate'
            );
        });

        $inspectionsToday = $dailyStats['inspectionsToday'];
        $pendingVerificationCount = $dailyStats['pendingVerificationCount'];
        $awaitingApprovalCount = $dailyStats['awaitingApprovalCount'];
        $awaitingApprovalPersonCount = $dailyStats['awaitingApprovalPersonCount'];
        $awaitingApprovalAreaCount = $dailyStats['awaitingApprovalAreaCount'];
        $recleanCount = $dailyStats['recleanCount'];
        $passRate = $dailyStats['passRate'];
        $monthlyPassRate = $dailyStats['monthlyPassRate'];
        if ($debugMark) $debugMark('§1-4 dailyStats(cached)');

        // 5. Recent Activity
        $recentLogsCacheKey = "dashboard_recent_logs_" . ($scopeDeptId ?? 'all');
        $recentLogs = \Illuminate\Support\Facades\Cache::remember($recentLogsCacheKey, 60, function () use ($applyScope) {
        $recentQuery = InspectionLog::with(['employee', 'location', 'machine', 'checkpoint', 'session.inspector', 'correctiveAction'])
            ->orderBy('id', 'desc')
            ->take(150); // Increased take to ensure enough unique sessions
            
            $logs = $applyScope($recentQuery)->get();
            
            return $logs->groupBy(function($log) {
                return $log->session_id . '-' . ($log->employee_id ?? $log->machine_id ?? $log->location_id);
            })->map(function($groupLogs) {
                // Loop Engineering: Prioritize 'fail' log for display if any exists in the session
                $failLog = $groupLogs->firstWhere('result', 'fail');
                return $failLog ? $failLog : $groupLogs->first();
            })->take(5)->values();
        });
        if ($debugMark) $debugMark('§5 recentLogs(cached)');

        // 6. CAR Statistics (Executive View) - Cached 5 minutes
        $startOfMonth = now()->startOfMonth();
        // These CAR aggregates had no department scope at all, and the cache key had no
        // department component — so /dashboard (middleware `auth` only, and the view
        // carries no role guard) showed every user a per-department CAR breakdown of
        // the whole company, defeating visibility_type = 'isolated'.
        $scopeCars = function ($query) use ($scopeDeptId) {
            if ($scopeDeptId !== null) {
                $query->whereHas('log.session', fn($q) => $q->where('department_id', $scopeDeptId));
            }

            return $query;
        };

        $carStatsCacheKey = "dashboard_car_stats_" . $startOfMonth->format('Y_m') . '_' . ($scopeDeptId ?? 'all');
        $carStats = \Illuminate\Support\Facades\Cache::remember($carStatsCacheKey, 300, function () use ($startOfMonth, $scopeDeptId, $scopeCars) {
            $totalCars = $scopeCars(\App\Models\CorrectiveAction::where('created_at', '>=', $startOfMonth))->count();

            $carsByDept = \Illuminate\Support\Facades\DB::table('corrective_actions')
                ->join('inspection_logs', 'corrective_actions.inspection_log_id', '=', 'inspection_logs.id')
                ->join('inspection_sessions', 'inspection_logs.session_id', '=', 'inspection_sessions.id')
                ->leftJoin('departments', 'inspection_sessions.department_id', '=', 'departments.id')
                ->where('corrective_actions.created_at', '>=', $startOfMonth)
                ->when($scopeDeptId !== null, fn($q) => $q->where('inspection_sessions.department_id', $scopeDeptId))
                ->selectRaw("COALESCE(departments.dept_name, 'Unknown') as dept_name, COUNT(corrective_actions.id) as count")
                ->groupBy('dept_name')
                ->pluck('count', 'dept_name');

            $onTimeCount = $scopeCars(\App\Models\CorrectiveAction::where('created_at', '>=', $startOfMonth))
                ->whereIn('status', ['resolved', 'closed', 'verified'])
                ->where(function($q) {
                    $q->whereColumn('resolved_at', '<=', 'due_date')
                      ->orWhereNull('due_date');
                })->count();

            $overdueCount = $scopeCars(\App\Models\CorrectiveAction::where('created_at', '>=', $startOfMonth))
                ->whereNotNull('due_date')
                ->where(function($q) {
                    $q->where(function($q2) {
                        $q2->where('due_date', '<', now())
                           ->whereNotIn('status', ['resolved', 'closed', 'verified']);
                    })->orWhere(function($q3) {
                        $q3->whereIn('status', ['resolved', 'closed', 'verified'])
                           ->whereColumn('resolved_at', '>', 'due_date');
                    });
                })->count();

            $inProgressCount = max(0, $totalCars - $onTimeCount - $overdueCount);

            $aiTagsRows = $scopeCars(\App\Models\CorrectiveAction::where('created_at', '>=', $startOfMonth))
                ->whereNotNull('ai_tags')
                ->pluck('ai_tags');
                
            $aiTagCounts = $aiTagsRows->flatten()->filter()->countBy()->sortDesc()->take(5);

            return compact('totalCars', 'carsByDept', 'onTimeCount', 'overdueCount', 'inProgressCount', 'aiTagCounts');
        });

        $totalCars = $carStats['totalCars'];
        $carsByDept = $carStats['carsByDept'];
        $onTimeCount = $carStats['onTimeCount'];
        $overdueCount = $carStats['overdueCount'];
        $inProgressCount = $carStats['inProgressCount'];
        $aiTagCounts = $carStats['aiTagCounts'];
        if ($debugMark) $debugMark('§6 CAR stats (cached)');

        // 7. Today's Scheduled Inspections
        $scheduleDeptId = $user->isAdmin() || $user->hasGlobalVisibility() ? null : $user->department_id;
        $scheduledTasks = collect();
        if ($scheduleDeptId || $user->isAdmin() || $user->hasGlobalVisibility()) {
            $scheduleCacheKey = "dashboard_scheduled_{$today}_" . ($scheduleDeptId ?? 'all');
            $scheduledTasks = \Illuminate\Support\Facades\Cache::remember($scheduleCacheKey, 300, function () use ($scheduleDeptId, $today) {
                $todaysSchedules = $this->scheduleService->getDailySchedules($scheduleDeptId, $today);
                $todaysSchedules->load('department');
                return $this->scheduleService->getComplianceStatus($todaysSchedules, $today);
            });
        }
        if ($debugMark) $debugMark('§7 scheduledTasks(cached)');

        // 8. Active Sessions (For Admins)
        $activeSessions = collect();
        if ($user->isAdmin() || $user->hasGlobalVisibility()) {
            $activeSessionsCacheKey = "dashboard_active_sessions_{$today}";
            $activeSessions = \Illuminate\Support\Facades\Cache::remember($activeSessionsCacheKey, 60, function () use ($today) {
                return InspectionSession::with(['inspector', 'department'])
                    ->whereDate('inspection_date', $today)
                    ->where('status', 'in_progress')
                    ->orderBy('created_at', 'desc')
                    ->get();
            });
        }
        if ($debugMark) $debugMark('§8 activeSessions(cached)');

        // 9. Today's Random Audits (For Supervisors/Managers)
        $todayAudits = collect();
        if ($user->level >= 4 || $user->isAdmin()) { // Supervisor (level 4) and above
            $isAdmin = $user->isAdmin();
            $hasGlobal = $user->hasGlobalVisibility();
            $deptId = $user->department_id;
            
            $auditDeptId = (!$isAdmin && !$hasGlobal) ? $deptId : 'all';
            $auditsCacheKey = "dashboard_random_audits_{$today}_{$auditDeptId}";
            
            $todayAudits = \Illuminate\Support\Facades\Cache::remember($auditsCacheKey, 60, function () use ($today, $isAdmin, $hasGlobal, $deptId) {
                $auditQuery = \App\Models\RandomAudit::with('department')
                    ->where('audit_date', $today)
                    ->where('status', 'pending');
                
                if (!$isAdmin && !$hasGlobal) {
                    $auditQuery->where('department_id', $deptId);
                }
                
                return $auditQuery->get();
            });
        }
        if ($debugMark) $debugMark('§9 todayAudits(cached)');
        if ($debugChannel) {
            $total = (microtime(true) - $debugStart) * 1000;
            $debugChannel->info(sprintf('--- dashboard render end: %.0fms total ---', $total));
        }

        return view('dashboard', compact(
            'inspectionsToday',
            'pendingVerificationCount',
            'awaitingApprovalCount',
            'awaitingApprovalPersonCount',
            'awaitingApprovalAreaCount',
            'recleanCount',
            'passRate',
            'monthlyPassRate',
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

        // 2. Fetch Pending Re-cleans (Scoped by department if needed)
        // scopedDepartmentId() is the fail-closed form: 0 for a departmentless user.
        $scopeDeptId = null;
        if (!$user->hasGlobalVisibility()) {
            $scopeDeptId = $user->scopedDepartmentId();
        }

        $pendingReCleansQuery = InspectionLog::with(['employee', 'location', 'machine', 'checkpoint', 'session.department', 'session.inspector', 'verifier'])
            ->where('verification_status', 'reclean')
            ->whereDoesntHave('rechecks')
            ->whereHas('session', function($q) use ($type) {
                $q->where('type', $type);
            });
            
        if ($scopeDeptId !== null) {
            $pendingReCleansQuery->where(function($q) use ($scopeDeptId) {
                $q->whereHas('employee', function($subQ) use ($scopeDeptId) {
                    $subQ->where('department_id', $scopeDeptId);
                })->orWhere(function($q2) use ($scopeDeptId) {
                    $q2->whereNull('employee_id')
                       ->whereHas('session', function($sq) use ($scopeDeptId) {
                           $sq->where('department_id', $scopeDeptId);
                       });
                });
            });
        }

        $pendingReCleans = $pendingReCleansQuery->orderBy('verified_at', 'desc')
            ->get()
            ->groupBy(function($log) {
                if ($log->employee_id) return $log->session_id . '_e' . $log->employee_id;
                if ($log->machine_id) return $log->session_id . '_m' . $log->machine_id;
                return $log->session_id . '_l' . ($log->location_id ?? 0);
            });

        // 3. Current Session Status & Remaining Items Logic
        $currentAutoShift = $this->getAutoShift();
        $currentShiftModel = \App\Models\Shift::detectCurrentShift();
        // Loop Engineering Fix: Cross-Midnight Bug. Hours 0-5 belong to yesterday's shift.
        $today = now()->hour < 6 ? now()->subDay()->toDateString() : now()->toDateString();
        $currentSession = InspectionSession::where('inspector_id', Auth::id())
            ->whereDate('inspection_date', $today)
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
                // Find Target Employees in the current shift.
                // Resolved through the session itself so a picked shift card ('custom_11')
                // counts. Matching shift_name here could never hit one, which left
                // $totalTargets at 0 — and a zero total made the block below list every
                // uninspected employee in the department as "remaining".
                $targetEmployeeIds = $currentSession->getSessionTargetEmployees()->pluck('id')->toArray();
                $totalTargets = count($targetEmployeeIds);
                
                // Loop Engineering Fix: Count ANYONE inspected in this session (even if they swapped shifts)
                $inspectedCount = InspectionLog::where('session_id', $currentSession->id)
                    ->distinct('employee_id')
                    ->count('employee_id');

                $remainingCount = max(0, $totalTargets - $inspectedCount);
                
                // This list populates the Bulk Pass confirmation modal, so it has to name
                // exactly who that button would pass — the round's own targets minus anyone
                // already logged. Listing every uninspected employee in the department showed
                // the inspector a cross-shift roster before confirming a sweep that
                // bulkPassRemaining() would scope differently.
                if ($remainingCount > 0) {
                    $inspectedIds = InspectionLog::where('session_id', $currentSession->id)
                        ->pluck('employee_id')->toArray();
                    $remainingList = Employee::whereIn('id', $targetEmployeeIds)
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
        $activeOtherSessionsQuery = InspectionSession::with(['inspector', 'department'])
            ->whereDate('inspection_date', $today)
            ->where('type', $type)
            ->where('status', 'in_progress');
            // ->where('inspector_id', '!=', Auth::id()) 
            
        if ($scopeDeptId !== null) {
            $activeOtherSessionsQuery->where('department_id', $scopeDeptId);
        }
            
        $activeOtherSessions = $activeOtherSessionsQuery->get();

        return view('inspections.dashboard', compact(
            'departments', 
            'pendingReCleans', 
            'type', 
            'currentSession', 
            'currentAutoShift', // Pass to view
            'currentShiftModel', // Actual Shift model from DB
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
            $shifts = $request->query('shifts', [$shift]);
            if (is_array($shifts)) {
                sort($shifts); // ensure consistent order
                $shiftStr = implode(',', $shifts);
            } else {
                $shiftStr = $shift;
                $shifts = [$shift];
            }
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
                    $q->where('is_active', true)
                      ->withCount(['checkpoints' => function($q2) {
                          $q2->where('is_active', true);
                      }]);
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
                            ->whereDate('inspection_date', $today)
                            ->where('shift', $shiftStr)
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
                            ->whereDate('inspection_date', $today)
                            ->where('shift', $shiftStr)
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
                    // One card per shift row, always keyed by id. Grouping rows under a generic
                    // key ('morning') made the card stand for EVERY shift of that type, so a
                    // round started from it swept groups the inspector never chose — and the
                    // start guard rejects generic keys outright now.
                    $dbShifts = \App\Models\Shift::all();
                    $shiftLabels = [];
                    $shiftMappings = [];
                    foreach ($dbShifts as $s) {
                        $shiftKey = 'custom_' . $s->id;
                        $shiftLabels[$shiftKey] = $s->shift_name;
                        $shiftMappings[$shiftKey] = [$s->id];
                    }

                    // Get all today's sessions for this department
                    $allTodaySessions = InspectionSession::where('department_id', $deptModel->id)
                                        ->whereDate('inspection_date', $today)
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

                    // Employees whose only records today are absences were not inspected —
                    // they did not come to work. They still count as "handled" so the shift
                    // can close, but the card reports them separately instead of folding
                    // them into the inspected figure.
                    $allAbsentIds = $allTodayLogs->groupBy('employee_id')
                        ->filter(fn($logs) => $logs->every(fn($l) => $l->result === 'absent'))
                        ->keys()
                        ->all();

                    $allEmployees = \App\Models\Employee::where('department_id', $deptModel->id)
                                        ->where('is_active', true)
                                        ->get();
                    $totalDeptEmployees = $allEmployees->count();

                    $schedules = \App\Models\EmployeeSchedule::where('date', now()->startOfDay())
                        ->whereIn('employee_id', $allEmployees->pluck('id'))
                        ->get()
                        ->keyBy('employee_id');

                    foreach ($shiftLabels as $shiftKey => $shiftLabel) {
                        $shiftIds = $shiftMappings[$shiftKey] ?? [];
                        
                        $empCount = 0;
                        $shiftEmployeeIds = [];
                        
                        if (!empty($shiftIds)) {
                            // Filter employees matching this shift using Schedule first, then Default Shift
                            $filteredEmployees = $allEmployees->filter(function($emp) use ($schedules, $shiftIds) {
                                $sch = $schedules->get($emp->id);
                                if ($sch) {
                                    return in_array($sch->shift_id, $shiftIds) && !$sch->is_day_off;
                                }
                                return in_array($emp->shift_id, $shiftIds);
                            });
                            
                            $shiftEmployeeIds = $filteredEmployees->pluck('id')->toArray();
                            $empCount = count($shiftEmployeeIds);
                        }
                        
                        // Count unique inspected employees for this shift
                        // Notice: inspectedByShift might have comma-separated keys now, so this might not match exactly.
                        // Actually, the sessions already created will have the comma-separated shift.
                        // So $sessShift could be "morning,afternoon". 
                        // To count properly per individual shift card, we can check if the session's shift string contains this $shiftKey.
                        $inspectedInShift = 0;
                        foreach ($inspectedByShift as $sessShiftStr => $empIdsCol) {
                            $sessShiftsArr = explode(',', $sessShiftStr);
                            if (in_array($shiftKey, $sessShiftsArr)) {
                                $inspectedInShift += $empIdsCol->unique()->count();
                            }
                        }
                            
                        $isCurrentShift = in_array($shiftKey, $shifts); // True if it's one of the selected shifts

                        // If we used the fallback for empCount, we should count all inspected employees in this shift's session
                        $inspectedFromThisShift = count(array_intersect($shiftEmployeeIds, $allInspectedIds));
                        if (empty($shiftEmployeeIds) && empty($shiftIds)) { // e.g. for fallback scenarios
                            $inspectedFromThisShift = $inspectedInShift;
                        }

                        // Loop Engineering: Hide shifts that have 0 employees and 0 inspections
                        if ($empCount === 0 && $inspectedFromThisShift === 0) {
                            continue;
                        }

                        // Split the handled figure so the card can say "ตรวจแล้ว 29 คน ·
                        // ไม่มาทำงาน 3 คน" rather than implying 32 people were inspected.
                        $absentFromThisShift = count(array_intersect($shiftEmployeeIds, $allAbsentIds));

                        $shiftCards[] = [
                            'id' => 'shift_' . $shiftKey,
                            'location_name' => $shiftLabel,
                            'employees_count' => $empCount,
                            'inspected_count' => $inspectedFromThisShift,
                            'absent_count' => $absentFromThisShift,
                            'actually_inspected_count' => max(0, $inspectedFromThisShift - $absentFromThisShift),
                            'description' => $isCurrentShift 
                                ? '🎯 กะที่ถูกเลือก' 
                                : 'จำนวนพนักงานที่ตรวจแล้วในกะนี้',
                            'has_checkpoints' => true,
                            'is_current_shift' => $isCurrentShift,
                        ];
                    }
                }

                return response()->json([
                    'success' => true,
                    'locations' => $shiftCards,
                    'shift' => $shiftStr,
                    'session_exists' => ($department !== 'all' ? !!$session : !empty($sessionIds)),
                    'session_status' => $session ? $session->status : null,
                    'session_round' => $session ? $session->round : null,
                    // Drives the "ตรวจต่อ" button: a finished round the supervisor has not
                    // reviewed yet can still be picked back up.
                    'session_can_reopen' => $session ? $this->inspectionService->canReopen($session) : false,
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
                    // For Area / Machine — both share the location list
                    $loc->inspected_count = in_array($loc->id, $inspectedLocationIds) ? 1 : 0;
                    $loc->is_no_production = in_array($loc->id, $noProductionLocationIds);

                    // Legacy field: only area mode used to render the area checkbox based on
                    // this. Machine mode kept it false because it didn't surface area targets
                    // at all. Now that machine mode also offers a "ตรวจพื้นที่ทั่วไป" checkbox,
                    // has_area_checkpoints is the canonical signal for BOTH modes.
                    $loc->has_area_checkpoints = $loc->checkpoints()
                        ->where('type', 'area')
                        ->where('is_active', true)
                        ->exists();
                    // area_inspected / area_no_production mirror the two flags above but with
                    // names the machine-mode selection UI can key on without a mode-check.
                    $loc->area_inspected = $loc->inspected_count > 0;
                    $loc->area_no_production = $loc->is_no_production;

                    // Preserve the old field so any code path still reading has_checkpoints
                    // in area mode keeps working.
                    $loc->has_checkpoints = ($type === 'area')
                        ? $loc->has_area_checkpoints
                        : false;

                    // Machine inspection status
                    if ($loc->machines) {
                        foreach ($loc->machines as $m) {
                            $m->is_inspected = in_array($m->id, $inspectedMachineIds);
                            $m->is_no_production = in_array($m->id, $noProductionMachineIds);
                            // Check machine checkpoints using pre-calculated count
                            $m->has_checkpoints = $m->checkpoints_count > 0;
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
                'session_can_reopen' => $session ? $this->inspectionService->canReopen($session) : false,
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
            'shift' => 'nullable|string'
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

        // Loop Engineering Fix: IDOR Protection on Session Creation
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->hasGlobalVisibility()) {
            if ((int)$deptId !== $user->department_id) {
                return redirect()->route('inspection.dashboard', $type)
                    ->with('error', 'คุณไม่มีสิทธิ์สร้างเซสชันการตรวจสำหรับแผนกอื่น (Unauthorized: Cannot create session for another department)');
            }
        }

        $shiftToSave = $request->input('shift');
        if ($type === 'personnel' && !empty($request->targets)) {
            $shifts = [];
            foreach ($request->targets as $target) {
                if (str_starts_with($target, 'shift:')) {
                    $shifts[] = str_replace('shift:', '', $target);
                }
            }
            if (!empty($shifts)) {
                sort($shifts);
                $shiftToSave = implode(',', $shifts);
            }
        }

        // The inspector picks the shift; the system never guesses one for them. Auto-detecting
        // it stored a generic key that spans every shift of that type, so a single "ผ่านทุกคน
        // ที่เหลือ" swept groups the inspector had not even started on.
        if ($type === 'personnel' && empty($shiftToSave)) {
            return redirect()->route('inspection.dashboard', $type)
                ->with('error', 'กรุณาเลือกกะที่ต้องการตรวจก่อนเริ่มการตรวจ');
        }

        // Fix: Prevent Empty Random Audit Session
        if ($type === 'personnel' && $request->boolean('is_sampling')) {
            $actualShift = $shiftToSave;
            $dummySession = new \App\Models\InspectionSession([
                'department_id' => (int) ($request->department_id ?? $deptId),
                'inspection_date' => now()->hour < 6 ? now()->subDay()->toDateString() : now()->toDateString(),
                'shift' => $actualShift,
            ]);
            
            $targetEmployeesList = $dummySession->getTargetEmployees();
            $previouslyInspectedIds = \App\Models\InspectionLog::whereDate('inspected_at', clone $dummySession->inspection_date)
                ->pluck('employee_id')
                ->unique()
                ->toArray();
                
            $available = $targetEmployeesList->whereNotIn('id', $previouslyInspectedIds)->count();
            if ($available === 0) {
                return redirect()->route('inspection.dashboard', $type)
                    ->with('error', 'ไม่มีพนักงานที่เหลือให้สุ่มตรวจในกะนี้ (No uninspected employees left for Random Audit)');
            }
        }

        try {
            $session = $this->inspectionService->startSession(
                Auth::user(),
                (int) ($request->department_id ?? $deptId),
                $type,
                $request->boolean('force_new_round'),
                $shiftToSave,
                $request->boolean('is_sampling'),
                $request->filled('sample_size') ? (int) $request->input('sample_size') : null
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

            // Resume path: the Resume/"กลับเข้าสู่การตรวจ" button on the dashboard submits
            // only department_id — no targets — because it doesn't re-render the checkbox
            // grid. Two things used to break here:
            //   1. The code tried to recover targets from ->pluck(location_id/machine_id)
            //      of existing logs, so a resume BEFORE any log was saved (fresh session,
            //      just clicked Start then closed the tab) yielded no targets and errored
            //      out with "กรุณาเลือกพื้นที่หรือเครื่องจักร...".
            //   2. Even when logs existed, that recovery only listed targets the inspector
            //      HAD ALREADY inspected — the un-inspected remaining ones (the whole point
            //      of resuming) were lost.
            // Fix: rebuild the target list from the same full-scope query the dashboard
            // uses for its "remaining count", so Resume behaves like clicking "เลือกทั้งหมด"
            // then Start. The bulk view will still mark inspected rows as done.
            if (empty($targetList) && $session) {
                if ($type === 'machine') {
                    $machineIds = \App\Models\Machine::where('is_active', true)
                        ->whereHas('checkpoints', function ($q) {
                            $q->where('is_active', true);
                        })
                        ->pluck('id');
                    foreach ($machineIds as $mid) {
                        $targetList[] = "machine:{$mid}";
                    }
                } elseif ($type === 'area') {
                    $locIds = \App\Models\Location::whereHas('checkpoints', function ($q) {
                        $q->where('type', 'area')->where('is_active', true);
                    })->pluck('id');
                    foreach ($locIds as $lid) {
                        $targetList[] = "loc:{$lid}";
                    }
                }

                if (empty($targetList)) {
                    return redirect()->route('inspection.dashboard', $type)
                        ->with('error', 'ไม่พบพื้นที่หรือเครื่องจักรที่มีจุดตรวจในระบบ กรุณาตั้งค่า Master Data ก่อน');
                }
            }

            // Auto-include area target: for every Location that has at least one machine
            // target AND has area-type checkpoints of its own, add its loc:X target so the
            // room's own inspection (floors, walls, ceilings) can't be silently skipped.
            // Idempotent — skips if loc:X is already in the list. Silent no-op when the
            // Location has no area checkpoints, so this doesn't clutter machine-only rooms.
            if ($type === 'machine' && !empty($targetList)) {
                $selectedLocationIds = collect($targetList)
                    ->filter(fn ($t) => str_starts_with($t, 'machine:'))
                    ->map(function ($t) {
                        $mid = explode(':', $t)[1] ?? null;
                        return $mid ? \App\Models\Machine::whereKey($mid)->value('location_id') : null;
                    })
                    ->filter()
                    ->unique()
                    ->values();

                if ($selectedLocationIds->isNotEmpty()) {
                    $locsWithArea = \App\Models\Location::whereIn('id', $selectedLocationIds)
                        ->whereHas('checkpoints', function ($q) {
                            $q->where('type', 'area')->where('is_active', true);
                        })
                        ->pluck('id');

                    foreach ($locsWithArea as $lid) {
                        $key = "loc:{$lid}";
                        if (!in_array($key, $targetList, true)) {
                            $targetList[] = $key;
                        }
                    }
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

        // Priority 1: Employee-specific checkpoints (employee_checkpoint)
        $checkpoints = $employee->checkpoints()
            ->where('is_active', true)
            ->when($checkpointType, fn($q) => $q->where('type', $checkpointType))
            ->get();

        // Priority 2: Location checkpoints (location_checkpoint)
        if ($checkpoints->isEmpty()) {
            $employeeLocation = $employee->location_id ? $employee->location : null;
            if ($employeeLocation) {
                $checkpoints = $employeeLocation->checkpoints()
                    ->where('is_active', true)
                    ->when($checkpointType, fn($q) => $q->where('type', $checkpointType))
                    ->get();
            }
        }

        // Priority 3: All active checkpoints (fallback)
        if ($checkpoints->isEmpty()) {
            $checkpointQuery = Checkpoint::where('is_active', true);
            if ($checkpointType) {
                $checkpointQuery->where('type', $checkpointType);
            }
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

        // Search filter
        $search = $request->input('q', '');
        if ($search) {
            $baseQuery->where(function($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $allEmployees = $baseQuery->orderBy('location_id')
            ->orderBy('fullname')
            ->get();

        // Fetch schedules for today
        $schedules = \App\Models\EmployeeSchedule::where('date', $session->inspection_date->startOfDay())
            ->whereIn('employee_id', $allEmployees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $resolved = $session->getResolvedShifts();
        $validShiftIds = $resolved['shift_ids'];
        $shiftNames = $resolved['shift_names'];

        $targetEmployeesList = $session->getTargetEmployees();

        // Loop Engineering: Filter out employees who were ALREADY inspected TODAY BEFORE this session started.
        // This ensures Random Audit only picks fresh people, but keeps the list locked because logs created
        // DURING this session won't be filtered out (their created_at is > session->created_at).
        $previouslyInspectedIds = \App\Models\InspectionLog::whereDate('inspected_at', $session->inspection_date)
            ->where('created_at', '<', $session->created_at)
            ->pluck('employee_id')
            ->unique()
            ->toArray();

        $targetEmployeesList = $targetEmployeesList->filter(function($emp) use ($previouslyInspectedIds) {
            return !in_array($emp->id, $previouslyInspectedIds);
        });

        if ($session->is_sampling && $session->sample_size > 0) {
            // Lock the sample to this session. Collection::shuffle() takes NO arguments
            // in Laravel 12 (it delegates to Arr::shuffle, which uses a CSPRNG), and PHP
            // silently discards extra arguments to userland methods — so the previous
            // ->shuffle($session->id) threw the seed away and re-rolled the target list
            // on every page load, losing the inspector's list on any refresh and making
            // the audited sample unreproducible afterwards.
            //
            // A hash of (session id, employee id) gives a stable pseudo-random order:
            // deterministic for a given session, different between sessions, and with no
            // dependence on global RNG state.
            $targetEmployeesList = $targetEmployeesList
                ->sortBy(fn($emp) => md5($session->id . '-' . $emp->id))
                ->values()
                ->take($session->sample_size);
        }
        $targetEmployeeIdsMap = array_flip($targetEmployeesList->pluck('id')->toArray());

        $employees = $allEmployees->filter(function($emp) use ($showAll, $targetEmployeeIdsMap) {
            if ($showAll) return true;
            return isset($targetEmployeeIdsMap[$emp->id]);
        });

        $allEmployeeIds = $employees->pluck('id')->toArray();
        $totalEmployees = count($allEmployeeIds);

        // Get inspected TODAY across ALL sessions for this department
        $todaySessions = InspectionSession::where('department_id', $session->department_id)
            ->whereDate('inspection_date', $session->inspection_date)
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

        // Get failed IDs across all sessions today (or just current)
        $failedIds = $allTodayLogs->where('result', 'fail')->pluck('employee_id')->unique()->toArray();

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

        foreach ($employees as $emp) {
            if (isset($targetEmployeeIdsMap[$emp->id])) {
                $currentShiftEmployees->push($emp);
            } elseif (in_array($emp->id, $inspectedOtherShiftIds)) {
                $inspectedPreviousShiftEmployees->push($emp);
            } else {
                $otherShiftEmployees->push($emp);
            }
        }

        if ($currentShiftEmployees->isNotEmpty()) {
            $grouped->put('เป้าหมายกะปัจจุบัน (' . $session->shift_label . ')', $currentShiftEmployees);
        }
        if ($inspectedPreviousShiftEmployees->isNotEmpty()) {
            $grouped->put('ถูกตรวจแล้วในกะก่อนหน้า', $inspectedPreviousShiftEmployees);
        }
        if ($otherShiftEmployees->isNotEmpty()) {
            $grouped->put('พนักงานกะอื่น (นอกกะ)', $otherShiftEmployees);
        }
        if ($inspectedPreviousShiftEmployees->isNotEmpty()) {
            $grouped->put('ถูกตรวจแล้วในกะก่อนหน้า', $inspectedPreviousShiftEmployees);
        }
        if ($otherShiftEmployees->isNotEmpty()) {
            $grouped->put('พนักงานกะอื่น (นอกกะ)', $otherShiftEmployees);
        }

        // Progress stats
        $inspectedCount = count(array_intersect($allEmployeeIds, $completedIds));
        $failedCount = count(array_intersect($allEmployeeIds, $failedIds));
        $progressPercent = $totalEmployees > 0 ? round(($inspectedCount / $totalEmployees) * 100) : 0;

        return view('inspections.browse', compact(
            'session', 'grouped', 'inspectedIds', 'inspectedCurrentIds', 'absentIds', 'failedIds', 'search',
            'totalEmployees', 'inspectedCount', 'failedCount', 'progressPercent', 'showAll',
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

        // Build the list of employees still uninspected in this shift so the Bulk Pass
        // confirmation modal on the scan page can show a checkbox per person —
        // letting the inspector mark who did not come to work before committing.
        // Without this the modal had no names and silently credited absentees with a pass.
        $targetEmployees = $session->getSessionTargetEmployees();
        $targetEmployeeIds = $targetEmployees->pluck('id')->toArray();
        $totalEmployees = count($targetEmployeeIds);

        $inspectedIds = \App\Models\InspectionLog::where('session_id', $session->id)
            ->whereIn('employee_id', $targetEmployeeIds)
            ->pluck('employee_id')
            ->unique()
            ->toArray();

        $shiftRemainingEmployees = $targetEmployees->whereNotIn('id', $inspectedIds)->values();
        $shiftRemainingCount = $shiftRemainingEmployees->count();
        $inspectedCount = count($inspectedIds);
        $progressPercent = $totalEmployees > 0 ? (int) round(($inspectedCount / $totalEmployees) * 100) : 0;

        return view('inspections.scan', compact(
            'session',
            'failedCount',
            'shiftRemainingEmployees',
            'shiftRemainingCount',
            'totalEmployees',
            'inspectedCount',
            'progressPercent'
        ));
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

        // Allow the session's inspector (including QA staff) or supervisors/managers/admins to close out the shift
        $user = Auth::user();
        $canBulkPass = $user->isSupervisor() || $user->isManager() || $user->isAdmin() || $session->inspector_id === $user->id;
        if (!$canBulkPass) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'ไม่มีสิทธิ์ใช้งานฟังก์ชันนี้ (Unauthorized)'], 403);
            }
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'คุณไม่มีสิทธิ์ใช้งานฟังก์ชัน Pass All');
        }

        if ($session->isLocked()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)'], 403);
            }
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        $request->validate([
            'absent_employee_ids' => 'nullable|array',
            'absent_employee_ids.*' => 'integer|exists:employees,id',
        ]);

        try {
            $count = $this->inspectionService->bulkPassRemaining(
                $session,
                $request->input('absent_employee_ids', []) ?? []
            );

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

        // Priority 1: Employee-specific checkpoints (employee_checkpoint)
        // Priority 2: Location checkpoints (location_checkpoint)
        // Priority 3: All active checkpoints (fallback)
        $checkpointQuery = Checkpoint::where('is_active', true)->orderBy('sort_order')->orderBy('id');
        if ($checkpointType) {
            $checkpointQuery->where('type', $checkpointType);
        }

        // Loop Engineering Fix: Dynamic Location Selector
        $currentLocationId = $request->query('location_id', $employee->location_id);
        $employeeLocation = $currentLocationId ? \App\Models\Location::find($currentLocationId) : null;
        
        // Fetch all locations for the override dropdown
        $departmentLocations = \App\Models\Location::orderBy('location_name')->get();
        
        // Priority 1: Employee-specific checkpoints
        $checkpoints = $employee->checkpoints()
            ->where('is_active', true)
            ->when($checkpointType, fn($q) => $q->where('type', $checkpointType))
            ->orderBy('sort_order')
            ->orderBy('checkpoints.id')
            ->get();
        
        // Priority 2: Location checkpoints (if employee has no personal mapping)
        if ($checkpoints->isEmpty() && $employeeLocation) {
            $checkpoints = $employeeLocation->checkpoints()
                ->where('is_active', true)
                ->when($checkpointType, fn($q) => $q->where('type', $checkpointType))
                ->orderBy('sort_order')
                ->orderBy('checkpoints.id')
                ->get();
        }
        
        // Priority 3: All active checkpoints (final fallback)
        if ($checkpoints->isEmpty()) {
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
            // ~13.4M base64 chars decodes to ~10MB, matching the logs.*.photo cap.
            // Without a cap this field accepted an unbounded string that was
            // base64_decode()d into memory and then written to disk.
            'random_evidence_photo_base64' => 'nullable|string|max:13400000',
            'logs' => 'required|array',
            'logs.*.checkpoint_id' => 'required|exists:checkpoints,id',
            'logs.*.result' => 'required|in:pass,fail',
            'logs.*.photo' => 'nullable|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB max, must be image
            'logs.*.reclean_assigned_to' => 'nullable|exists:users,id'
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
                
                $imageBinary = base64_decode($base64String, true);
                $filename = 'evidence_random_' . uniqid() . '_' . $session->id . '_' . $request->employee_id . '.webp';
                $path = 'evidence/' . $filename;

                // The payload must actually be an image before anything is written.
                // The fallback below exists for cameras whose format Intervention cannot
                // re-encode, but it used to run on ANY exception, so arbitrary bytes
                // reached web-served storage whenever Image::read() threw.
                $isImage = $imageBinary !== false && @getimagesizefromstring($imageBinary) !== false;

                if (! $isImage) {
                    \Log::warning('Rejected non-image random evidence payload', [
                        'session_id' => $session->id,
                        'employee_id' => $request->employee_id,
                        'user_id' => Auth::id(),
                    ]);

                    return redirect()->back()
                        ->with('error', 'ไฟล์รูปหลักฐานไม่ถูกต้อง กรุณาถ่ายใหม่อีกครั้ง');
                }

                try {
                    $image = Image::read($imageBinary);
                    $image->scale(width: 800);
                    $encoded = $image->toWebp(quality: 75);
                    Storage::disk('public')->put($path, (string) $encoded);
                    $randomPhotoPath = $path;
                } catch (\Exception $e) {
                    // Intervention could not re-encode it, but it is a real image, so
                    // keep the original rather than losing the inspector's evidence.
                    Storage::disk('public')->put($path, $imageBinary);
                    $randomPhotoPath = $path;
                }
                
                // Clear the temporary random photo from session once it is saved
                session()->forget("random_photo_{$session->id}_{$request->employee_id}");
            }

            // Pre-process images and correction actions
            $isFirstLog = true;
            $failedItems = [];

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
                    
                    $cpTitle = Checkpoint::find($checkpointId)?->title ?? 'รายการที่ไม่ผ่าน';
                    $failedItems[] = [
                        'title' => $cpTitle,
                        'correction' => $data['correction']
                    ];
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

        if (!empty($failedItems)) {
            try {
                \Illuminate\Support\Facades\Notification::route(\App\Channels\LineMessagingChannel::class, '')
                    ->notify(new \App\Notifications\InspectionFailedLineNotification(
                        $employee->fullname,
                        $session->department->dept_name ?? 'ไม่ระบุ',
                        $failedItems
                    ));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('LINE Failed Notification Error: ' . $e->getMessage());
            }
        }

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
        // In Inbox Mode (empty date), all actionable items (pending QA, reclean, pending manager approval) are loaded across all dates, plus today's approved items.
        // Explicit dates only apply if user selected a date in the date picker.
        $dateStr = (string) ($request->input('date') ?? '');
        
        // Parse Flatpickr Range "YYYY-MM-DD to YYYY-MM-DD"
        [$startDate, $endDate] = $this->verificationDateRange($dateStr);

        $user = Auth::user();

        // --- SCOPE DEFINITION (Role Matrix Compliance) ---
        // Global scope users (QA, Admin, Executive) see all departments
        // Isolated scope users see only their own department  
        $scopeDeptId = null;
        if (!$user->hasGlobalVisibility()) {
            $scopeDeptId = $user->scopedDepartmentId();
        }

        // 2. Type Filtering (Extract early to prevent loading thousands of logs into memory)
        $filterType = $request->input('filter_type', 'person');
        if (!in_array($filterType, ['person', 'area', 'machine'])) {
            $filterType = 'person';
        }

        // --- One predicate, used twice ---------------------------------------
        // Once to summarise every group in SQL, and again to load the logs of
        // only the groups this page shows. Both have to select exactly the same
        // rows, so the predicate lives in one place. Columns are qualified
        // because the summary joins machines, which has a location_id too.
        $baseQuery = fn () => $this->verificationLogQuery($startDate, $endDate, $scopeDeptId);

        // --- Summarise every group in SQL -------------------------------------
        // The page shows 20 groups, but it used to hydrate every matching log to
        // work out what those 20 were: on the UAT database that meant 17,993 rows
        // and ~2.6s before anything rendered, growing with every round the system
        // has ever verified. These aggregates answer "which groups exist, and what
        // status is each" in a single pass over narrow columns, so only the 20 on
        // screen are ever loaded in full.
        $summaryRows = $this->verificationGroupSummary($baseQuery());

        // 'verified' means QA has signed it off and it is now waiting on a manager,
        // which is a different job from work that is finished. They shared a tab
        // labelled "รออนุมัติ / ผ่านแล้ว", so a manager had no way to see what was
        // actually left for them - 17,993 logs had piled up behind that.
        $matchesTab = function (string $status) use ($activeTab) {
            if ($activeTab === 'pending') {
                return $status === 'pending';
            } elseif ($activeTab === 'awaiting_approval') {
                return $status === 'verified';
            } elseif ($activeTab === 'completed') {
                return in_array($status, ['approved', 'auto_verified']);
            } elseif ($activeTab === 'reclean') {
                return $status === 'reclean';
            }
            return true;
        };

        // 2. Status Counts (KPI cards and tab badges) for the CURRENT category.
        // Counted across every tab, so the badges do NOT drop to 0 when the user
        // navigates between tabs.
        $currentTypeRows = $summaryRows->filter(
            fn($row) => $filterType === 'person' ? $row->is_person : ! $row->is_person
        );

        $counts = [
            'pending' => $currentTypeRows->where('group_status', 'pending')->count(),
            'awaiting_approval' => $currentTypeRows->where('group_status', 'verified')->count(),
            'completed' => $currentTypeRows->whereIn('group_status', ['approved', 'auto_verified'])->count(),
            'reclean' => $currentTypeRows->where('group_status', 'reclean')->count(),
            'total' => $currentTypeRows->count(),
        ];

        // 3. Type Counts (badges on the 'พนักงาน' and 'พื้นที่ / เครื่องจักร' buttons),
        // for the open tab across both categories.
        $activeTabRows = $summaryRows->filter(fn($row) => $matchesTab($row->group_status));

        $typeCounts = [
            'person' => $activeTabRows->filter(fn($row) => $row->is_person)->count(),
            'machine' => $activeTabRows->reject(fn($row) => $row->is_person)->count(),
        ];

        // 4. The rows this tab shows, and 5. the slice of them on this page.
        // Both happen before any log is loaded - that is the whole point.
        $itemsToDisplay = $currentTypeRows->filter(fn($row) => $matchesTab($row->group_status))->values();

        $perPage = 20;
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $pageRows = $itemsToDisplay->slice(($currentPage - 1) * $perPage, $perPage)->values();

        // Now that the page knows which twenty groups it shows, ask for the
        // columns only a rendered card needs. Counting those for every group in
        // the backlog cost ~110ms on every page view, the empty tabs included.
        if ($pageRows->isNotEmpty()) {
            $cardDetail = $this->verificationGroupSummary(
                $baseQuery()->whereIn(
                    'inspection_logs.session_id',
                    $pageRows->pluck('session_id')->unique()->values()
                ),
                true
            )->keyBy('group_key');

            $pageRows = $pageRows->map(fn($row) => $cardDetail->get($row->group_key))->filter()->values();
        }

        // Loading by session pulls in at most a handful of extra location groups
        // belonging to the same round; they are dropped again below.
        $logs = collect();
        if ($pageRows->isNotEmpty()) {
            $logs = $baseQuery()
                ->with(['employee', 'checkpoint', 'employee.department', 'employee.shift', 'location', 'machine', 'session.inspector', 'session.department', 'verifier', 'correctiveAction.approvals'])
                ->whereIn('inspection_logs.session_id', $pageRows->pluck('session_id')->unique()->values())
                ->orderBy('inspection_logs.inspected_at', 'desc')
                ->get();
        }

        $groupedLogs = $logs->groupBy(fn ($log) => $this->verificationGroupKey($log));

        // Walk the summary rather than the loaded logs, so the page keeps the
        // newest-first order the aggregate query already settled.
        // Each entry pairs the group's SQL summary with its logs. The summary
        // supplies every count the card shows; the logs are only there for the
        // detail list and the findings.
        $displayGroups = $pageRows
            ->map(fn($row) => [$row, $groupedLogs->get($row->group_key)])
            ->filter(fn($pair) => $pair[1] !== null);

        // Pre-fetch the roster rows for the groups on screen. This used to ask for
        // one group's schedule at a time, over every group in the backlog.
        $employeeIds = $logs->pluck('employee_id')->unique()->filter()->values();
        $scheduleDates = $logs->map(function ($log) {
            $date = $log->session?->inspection_date;

            return $date ? $date->toDateString() : now()->toDateString();
        })->unique()->values();

        $schedulesByEmployeeDate = collect();
        if ($employeeIds->isNotEmpty() && $scheduleDates->isNotEmpty()) {
            $schedulesByEmployeeDate = \App\Models\EmployeeSchedule::with('shift')
                ->whereIn('employee_id', $employeeIds)
                ->whereIn('date', $scheduleDates)
                ->get()
                ->keyBy(fn ($sch) => $sch->employee_id . '|' . $sch->date->toDateString());
        }

        $groupedInspections = $displayGroups->map(
            fn ($pair) => $this->buildVerificationGroup($pair[0], $pair[1], $schedulesByEmployeeDate)
        );

        // The old code filtered out groups whose is_action_required was false.
        // Nothing ever set it false, so the filter has been dropped rather than
        // carried over - the flag itself is still on each group for the view.
        $paginatedInspections = new \Illuminate\Pagination\LengthAwarePaginator(
            $groupedInspections->values(),
            $itemsToDisplay->count(),
            $perPage,
            $currentPage,
            [
                'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );
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

        $user = Auth::user();
        
        $logsQuery = InspectionLog::with('session')->whereIn('id', $ids);
        if (!$user->isAdmin() && !$user->hasGlobalVisibility()) {
            $logsQuery->whereHas('session', fn($q) => $q->where('department_id', $user->department_id));
        }
        $logs = $logsQuery->get();
        
        if ($logs->isEmpty()) {
            return back()->with('error', 'ไม่พบรายการที่เลือก หรือคุณไม่มีสิทธิ์เข้าถึงข้อมูลของแผนกนี้');
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
            // Prevent duplicate re-clean triggers on already re-cleaned items
            $failedLogs = InspectionLog::whereIn('id', $logs->pluck('id'))
                ->where('result', 'fail')
                ->where(function($q) {
                    $q->where('verification_status', '!=', 'reclean')->orWhereNull('verification_status');
                })
                ->get();
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
                        if ($manager->email && $manager->wantsEmailFor('email_order_reclean')) {
                            $emails[] = $manager->email;
                        }
                    }

                    if (count($emails) > 0) {
                        \Illuminate\Support\Facades\Mail::bcc($emails)
                            ->send(new \App\Mail\OrderRecleanNotification($session, $failedLogs, $comment));
                    }
                } catch (\Throwable $e) {
                    \Log::error('Failed to send Re-clean notification: ' . $e->getMessage());
                }
            }

            // 2. Handle Passed Items (Set to 'verified')
            // If the supervisor clicked "Order Re-clean", they likely accept the Passed items effectively, 
            // or at least we don't want to make the user re-do them.
            $passedIds = InspectionLog::whereIn('id', $logs->pluck('id'))->where('result', 'pass')->pluck('id')->toArray();
            
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
            InspectionLog::whereIn('id', $logs->pluck('id'))->update([
                'verified_at' => now(),
                'verifier_id' => Auth::id(), 
                'verification_status' => $status,
                'verification_comment' => $comment
            ]);

            \Log::info('verify: log updated');
            // Loop Engineering: When QA verifies, we trigger Manager Approval
            if ($status === 'verified') {
                $cars = \App\Models\CorrectiveAction::whereIn('inspection_log_id', $logs->pluck('id'))
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

        // Update session verification timestamp and verifier
        $sessionIds = $logs->pluck('session_id')->unique()->filter();
        if ($sessionIds->isNotEmpty()) {
            InspectionSession::whereIn('id', $sessionIds)->update([
                'verified_at' => now(),
                'verified_by' => Auth::id(),
            ]);
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

                    $mailed = 0;
                    foreach ($qaManagers as $manager) {
                        if ($manager->email && $manager->wantsEmailFor('email_session_verified')) {
                            \Illuminate\Support\Facades\Mail::to($manager->email)
                                ->send(new \App\Mail\InspectionVerified($session, Auth::user()));
                            $mailed++;
                        }
                    }

                    // 'email_session_verified' defaults to false, so this silently
                    // mails no one unless a manager has opted in. Say so in the log.
                    if ($mailed === 0) {
                        \Log::warning("verify: session {$session->id} fully verified but NO email sent - {$qaManagers->count()} QA manager(s) found, none opted in to 'email_session_verified'.");
                    } else {
                        \Log::info("verify: session {$session->id} verified, queued email to {$mailed} QA manager(s).");
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

        $user = Auth::user();
        
        $logsQuery = InspectionLog::with('session')->whereIn('id', $request->ids);
        if (!$user->isAdmin() && !$user->hasGlobalVisibility()) {
            $logsQuery->whereHas('session', fn($q) => $q->where('department_id', $user->department_id));
        }
        $logs = $logsQuery->get();
        
        if ($logs->isEmpty()) {
            return redirect()->route('inspection.verification', ['tab' => 'pending'])->with('error', 'ไม่พบรายการที่เลือก หรือคุณไม่มีสิทธิ์เข้าถึงข้อมูลของแผนกนี้');
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
                // Clearing finished_notified_at matters: the round will be finished a second
                // time once the inspector reworks it, and the supervisor who sent it back has
                // to hear about that. Leaving the marker set would silence the rework.
                $affectedSession->update([
                    'status' => 'in_progress',
                    'finished_notified_at' => null,
                ]);
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

        // Segregation of duty is enforced HERE, not in verify(). Each shift has a single
        // QA inspector, so that person necessarily inspects and verifies their own round
        // — blocking verify() would stop inspections outright. The manager approval step
        // is the genuine second signature: a manager is not the one walking the shift, so
        // refusing to let them approve a round they personally inspected costs nothing
        // operationally and restores two-person control.
        $ownWork = $approvedLogs->filter(fn($log) => $log->session?->inspector_id === $user->id);

        if ($ownWork->isNotEmpty() && !$user->isAdmin()) {
            return back()->with('error', 'ไม่สามารถอนุมัติรอบที่ตัวเองเป็นผู้ตรวจได้ กรุณาให้ผู้จัดการท่านอื่นอนุมัติ (Cannot approve a round you inspected yourself)');
        }

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
            // Track approval on session immediately
            InspectionSession::where('id', $sessionId)->whereNull('approved_by')->update([
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

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

    /**
     * Percentage of assessed checkpoints that passed.
     *
     * Only 'pass' and 'fail' are outcomes. 'absent' (nobody came to work) and
     * 'no_production' (the line was not running) mean nothing was assessed, so they are
     * excluded from both sides of the ratio — counting them as passes is what let a
     * shift where three people were on leave report a perfect hygiene score.
     * ReportController applies the same exclusion to its own figures.
     *
     * @return int|null null when nothing was assessed at all, so the UI can show "—"
     *                  instead of a fabricated 100%.
     */
    private function scoreFromLogs($logs): ?int
    {
        $assessed = $logs->whereIn('result', ['pass', 'fail']);

        if ($assessed->isEmpty()) {
            return null;
        }

        return (int) round($assessed->where('result', 'pass')->count() / $assessed->count() * 100);
    }

    /**
     * The same ratio as scoreFromLogs, for callers that already counted in SQL.
     *
     * Both dashboard figures used to fall back to a literal 100 when nothing
     * had been assessed, so a morning where no one had inspected anything yet
     * rendered a green "อัตราผ่าน 100%" beside "งานตรวจวันนี้ 0". Green reads
     * as all-clear, which is the opposite of what an untouched shift means.
     *
     * @return int|null null when nothing was assessed, so the page can print
     *                  "—" instead of inventing a perfect score.
     */
    private function passRate(int $pass, int $fail): ?int
    {
        $assessed = $pass + $fail;

        if ($assessed === 0) {
            return null;
        }

        return (int) round($pass / $assessed * 100);
    }

    /**
     * The body of one card's detail modal.
     *
     * The page used to render all twenty modals inline, which is why it loaded
     * every log of twenty rounds - 5,620 rows on the UAT database - to build
     * markup that stayed hidden until someone clicked. The card now ships empty
     * and asks for its contents here.
     *
     * Reuses the page's own predicate, summary and builder, so a card opened
     * here shows exactly what the page would have shown inline.
     */
    public function verificationDetail(Request $request)
    {
        $request->validate([
            'session_id' => 'required|integer',
            'group_key' => 'required|string',
            'date' => 'nullable|string',
        ]);

        $user = Auth::user();
        $scopeDeptId = $user->hasGlobalVisibility() ? null : $user->scopedDepartmentId();

        [$startDate, $endDate] = $this->verificationDateRange((string) $request->input('date', ''));
        $sessionId = (int) $request->input('session_id');
        $groupKey = (string) $request->input('group_key');

        $base = fn () => $this->verificationLogQuery($startDate, $endDate, $scopeDeptId)
            ->where('inspection_logs.session_id', $sessionId);

        // Scope is enforced by the predicate itself: a round outside this user's
        // department summarises to nothing, and they get a 404 rather than a card.
        $summary = $this->verificationGroupSummary($base(), true)->firstWhere('group_key', $groupKey);

        abort_if($summary === null, 404);

        $logs = $base()
            ->with(['employee', 'checkpoint', 'employee.department', 'employee.shift', 'location', 'machine', 'session.inspector', 'session.department', 'verifier', 'correctiveAction.approvals'])
            ->orderBy('inspection_logs.inspected_at', 'desc')
            ->get()
            ->filter(fn ($log) => $this->verificationGroupKey($log) === $groupKey)
            ->values();

        abort_if($logs->isEmpty(), 404);

        $schedulesByEmployeeDate = collect();
        $employeeIds = $logs->pluck('employee_id')->unique()->filter()->values();
        if ($employeeIds->isNotEmpty()) {
            $date = $logs->first()->session?->inspection_date;
            $schedulesByEmployeeDate = \App\Models\EmployeeSchedule::with('shift')
                ->whereIn('employee_id', $employeeIds)
                ->where('date', $date ? $date->toDateString() : now()->toDateString())
                ->get()
                ->keyBy(fn ($sch) => $sch->employee_id . '|' . $sch->date->toDateString());
        }

        return view('inspections.partials.verification-detail', [
            'group' => $this->buildVerificationGroup($summary, $logs, $schedulesByEmployeeDate),
        ]);
    }

    /**
     * The key a log's card is filed under: a personnel round is one group per
     * session, an area round one per location, and a machine counts as its
     * location. Mirrors the loc_id expression in verificationGroupSummary().
     */
    private function verificationGroupKey(InspectionLog $log): string
    {
        if ($log->employee_id) {
            return $log->session_id . '_personnel';
        }

        return $log->session_id . '_loc_' . ($log->location_id ?? ($log->machine->location_id ?? 'unknown'));
    }

    /**
     * Flatpickr hands back "YYYY-MM-DD to YYYY-MM-DD", localised to " ถึง " in Thai.
     * Nothing selected means inbox mode, which has no date bound.
     *
     * Nullable on purpose: the page's own filter form submits date= with nothing
     * in it, and ConvertEmptyStringsToNull turns that into null before the
     * controller sees it - so every link the page builds for itself arrives here
     * as null rather than as the empty string the query string suggests.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function verificationDateRange(?string $dateStr): array
    {
        if (empty($dateStr)) {
            return [null, null];
        }

        $separator = str_contains($dateStr, ' ถึง ') ? ' ถึง ' : ' to ';
        $dates = explode($separator, $dateStr);
        $start = trim($dates[0]);

        // A single date selected means start = end.
        return [$start, trim($dates[1] ?? $dates[0])];
    }

    /**
     * One narrow row per group, with every count a card shows.
     */
    private function verificationGroupSummary($query, bool $withCardDetail = false): \Illuminate\Support\Collection
    {
        return $this->verificationGroups->summarise($query, $withCardDetail);
    }

    /**
     * The rows /verification works from, for a given date window and scope.
     */
    private function verificationLogQuery(?string $startDate, ?string $endDate, ?int $scopeDeptId)
    {
        return $this->verificationGroups->logQuery($startDate, $endDate, $scopeDeptId);
    }

    /**
     * Build the card the verification page (and its detail modal) renders for
     * one group: the summary row carries its counts, the logs its detail.
     */
    private function buildVerificationGroup(object $summary, $logsInGroup, $schedulesByEmployeeDate): object
    {
        $firstLog = $logsInGroup->first();

        // One Carbon for the card's timestamp. The row carries max(inspected_at)
        // already; asking the collection for it walked every log, and each read of
        // a date column parses a fresh Carbon - twice over, for the date and time.
        $lastInspectedAt = $summary->last_inspected_at
            ? \Illuminate\Support\Carbon::parse($summary->last_inspected_at)
            : null;
        $session = $firstLog->session;
        
        // BUG-009 Fix: Inconsistent Type Check
        $type = $firstLog->machine_id ? 'machine' : 'area'; 
        if ($firstLog->employee_id) {
             $type = 'person';
        }

        $shift = $session->shift;
        $round = $session->round ?? 1; 
        $inspectorName = $session->inspector->name ?? 'Unknown';
        $shiftLabel = $session ? $session->shift_label : '-';

        $employee = $firstLog->employee;
        $location = $firstLog->location;
        $machine = $firstLog->machine;

        if ($employee && $session) {
            $schDate = $session->inspection_date ? $session->inspection_date->startOfDay() : now()->startOfDay();
            $empSch = $schedulesByEmployeeDate->get($employee->id . '|' . $schDate->toDateString());
            if ($empSch && $empSch->shift) {
                $shiftLabel = $empSch->shift->shift_name;
            } elseif ($employee->shift) {
                $shiftLabel = $employee->shift->shift_name;
            }
        }
        
        $failedLogs = $logsInGroup->filter(function ($log) {
            return $log->result === 'fail';
        });

        // A group reads as passed when no failure is left unsigned-off. Counted
        // in SQL: the old version filtered the failures again per group.
        $isPass = (int) $summary->n_outstanding_fail === 0;

        // Check for 100% no_production or absent
        $isAllNoProduction = (int) $summary->n_no_production === (int) $summary->n_total;
        $isAllAbsent = (int) $summary->n_absent === (int) $summary->n_total;

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
            $employeeCount = (int) $summary->n_employees;
            $isFailedGroup = (int) $summary->n_fail > 0;

            $isAutoVerifiedGroup = (int) $summary->n_auto + (int) $summary->n_approved
                + (int) $summary->n_verified === (int) $summary->n_total;

            $name = "ตรวจพนักงาน จำนวน {$employeeCount} คน";
            if ($isFailedGroup) {
                $name .= " (พบข้อบกพร่อง)";
            } elseif ($isAutoVerifiedGroup) {
                $name .= " (ผ่านอัตโนมัติ)";
            } else {
                $name .= " (รอทวนสอบทั้งหมด)";
            }

            $subtext = $firstLog->session->department->dept_name ?? '-';
            
            $statusType = $isFailedGroup ? 'failed' : 'passed';
            $verifyGroup = $isAutoVerifiedGroup ? 'verified' : 'pending';
            $modalId = 'sess_personnel_' . $firstLog->session_id . '_' . $statusType . '_' . $verifyGroup;
            
            $imagePath = null;
            $employee = null; // Unset so UI treats it as a group

            $monthlyFailures = 0;
            // Was hardcoded to 100, so a round consisting entirely of people who did
            // not come to work still displayed "Hygiene Score 100%". Score the actual
            // results, counting only checkpoints someone was really assessed on —
            // 'absent' and 'no_production' are not outcomes, they are non-events, and
            // ReportController already excludes them from its own scoring.
            $hygieneScore = $this->scoreFromLogs($logsInGroup);
            $trafficLight = match (true) {
                $hygieneScore === null => 'grey',
                $hygieneScore >= 90 => 'green',
                $hygieneScore >= 70 => 'yellow',
                default => 'red',
            };
        } else {
            $machineCount = (int) $summary->n_machines;
            $hasArea = (int) $summary->n_without_machine > 0;
            $type = 'machine'; // Default to machine so it shows the machine icon, or area if only area
            if ($machineCount === 0) $type = 'area';
            
            // Find the best representation of location
            $location = $firstLog->location ?? ($firstLog->machine->location ?? null);
            
            $sessionDept = $firstLog->session?->department?->dept_name;
            $name = $location ? $location->location_name : 'พื้นที่ไม่ระบุ';
            if ($machineCount > 0 && $hasArea) {
                 $name .= " (พื้นที่ + อุปกรณ์ {$machineCount} ชิ้น)";
                 $subtext = $sessionDept ?: 'พื้นที่และเครื่องจักร';
            } elseif ($machineCount > 0) {
                 $name .= " (ตรวจอุปกรณ์ {$machineCount} ชิ้น)";
                 $subtext = $sessionDept ?: 'เครื่องจักร/อุปกรณ์';
            } else {
                 $subtext = $sessionDept ?: 'พื้นที่';
            }
            
            $modalId = 'loc_' . ($location->id ?? rand()) . '_sess_' . $firstLog->session_id;
            $imagePath = $location->image ?? null;
            
            $monthlyFailures = 0;
            // Same fix as the personnel branch: score the real results rather than
            // reporting a flat 100. 'no_production' areas are excluded the same way
            // absences are — nothing was assessed.
            $hygieneScore = $this->scoreFromLogs($logsInGroup);
            $trafficLight = match (true) {
                $hygieneScore === null => 'grey',
                $hygieneScore >= 90 => 'green',
                $hygieneScore >= 70 => 'yellow',
                default => 'red',
            };
        }

        $hasReclean = (int) $summary->n_reclean > 0;
        // reject() stamps verified_at alongside verification_status = 'rejected', so
        // a rejected group used to fail the is_null(verified_at) test, fall through
        // the ladder below and be filed as 'verified' — rejected work vanished from
        // the queue into the completed tab and was never signed off by anyone.
        // A rejection means the work still needs attention: treat it as pending.
        $hasRejected = (int) $summary->n_rejected > 0;
        $hasPending = $hasRejected || (int) $summary->n_unverified > 0;

        // The same ladder the summary query's status already walked - see
        // groupStatusFromCounts(). Reading it back costs nothing; deriving it here
        // meant five more passes over every log in the group, one of them touching
        // verified_at and so building a Carbon per log.
        $groupStatus = $summary->group_status;
        $isGroupApproved = $groupStatus === 'approved';
        $isGroupAutoVerified = $groupStatus === 'auto_verified';

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
            // Lets a card ask the server for its own detail body.
            'group_key' => $summary->group_key,
            'is_sampling' => $session->is_sampling ?? false, // Loop Engineering: Flag to identify random audit
            'date' => $lastInspectedAt?->format('d/m/Y') ?? '-',
            'time' => $lastInspectedAt?->format('H:i') ?? '-',
            'status' => $status,
            'is_action_required' => $isActionRequired,
            'findings' => $failedLogs->values(),
            'all_logs' => $logsInGroup->values(),
            'is_verified' => !$hasPending && !$hasReclean,
            'is_approved' => $isGroupApproved || $isGroupAutoVerified,
            // Each shift runs a single QA inspector, so the person who inspected is
            // usually also the one who verified. That is accepted operationally, but
            // it must be visible: an FM-QA-22 auditor needs to see which rounds
            // carried only one signature rather than have it look like two people
            // signed. The real second signature is the manager approval step.
            'self_verified' => $logsInGroup->contains(
                fn($l) => !is_null($l->verifier_id) && $l->verifier_id === $session->inspector_id
            ),
            'is_acknowledged' => (int) $summary->n_unacknowledged === 0, // Gap 3: Check if acknowledged
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
    }

    private function authorizeSessionOwner(InspectionSession $session): void
    {
        $user = Auth::user();
        $isOwner = $session->inspector_id === $user->id;
        $canSupervise = $user->isQA() && ($user->isSupervisor() || $user->isManager());

        if (!$isOwner && !$user->isAdmin() && !$canSupervise) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงเซสชันนี้ (Unauthorized: not session owner)');
        }
    }

    private function getAutoShift()
    {
        return \App\Models\Shift::detectCurrent();
    }
}