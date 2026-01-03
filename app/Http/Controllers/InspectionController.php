<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image; // Laravel 11/Intervention 3

class InspectionController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        
        if ($user->department && $user->department->isGlobal()) {
            $departments = Department::all();
        } elseif ($user->department) {
            $departments = Department::where('id', $user->department_id)->get();
        } else {
             // Fallback for admin or unassigned
             $departments = Department::all();
        }

        return view('inspections.dashboard', compact('departments'));
    }

    public function getDepartmentStats(Request $request, Department $department)
    {
        $shift = $request->query('shift', 'morning'); // Default to morning if not sent
        $today = now()->toDateString();

        // 1. Get stats of Total Employees per Location (in this Dept)
        $locations = \App\Models\Location::withCount(['employees' => function ($query) use ($department) {
            $query->where('department_id', $department->id)->where('is_active', true);
        }])->get();

        // 2. Find existing session for Today + Shift + Dept to count "Inspected"
        $session = \App\Models\InspectionSession::where('department_id', $department->id)
                    ->where('inspection_date', $today)
                    ->where('shift', $shift)
                    ->first();

        $inspectedEmployeeIds = [];
        if ($session) {
            // Get list of employees who have *any* log in this session
            // Assuming one log entry means "Inspected" (or we can check for 'pass'/'fail')
            $inspectedEmployeeIds = \App\Models\InspectionLog::where('session_id', $session->id)
                                    ->pluck('employee_id')
                                    ->unique()
                                    ->toArray();
        }

        // 3. Map Inspection Data to Locations
        $locations->transform(function ($loc) use ($inspectedEmployeeIds) {
            // Filter employees in this location that are also in the inspected list
            // This requires us to know WHICH employees are in this location. 
            // The 'withCount' doesn't give us IDs. We need to query or verify.
            
            // Optimization: Since we need accurate counts, let's load employees briefly or do a subquery?
            // Better: Just fetch relation with 'select id' to be lightweight.
            $locEmployees = $loc->employees()
                            ->where('department_id', request()->route('department')->id)
                            ->where('is_active', true)
                            ->pluck('id')
                            ->toArray();

            $inspectedCount = count(array_intersect($locEmployees, $inspectedEmployeeIds));
            
            $loc->inspected_count = $inspectedCount;
            $loc->area_status = 'pending'; 
            
            // Cleanup: remove the full employee list from response to save bandwidth
            unset($loc->employees); 
            
            return $loc;
        });

        return response()->json([
            'success' => true,
            'locations' => $locations,
            'shift' => $shift,
            'session_exists' => !!$session
        ]);
    }

    public function startSession(Request $request)
    {
        $request->validate([
            'department_id' => 'required|exists:departments,id',
            'shift' => 'required|in:morning,afternoon,night',
        ]);

        // Smart Round Logic
        // 1. Find the *latest* session for this day/shift/department
        $latestSession = InspectionSession::where('department_id', $request->department_id)
            ->where('inspection_date', now()->toDateString())
            ->where('shift', $request->shift)
            ->latest('id')
            ->first();

        // 2. Decide: Resume or New Round?
        if ($latestSession) {
            // Check usage: Has it been used recently?
            $lastLog = $latestSession->logs()->latest('created_at')->first();
            
            // Criteria for New Round:
            // A. Session is explicitly 'completed' (future feature)
            // B. Last log was more than 60 minutes ago (assuming a round takes < 1 hr)
            // C. Session has logs but user is starting "fresh" (implied by button click after long gap)
            
            $isOldSession = $lastLog && $lastLog->created_at->diffInMinutes(now()) > 60;
            
            if (!$isOldSession) {
                // Resume existing session if it's recent or empty
                return redirect()->route('inspection.scan', $latestSession->id)
                    ->with('info', 'Returning to your active session (Round ' . $latestSession->round . ').');
            }
            
            // Start Next Round
            $nextRound = $latestSession->round + 1;
        } else {
            // First round of the shift
            $nextRound = 1;
        }

        // Create New Session
        $session = InspectionSession::create([
            'inspector_id' => Auth::id(),
            'department_id' => $request->department_id,
            'inspection_date' => now(),
            'shift' => $request->shift,
            'round' => $nextRound,
            'status' => 'draft'
        ]);

        return redirect()->route('inspection.scan', $session->id)
            ->with('success', 'Started Inspection Round ' . $nextRound);
    }
    
    public function scan(InspectionSession $session)
    {
        return view('inspections.scan', compact('session'));
    }

    public function showChecklist(InspectionSession $session, $hash)
    {
        // Allow lookup by QR Hash OR Human Readable Employee ID
        $employee = Employee::where('qr_code_hash', $hash)
                    ->orWhere('employee_id', $hash)
                    ->firstOrFail();

        // Security Check: Is employee in the session's department?
        if ($employee->department_id !== $session->department_id) {
            return redirect()->route('inspection.scan', $session->id)
                ->with('error', "Employee does not belong to this department!");
        }

        // Dynamic Loading: If employee has a location, load only those checkpoints.
        // If no location (or no mappings), fall back to all active checkpoints.
        if ($employee->location_id) {
            $checkpoints = $employee->location->checkpoints()->where('is_active', true)->get();
            
            // If the location has NO checkpoints mapped, fall back to global active ones
            if ($checkpoints->isEmpty()) {
                $checkpoints = Checkpoint::where('is_active', true)->get();
            }
        } else {
            $checkpoints = Checkpoint::where('is_active', true)->get();
        }
        
        // Load existing logs for today (if re-checking)
        $existingLogs = InspectionLog::where('session_id', $session->id)
            ->where('employee_id', $employee->id)
            ->get()
            ->keyBy('checkpoint_id');

        return view('inspections.form', compact('session', 'employee', 'checkpoints', 'existingLogs'));
    }

    public function storeLog(Request $request, InspectionSession $session)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'logs' => 'required|array',
            'logs.*.checkpoint_id' => 'required|exists:checkpoints,id',
            'logs.*.result' => 'required|in:pass,fail',
            // Conditional validation logic usually handled manually or with complex rules
        ]);

        $employee = Employee::findOrFail($request->employee_id);

        foreach ($request->logs as $checkpointId => $data) {
            // Check if photo required
            if ($data['result'] === 'fail') {
                if (empty($data['correction'])) {
                     return back()->with('error', 'Correction action is required for failed items.');
                }
                // Photo handling would go here (simplified for now)
                // In real app, we check $request->file("logs.$checkpointId.photo")
            }

            $logData = [
                'session_id' => $session->id,
                'employee_id' => $employee->id,
                'checkpoint_id' => $checkpointId,
                'result' => $data['result'],
                'correction_action' => $data['correction'] ?? null,
                'inspected_at' => now(),
                // Snapshotting
                'dept_snapshot' => $session->department->dept_name,
            ];

            // Handle Image Upload with Compression
            if ($request->hasFile("logs.$checkpointId.photo")) {
                $file = $request->file("logs.$checkpointId.photo");
                $filename = 'evidence_' . time() . '_' . $session->id . '_' . $checkpointId . '.webp';
                $path = 'evidence/' . $filename;

                try {
                    // 1. Read the image
                    $image = Image::read($file);

                    // 2. Resize (Scale width to 800px, maintain aspect ratio)
                    $image->scale(width: 800);

                    // 3. Encode to WebP with 75% quality
                    $encoded = $image->toWebp(quality: 75);

                    // 4. Save to Storage
                    Storage::disk('public')->put($path, $encoded);
                    
                    $logData['photo_path'] = $path;
                } catch (\Exception $e) {
                    // Fallback to original if compression fails
                    $path = $file->store('evidence', 'public');
                    $logData['photo_path'] = $path;
                }
            }

            InspectionLog::updateOrCreate(
                [
                    'session_id' => $session->id,
                    'employee_id' => $employee->id,
                    'checkpoint_id' => $checkpointId
                ],
                $logData
            );
        }

        return redirect()->route('inspection.scan', $session->id)
            ->with('success', 'Saved inspection for ' . $employee->fullname);
    }

    public function verification(Request $request)
    {
        $date = $request->input('date', date('Y-m-d'));

        $logs = InspectionLog::with(['employee', 'checkpoint', 'employee.department', 'location', 'machine', 'session.inspector']) // Added session.inspector
            ->whereDate('inspected_at', $date)
            ->orderBy('inspected_at', 'desc')
            ->get();

        $groupedInspections = $logs->groupBy(function($log) {
            // Group by SESSION_ID + Entity to separate rounds
            // Group Identifier: Employee ID (for people) or Machine ID (for machines)
            // Use Session Round to distinguish different rounds in the same day
            $key = $log->session_id . '_' . ($log->machine_id ? 'm_' . $log->machine_id : 'e_' . $log->employee_id);
            return $key;
        });

        $groupedInspections = $groupedInspections->map(function ($logsInGroup) use ($date) {
            $firstLog = $logsInGroup->first();
            $session = $firstLog->session;
            
            // Determine type and details
            $type = $firstLog->machine_id ? 'area' : 'person';
            $shift = $session->shift;
            $round = $session->round ?? 1; // Use DB Round
            $inspectorName = $session->inspector->name ?? 'Unknown';
            $shift = ucfirst($firstLog->session->shift ?? '-'); // Get Shift

            $employee = $firstLog->employee;
            $location = $firstLog->location;
            $machine = $firstLog->machine;
            // Alternative: Sort all grouped keys by time, then assign index.
            
            // To properly calculate "Round", we should perhaps count how many distinct sessions 
            // involved this entity on this date UP TO this current session.
            // Simplified: Just use the TIME as the differentiator for now, or calculate dynamically in the view?
            // No, user wants a number "Round 1, Round 2".
            
            // Let's rely on time sorting of the groups later?
            // Actually, we can just return the data now and sort/index locally in the view loop? 
            // But we might filter in view.
            
            // Query for Round Count:
            $queryBase = InspectionLog::whereDate('inspected_at', $firstLog->inspected_at->toDateString())
                            ->where('session_id', '<=', $firstLog->session_id); // Sessions before or equal this one
            
            $failedLogs = $logsInGroup->filter(function ($log) {
                return $log->result === 'fail';
            });
            $isPass = $failedLogs->isEmpty();

            if ($employee) {
                $type = 'person';
                $name = $employee->fullname;
                $subtext = $employee->department->dept_name ?? '-';
                $modalId = 'emp_' . $employee->id . '_sess_' . $firstLog->session_id;
                $imagePath = $employee->profile_image;
            } else {
                $type = 'area';
                $name = $location->location_name ?? 'Unknown Area';
                $subtext = 'Area Inspection';
                $modalId = 'loc_' . ($location->id ?? rand()) . '_sess_' . $firstLog->session_id;
                $imagePath = $location->image;
            }

            return (object) [
                'type' => $type,
                'name' => $name,
                'subtext' => $subtext,
                'modal_id' => $modalId,
                'image_path' => str_replace('storage/', '', $imagePath), // Ensure no double prefix
                'employee' => $employee,
                'machine' => $machine,
                'location' => $location,
                'inspector_name' => $inspectorName,
                'shift' => $shift,
                'round' => $round,
                'date' => $logsInGroup->max('inspected_at')->format('d/m/Y'),
                'time' => $logsInGroup->max('inspected_at')->format('H:i'),
                'status' => $isPass ? 'pass' : 'fail',
                'findings' => $failedLogs->values(),
                'all_logs' => $logsInGroup->values(), // Corrected from $logs
                'is_verified' => $logsInGroup->every(fn($l) => !is_null($l->verified_at)), // Corrected from $logs
                'log_ids' => $logsInGroup->pluck('id')->toArray(), // Corrected from $logs
            ];
        });

        return view('inspections.verification', compact('groupedInspections', 'date'));
    }
    public function approve(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'type' => 'required|string'
        ]);

        $ids = $request->ids;

        // Perform bulk update on InspectionLogs
        // We know the IDs of the logs we want to approve (passed from frontend)
        // OR we pass the group ID (emp_id, machine_id) and approved all logs for that target on that day.
        
        // Let's rely on receiving a list of LOG IDs to be safe and explicit.
        // Frontend will gather all log IDs in the group.

        InspectionLog::whereIn('id', $ids)->update([
            'verified_at' => now(),
            'verifier_id' => Auth::id()
        ]);

        return back()->with('success', 'Approved successfully.');
    }
}
