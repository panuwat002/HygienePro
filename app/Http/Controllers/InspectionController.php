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
    public function home()
    {
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

        // 1. Total unique inspections today
        $todayQuery = InspectionLog::whereDate('inspected_at', $today);
        $todayLogs = $applyScope($todayQuery)->get();
        
        $inspectionsToday = $todayLogs->groupBy(function($log) {
            return $log->session_id . '_' . ($log->machine_id ? 'm' . $log->machine_id : ($log->employee_id ? 'e' . $log->employee_id : 'l' . $log->location_id));
        })->count();

        // 2. Pending Verification
        $pendingQuery = InspectionLog::where('verification_status', 'pending');
        $pendingLogs = $applyScope($pendingQuery)->get();
        
        $pendingVerificationCount = $pendingLogs->groupBy(function($log) {
             return $log->session_id . '_' . ($log->machine_id ? 'm' . $log->machine_id : ($log->employee_id ? 'e' . $log->employee_id : 'l' . $log->location_id));
        })->count();

        // 3. Outstanding Re-cleans
        $recleanQuery = InspectionLog::where('verification_status', 'reclean');
        $recleanLogs = $applyScope($recleanQuery)->get();

        $recleanCount = $recleanLogs->groupBy(function($log) {
            return $log->session_id . '_' . ($log->machine_id ? 'm' . $log->machine_id : ($log->employee_id ? 'e' . $log->employee_id : 'l' . $log->location_id));
        })->count();

        // 4. Daily Pass Rate (Based on Scope)
        $totalPass = $todayLogs->where('result', 'pass')->count();
        $totalFail = $todayLogs->where('result', 'fail')->count();
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
        $carsByDept = $carsThisMonth->groupBy(fn($c) => $c->log->session->department->dept_name ?? 'Unknown')
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

        // 7. Today's Scheduled Inspections
        $scheduleDeptId = $user->isAdmin() || $user->hasGlobalVisibility() ? null : $user->department_id;
        $scheduledTasks = collect();
        if ($scheduleDeptId || $user->isAdmin() || $user->hasGlobalVisibility()) {
            $todaysSchedules = $this->scheduleService->getDailySchedules($scheduleDeptId, $today);
            $todaysSchedules->load('department');
            $scheduledTasks = $this->scheduleService->getComplianceStatus($todaysSchedules, $today);
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
            'scheduledTasks'
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
            // Failsafe for users with neither a department nor explicitly assigned global visibility
            $departments = Department::all();
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
                return $log->session_id . '_' . ($log->employee_id ? 'e' . $log->employee_id : 'l' . $log->location_id);
            });

        // 3. Current Session Status & Remaining Items Logic
        $currentAutoShift = $this->getAutoShift();
        $today = now()->toDateString();
        $currentSession = InspectionSession::where('inspector_id', Auth::id())
            ->where('inspection_date', $today)
            ->where('type', $type)
            ->where('shift', $currentAutoShift) // Filter by auto shift
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
                $baseQuery = Employee::where('department_id', $currentSession->department_id)
                    ->where('is_active', true)
                    ->whereHas('shift', function($q) use ($currentSession) {
                        $q->where('shift_name', $currentSession->shift);
                    });
                
                $targetEmployeeIds = $baseQuery->pluck('id')->toArray();
                $totalTargets = count($targetEmployeeIds);
                
                // Count Inspected (Only within the target shift for accurate remaining count)
                $inspectedCount = InspectionLog::where('session_id', $currentSession->id)
                    ->whereIn('employee_id', $targetEmployeeIds)
                    ->distinct('employee_id')
                    ->count('employee_id');

                $remainingCount = max(0, $totalTargets - $inspectedCount);
                
                if($remainingCount > 0) {
                     // Get list of uninspected employees
                     $inspectedIds = InspectionLog::where('session_id', $currentSession->id)
                        ->pluck('employee_id')->toArray();
                     $remainingList = (clone $baseQuery)
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
            
            // Auto-complete if valid remaining is 0 (Self-Fix)
            if ($remainingCount === 0 && $currentSession->status !== 'completed') {
                $currentSession->update(['status' => 'completed']);
                $currentSession->refresh(); // explicit refresh
            }
        }

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
            'scheduledTasks'
        ));
    }

    public function getDepartmentStats($type, $department, Request $request)
    {
        try {
            $shift = $request->query('shift') ?? $this->getAutoShift(); 
            $today = now()->toDateString();

            // 1. Get locations and optionally machines
            $query = Location::query();
            
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

            $locations = $query->get();

            // BUG-012 Fix: Removed unused variable initialization when not needed
            // FIXED (Revert): Variable IS needed for closure below if no session exists
            $inspectedEmployeeIds = [];
            $inspectedLocationIds = [];
            $inspectedMachineIds = [];

            if ($department !== 'all' && $session) {
                $logs = InspectionLog::where('session_id', $session->id)->get();
                if ($type === 'personnel') {
                    $inspectedEmployeeIds = $logs->pluck('employee_id')->unique()->filter()->values()->toArray();
                } else {
                    $inspectedLocationIds = $logs->whereNull('machine_id')->pluck('location_id')->unique()->filter()->values()->toArray();
                    $inspectedMachineIds = $logs->whereNotNull('machine_id')->pluck('machine_id')->unique()->filter()->values()->toArray();
                }
            } else if ($department === 'all' && !empty($sessionIds)) {
                $logs = InspectionLog::whereIn('session_id', $sessionIds)->get();
                if ($type === 'personnel') {
                    $inspectedEmployeeIds = $logs->pluck('employee_id')->unique()->filter()->values()->toArray();
                } else {
                    $inspectedLocationIds = $logs->whereNull('machine_id')->pluck('location_id')->unique()->filter()->values()->toArray();
                    $inspectedMachineIds = $logs->whereNotNull('machine_id')->pluck('machine_id')->unique()->filter()->values()->toArray();
                }
            }

            // 3. Map Inspection Data
            $locations->transform(function ($loc) use ($inspectedEmployeeIds, $inspectedLocationIds, $inspectedMachineIds, $type, $department) {
                if ($type === 'personnel') {
                    // This logic needs to be adjusted for 'all' departments if employee count is desired
                    // For now, it assumes a single department context for employee filtering
                    $locEmployees = $loc->employees()
                                    ->when($department !== 'all', function ($q) use ($department) {
                                        $deptModel = is_numeric($department) ? Department::find($department) : $department;
                                        $q->where('department_id', $deptModel->id);
                                    })
                                    ->where('is_active', true)
                                    ->pluck('id')
                                    ->toArray();

                    $loc->inspected_count = count(array_intersect($locEmployees, $inspectedEmployeeIds));
                    $loc->has_checkpoints = true; // Not strictly used for personnel but consistent
                } else {
                    // For Area
                    $loc->inspected_count = in_array($loc->id, $inspectedLocationIds) ? 1 : 0;
                    
                    // Check area checkpoints
                    $loc->has_checkpoints = false;
                    if ($type === 'area') {
                         $loc->has_checkpoints = $loc->checkpoints()->where('type', 'area')->where('is_active', true)->exists();
                    }
                    
                    // Machine inspection status
                    if ($loc->machines) {
                        foreach ($loc->machines as $m) {
                            $m->is_inspected = in_array($m->id, $inspectedMachineIds);
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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    public function startSession($type, Request $request)
    {
        $rules = [
            'department_id' => ($type === 'personnel') ? 'required|exists:departments,id' : 'nullable',
            'targets' => 'nullable|array' 
        ];
        
        $request->validate($rules);

        // For Area/Machine, if no department selected, use User's department or First available
        // This is a workaround if DB requires department_id. Ideally schema should allow null.
        // Assuming DB requires it based on previous code.
        $deptId = $request->department_id;
        if (empty($deptId) && ($type === 'area' || $type === 'machine')) {
            $deptId = Auth::user()->department_id ?? Department::first()->id;
        }

        try {
            $session = $this->inspectionService->startSession(
                Auth::user(),
                $request->department_id ?? $deptId,
                $type,
                $request->boolean('force_new_round')
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('inspection.dashboard', $type)
                ->with('error', $e->getMessage());
        }

        if ($type === 'personnel') {
            return redirect()->route('inspection.scan', $session->id);
        } else {
            // For Area/Machine, redirect to bulk checklist with targets
            $targets = is_array($request->targets) ? implode(',', $request->targets) : '';

            // If resuming and no targets selected, load ALL targets for this department/type
            // This fixes "Resume" button doing nothing (redirecting back with error)
            if (empty($targets) && $session) {
                 if ($type === 'area') {
                     // Locations are global, not linked to department in DB schema
                     $locs = Location::all();
                     $targetList = [];
                     foreach ($locs as $l) {
                         $targetList[] = "loc:{$l->id}";
                     }
                     $targets = implode(',', $targetList);
                 } elseif ($type === 'machine') {
                     // Machines are global, fetching all active machines
                     $machines = \App\Models\Machine::where('is_active', true)->get();
                     
                     $targetList = [];
                     foreach ($machines as $m) {
                         $targetList[] = "machine:{$m->id}";
                     }
                     $targets = implode(',', $targetList);
                 }
            }

            return redirect()->route('inspection.area.bulk', [
                'department' => $session->department_id,
                'session' => $session->id,
                'targets' => $targets
            ]);
        }
    }
    
    public function browseEmployees(InspectionSession $session, Request $request)
    {
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
        $employeesQuery = Employee::with(['location', 'department', 'shift'])
            ->where('department_id', $session->department_id)
            ->where('is_active', true);

        if (!$showAll) {
            $employeesQuery->whereHas('shift', function ($q) use ($session) {
                $q->where('shift_name', $session->shift);
            });
        }

        $employees = $employeesQuery->orderBy('location_id')
            ->orderBy('fullname')
            ->get();

        // Get already inspected employee IDs
        $inspectedIds = InspectionLog::where('session_id', $session->id)
            ->pluck('employee_id')
            ->unique()
            ->toArray();

        // Group employees by location
        $grouped = $employees->groupBy(function ($emp) {
            return $emp->location->location_name ?? 'ไม่ได้กำหนดพื้นที่ (Unassigned)';
        });

        // Search filter
        $search = $request->input('q', '');

        // Progress stats
        $totalEmployees = $employees->count();
        $inspectedCount = count(array_intersect($employees->pluck('id')->toArray(), $inspectedIds));
        $progressPercent = $totalEmployees > 0 ? round(($inspectedCount / $totalEmployees) * 100) : 0;

        return view('inspections.browse', compact(
            'session', 'grouped', 'inspectedIds', 'search',
            'totalEmployees', 'inspectedCount', 'progressPercent', 'showAll'
        ));
    }

    public function scan(InspectionSession $session)
    {
        if ($session->isLocked()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        if ($session->status === 'completed' && !$session->hasPendingRecleans()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกบันทึกจบงานไปแล้ว ไม่สามารถตรวจเพิ่มได้ (Session already completed)');
        }
        return view('inspections.scan', compact('session'));
    }

    public function pauseSession(InspectionSession $session)
    {
        if ($session->status !== 'completed') {
            $session->update(['status' => 'paused']);
        }

        return redirect()->route('inspection.dashboard', $session->type)
            ->with('success', 'พักการตรวจชั่วคราวแล้ว (Session Paused)');
    }

    public function finishSession(InspectionSession $session)
    {
        $this->inspectionService->finishSession($session);

        return redirect()->route('inspection.dashboard', $session->type)
            ->with('success', 'บันทึกจบงานสรุปยอดการตรวจเรียบร้อยแล้ว (Session Completed)');
    }

    public function showChecklist(InspectionSession $session, $hash, Request $request)
    {
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
                ->with('error', "Employee not found for code: " . $hash);
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
        $checkpointQuery = Checkpoint::where('is_active', true);
        if ($checkpointType) {
            $checkpointQuery->where('type', $checkpointType);
        }

        if ($employee->location_id) {
            $checkpoints = $employee->location->checkpoints()
                ->where('is_active', true)
                ->when($checkpointType, fn($q) => $q->where('type', $checkpointType))
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

    return view('inspections.form', compact('session', 'employee', 'checkpoints', 'existingLogs', 'recleanRequests', 'previousRecleanCount', 'requiresRandomPhoto', 'randomEvidencePhoto'));
    }

    public function storeLog(Request $request, InspectionSession $session)
    {
        if ($session->isLocked()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'random_evidence_photo_base64' => 'nullable|string',
            'logs' => 'required|array',
            'logs.*.checkpoint_id' => 'required|exists:checkpoints,id',
            'logs.*.result' => 'required|in:pass,fail',
        ]);

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
                $filename = 'evidence_random_' . time() . '_' . $session->id . '_' . $request->employee_id . '.webp';
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
                         $cpTitle = Checkpoint::find($checkpointId)->title ?? 'รายการที่ไม่ผ่าน';
                         return back()->with('error', "กรุณาถ่ายรูปหลักฐาน (Evidence Photo) สำหรับ: $cpTitle");
                    }
                }

                $photoPath = null;
                if ($request->hasFile("logs.$checkpointId.photo")) {
                    $file = $request->file("logs.$checkpointId.photo");
                    $filename = 'evidence_' . time() . '_' . $session->id . '_' . $checkpointId . '.webp';
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

        if ($session->status === 'completed') {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('success', 'บันทึกการแก้ไขเรียบร้อยแล้ว (Saved correction for ' . $employee->fullname . ')');
        }

        return redirect()->route('inspection.scan', $session->id)
            ->with('success', 'Saved inspection for ' . $employee->fullname);
    }
    public function verification(Request $request)
    {
        $date = $request->input('date', date('Y-m-d'));
        $user = Auth::user();

        // --- SCOPE DEFINITION (Role Matrix Compliance) ---
        // Global scope users (QA, Admin, Executive) see all departments
        // Isolated scope users see only their own department  
        $scopeDeptId = null;
        if (!$user->hasGlobalVisibility()) {
            $scopeDeptId = $user->department_id;
        }

        $query = InspectionLog::with(['employee', 'checkpoint', 'employee.department', 'location', 'machine', 'session.inspector', 'verifier', 'correctiveAction']) 
            ->whereDate('inspected_at', $date)
            ->orderBy('inspected_at', 'desc');

        if ($scopeDeptId) {
            $query->where(function($q) use ($scopeDeptId) {
                $q->whereHas('employee', function($subQ) use ($scopeDeptId) {
                    $subQ->where('department_id', $scopeDeptId);
                })->orWhereNull('employee_id');
            });
        }

        $logs = $query->get();

        $groupedInspections = $logs->groupBy(function($log) {
            if ($log->employee_id) {
                return $log->session_id . '_e_' . $log->employee_id;
            } elseif ($log->machine_id) {
                return $log->session_id . '_m_' . $log->machine_id;
            } else {
                return $log->session_id . '_l_' . ($log->location_id ?? 'unknown');
            }
        });

        $groupedInspections = $groupedInspections->map(function ($logsInGroup) use ($date) {
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
                return $log->verification_status !== 'approved';
            });

            $isPass = $outstandingFailures->isEmpty();

            if ($employee) {
                $type = 'person';
                $name = $employee->fullname;
                $subtext = $employee->department->dept_name ?? '-';
                $modalId = 'emp_' . $employee->id . '_sess_' . $firstLog->session_id;
                $imagePath = $employee->profile_image;

                $month = now()->month;
                $year = now()->year;
                $monthlyFailures = $employee->getMonthlyFailures($month, $year);
                $hygieneScore = $employee->getHygieneScore($month, $year);
                $trafficLight = $employee->getTrafficLightStatus();
            } else {
                // Logic above handles basic type, but refine here if needed
                $type = $machine ? 'machine' : 'area';
                $name = $machine ? $machine->name : ($location->location_name ?? 'Unknown Area');
                $subtext = $machine ? $location->location_name : 'Area Inspection';
                $modalId = 'loc_' . ($location->id ?? rand()) . ($machine ? '_m_' . $machine->id : '') . '_sess_' . $firstLog->session_id;
                $imagePath = ($machine && $machine->image) ? $machine->image : ($location->image ?? null);
                
                $monthlyFailures = 0;
                $hygieneScore = 100;
                $trafficLight = 'green';
            }

            $hasReclean = $logsInGroup->contains('verification_status', 'reclean');
            $hasPending = $logsInGroup->contains(fn($l) => is_null($l->verified_at));

            // Check Session Approval (Manager Level)
            // Check Group Approval (Log Level)
            $isGroupApproved = $logsInGroup->every(fn($l) => $l->verification_status === 'approved');

            // Determine Group Status Priority: Approved > Re-clean > Pending > Verified
            if ($isGroupApproved) {
                $groupStatus = 'approved';
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
                'image_path' => str_replace('storage/', '', $imagePath),
                'employee' => $employee,
                'machine' => $machine,
                'location' => $location,
                'inspector_name' => $inspectorName,
                'shift' => $shiftLabel,
                'round' => $round,
                'session_id' => $session->id, // Important for Approval
                'date' => $logsInGroup->max('inspected_at')->format('d/m/Y'),
                'time' => $logsInGroup->max('inspected_at')->format('H:i'),
                'status' => $isPass ? 'pass' : 'fail',
                'findings' => $failedLogs->values(),
                'all_logs' => $logsInGroup->values(),
                'is_verified' => !$hasPending && !$hasReclean,
                'is_approved' => $isGroupApproved,
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

        // 1. Calculate Type Counts (Total items per type for this date)
        $typeCounts = [
            'person' => $groupedInspections->filter(fn($g) => $g->type === 'person')->count(),
            'area' => $groupedInspections->filter(fn($g) => $g->type === 'area')->count(),
            'machine' => $groupedInspections->filter(fn($g) => $g->type === 'machine')->count(),
        ];

        // 2. Type Filtering (Default to 'person')
        $filterType = $request->input('filter_type', 'person');
        if (!in_array($filterType, ['person', 'area', 'machine'])) {
            $filterType = 'person';
        }
        
        // Filter the main list by selected type
        $groupedInspections = $groupedInspections->filter(fn($g) => $g->type === $filterType);

        // 3. Calculate Status Counts (For the selected type)
        $counts = [
            'pending' => $groupedInspections->filter(fn($g) => $g->verification_status === 'pending')->count(),
            'completed' => $groupedInspections->filter(fn($g) => $g->verification_status === 'verified')->count(),
            'reclean' => $groupedInspections->filter(fn($g) => $g->verification_status === 'reclean')->count(),
            'total' => $groupedInspections->count(),
        ];

        // 4. Status Tab Filtering
        $activeTab = $request->input('tab', 'pending');
        if ($activeTab === 'pending') {
            // Strictly 'pending', NOT just unverified. Exclude 'reclean'.
            $groupedInspections = $groupedInspections->filter(fn($g) => $g->verification_status === 'pending');
        } elseif ($activeTab === 'completed') {
            $groupedInspections = $groupedInspections->filter(fn($g) => in_array($g->verification_status, ['verified', 'approved']));
        } elseif ($activeTab === 'reclean') {
            $groupedInspections = $groupedInspections->filter(fn($g) => $g->verification_status === 'reclean');
        }

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
            'date' => $date,
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
        if (!Auth::user()->can('verify')) {
            abort(403, 'Unauthorized. Requires Supervisor verify permission.');
        }

        $request->validate([
            'ids' => 'required|array',
            'status' => 'required|string|in:verified,reclean', // BUG-011 Fix: Added validation
            'comment' => 'nullable|string'
        ]);

        $ids = $request->ids;
        $status = $request->status; // 'verified' or 'reclean'
        $comment = $request->input('comment');

        $logs = InspectionLog::with('session')->whereIn('id', $ids)->get();
        if ($logs->isEmpty()) {
            return back()->with('error', 'ไม่พบรายการที่เลือก');
        }

        if ($logs->contains(fn ($log) => $log->session?->isLocked())) {
            return back()->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถตรวจสอบ/แก้ไขได้ (Session Locked)');
        }

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

                // Notify Department Manager (Only about the failed items)
                try {
                    $session = $failedLogs->first()->session;
                    $departmentId = $session->department_id;

                    // Find Manager (Level >= 5) in that department
                    $managers = \App\Models\User::where('department_id', $departmentId)
                                ->where('level', '>=', 5)
                                ->get();

                    foreach ($managers as $manager) {
                        if ($manager->email) {
                            \Illuminate\Support\Facades\Mail::to($manager->email)
                                ->send(new \App\Mail\OrderRecleanNotification($session, $failedLogs, $comment));
                        }
                    }
                } catch (\Exception $e) {
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
            // Normal Verify: Mark Passed as Verified, but Auto-Reclean Failed items
            $passedIds = InspectionLog::whereIn('id', $ids)->where('result', 'pass')->pluck('id')->toArray();
            $failedLogs = InspectionLog::whereIn('id', $ids)->where('result', 'fail')->get();

            if (!empty($passedIds)) {
                InspectionLog::whereIn('id', $passedIds)->update([
                    'verified_at' => now(),
                    'verifier_id' => Auth::id(), 
                    'verification_status' => $status,
                    'verification_comment' => $comment
                ]);
            }

            if ($failedLogs->isNotEmpty()) {
                $failedIds = $failedLogs->pluck('id')->toArray();
                InspectionLog::whereIn('id', $failedIds)->update([
                    'verified_at' => now(),
                    'verifier_id' => Auth::id(),
                    'verification_status' => 'reclean',
                    'verification_comment' => $comment ?? 'ระบบสั่งแก้ไขอัตโนมัติ เนื่องจากผลการตรวจไม่ผ่าน'
                ]);
            }
        }

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

        $request->validate([
            'ids' => 'required|array',
            'comment' => 'required|string|max:500', // บังคับใส่เหตุผล
        ]);

        $logs = InspectionLog::with('session')->whereIn('id', $request->ids)->get();
        if ($logs->isEmpty()) {
            return back()->with('error', 'ไม่พบรายการที่เลือก');
        }

        if ($logs->contains(fn ($log) => $log->session?->isLocked())) {
            return back()->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถตีกลับได้ (Session Locked)');
        }

        // Update logs to rejected status
        InspectionLog::whereIn('id', $logs->pluck('id'))->update([
            'verified_at' => now(),
            'verifier_id' => Auth::id(),
            'verification_status' => 'rejected',
            'verification_comment' => $request->comment,
        ]);

        // Reset Session Status to allow editing (only when not fully approved/locked)
        $log = $logs->first();
        if ($log && $log->session && !$log->session->isLocked()) {
            $log->session->update(['status' => 'in_progress']);
        }

        // Send Notification to Inspector
        $inspector = $log->session->inspector;
        if ($inspector) {
             $inspector->notify(new \App\Notifications\InspectionRejectedNotification($log->session, $request->comment));
        }

        // Bug Fix: Return success with alert
        return back()->with('success', 'ตีกลับเรียบร้อยและแจ้งเตือน Staff แล้ว (Rejected & Notified)');
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
        
        // Security: Only allow acknowledging logs from their own department
        $logs = InspectionLog::whereIn('id', $request->ids)
                ->whereHas('employee', function($q) use ($user) {
                    $q->where('department_id', $user->department_id);
                })
                ->get();

        // Admin can acknowledge any department
        if ($user->isAdmin()) {
            $logs = InspectionLog::whereIn('id', $request->ids)->get();
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

        $request->validate([
            'ids' => 'required|array',
        ]);

        // Granular Approval: Update status to 'approved' for specific logs
        $approvedLogs = InspectionLog::with('session')->whereIn('id', $request->ids)->get();

        if ($approvedLogs->contains(fn ($log) => $log->session?->isLocked())) {
            return back()->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถอนุมัติเพิ่มได้ (Session Locked)');
        }

        InspectionLog::whereIn('id', $request->ids)->update([
            'verification_status' => 'approved',
            'verified_at' => now(), // Treat approval as a stamp
            'verifier_id' => Auth::id(),
        ]);

        // Lock session only when every log in the session is approved
        $sessionIds = $approvedLogs->pluck('session_id')->unique()->filter();
        $fullyLockedSessions = 0;

        foreach ($sessionIds as $sessionId) {
            $totalLogs = InspectionLog::where('session_id', $sessionId)->count();
            if ($totalLogs === 0) {
                continue;
            }

            $approvedCount = InspectionLog::where('session_id', $sessionId)
                ->where('verification_status', 'approved')
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

        // Gap 4: Send Line Notify to affected Dept Heads
        try {
            $failedLogs = InspectionLog::whereIn('id', $request->ids)
                            ->where('result', 'fail')
                            ->with(['employee.department', 'session'])
                            ->get();

            if ($failedLogs->isNotEmpty()) {
                $affectedDepts = $failedLogs->pluck('employee.department_id')->unique()->filter();
                
                $lineService = new \App\Services\LineNotifyService();
                
                foreach ($affectedDepts as $deptId) {
                    // Find Dept Manager (Level >= 5) with Line token
                    $managers = \App\Models\User::where('department_id', $deptId)
                                ->where('level', '>=', 5)
                                ->whereNotNull('line_token')
                                ->get();

                    $deptName = \App\Models\Department::find($deptId)?->dept_name ?? 'Unknown';
                    $failCount = $failedLogs->where('employee.department_id', $deptId)->count();
                    
                    $message = $lineService->buildHygieneAlertMessage($deptName, $failCount, now()->format('d/m/Y'));

                    $lineService->sendToUsers($managers, $message);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Line Notify failed in managerApprove: ' . $e->getMessage());
            // Don't fail the approval, just log the error
        }

        $message = $fullyLockedSessions > 0
            ? 'อนุมัติเรียบร้อย และล็อคเซสชันที่ครบทุกรายการแล้ว (Approved & Locked)'
            : 'อนุมัติรายการที่เลือกเรียบร้อย (ยังมีรายการอื่นในเซสชันที่รออนุมัติ)';

        return back()->with('success', $message);
    }

    private function getAutoShift()
    {
        $now = now();
        $time = $now->format('H:i:s');

        // Check DB first with Night Shift Logic (Cross-Midnight)
        $dbShift = \App\Models\Shift::where(function($q) use ($time) {
            // Normal shift (e.g., 08:00 - 17:00)
            $q->where('start_time', '<=', $time)
              ->where('end_time', '>=', $time);
        })->orWhere(function($q) use ($time) {
            // Night shift spanning midnight (e.g., 22:00 - 06:00)
            $q->where('start_time', '>', 'end_time')
              ->where(function($sub) use ($time) {
                  $sub->where('start_time', '<=', $time)
                      ->orWhere('end_time', '>=', $time);
              });
        })->first();

        if ($dbShift) {
            $name = strtolower($dbShift->shift_name);
            if (in_array($name, ['morning', 'afternoon', 'night'])) {
                return $name;
            }
        }

        // Hardcoded Fallback based on start times:
        // Morning: 06:00 - 12:59
        // Afternoon: 13:00 - 18:59
        // Night: 19:00 - 05:59
        $hour = $now->hour;
        if ($hour >= 6 && $hour < 13) {
            return 'morning';
        } elseif ($hour >= 13 && $hour < 19) {
            return 'afternoon';
        } else {
            return 'night';
        }
    }
}