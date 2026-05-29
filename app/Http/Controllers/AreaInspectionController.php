<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class AreaInspectionController extends Controller
{
    /**
     * Show the area/machine checklist for a specific location.
     */
    public function showChecklist(Department $department, Location $location, Request $request)
    {
        // For backwards compatibility or direct access, we just redirect or handle as bulk with one target
        return $this->showBulkChecklist($department, $request, "loc:{$location->id}");
    }

    public function showBulkChecklist(Department $department, Request $request, $targets = null)
    {
        $targets = $targets ?? $request->query('targets');
        if (!$targets) {
            return redirect()->route('inspection.dashboard', 'area')->with('error', 'กรุณาเลือกพื้นที่หรือเครื่องจักรที่ต้องการตรวจ');
        }

        $targetArray = explode(',', $targets);
        $inspectionData = [];

        // Determine Session
        $sessionId = $request->query('session');
        if ($sessionId) {
            $session = InspectionSession::find($sessionId);
        } else {
            $shift = $this->determineShift();
            $today = now()->toDateString();
            $session = InspectionSession::where('department_id', $department->id)
                        ->where('inspection_date', $today)
                        ->where('shift', $shift)
                        ->where('type', 'area')
                        ->latest('id')
                        ->first();
        }

        if (!$session) {
            return redirect()->route('inspection.dashboard', 'area')
                ->with('error', 'ไม่พบเซสชันการตรวจ กรุณาเริ่มเซสชันใหม่');
        }

        if ($session->isLocked()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        $recleanFixMode = $session->status === 'completed' && $session->hasPendingRecleans();

        if ($session->status === 'completed' && !$recleanFixMode) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้จบงานแล้ว กรุณาเริ่มรอบตรวจใหม่');
        }

        foreach ($targetArray as $t) {
            $parts = explode(':', $t);
            if (count($parts) !== 2) continue;

            $type = $parts[0];
            $id = $parts[1];

            $location = null;
            $machine = null;
            $checkpoints = null;
            $targetName = "";
            $targetSubtext = "";
            $targetType = "";

            if ($type === 'loc') {
                $location = Location::find($id);
                if (!$location) continue;
                $targetName = $location->location_name;
                $targetSubtext = "General Area";
                $targetType = "loc";
                
                $checkpoints = $location->checkpoints()
                    ->where('type', 'area')
                    ->where('is_active', true)
                    ->with('category')
                    ->get()
                    ->groupBy('category.name');
            } else if ($type === 'machine') {
                $machine = \App\Models\Machine::with('location')->find($id);
                if (!$machine) continue;
                $location = $machine->location;
                // BUG-006 Fix: Null Safe Access
                $targetName = $machine->name;
                $targetSubtext = $location?->location_name ?? 'Unknown Location';
                $targetType = "machine";

                $checkpoints = $machine->checkpoints()
                    ->where('is_active', true)
                    ->with('category')
                    ->get()
                    ->groupBy('category.name');
            }

            // Existing logs for this target
            $query = InspectionLog::where('session_id', $session->id)
                        ->where('location_id', $location?->id); // Safe access if location is null (though filtered above)
            if ($machine) {
                $query->where('machine_id', $machine->id);
            } else {
                $query->whereNull('machine_id');
            }
            $existingLogs = $query->get()->keyBy('checkpoint_id');

            // Re-clean requests
            if ($location) {
                $recleanQuery = InspectionLog::where('location_id', $location->id)
                                ->where('verification_status', 'reclean')
                                ->whereDoesntHave('rechecks');
                if ($machine) {
                    $recleanQuery->where('machine_id', $machine->id);
                } else {
                    $recleanQuery->whereNull('machine_id');
                }
                $recleanRequests = $recleanQuery->get()->keyBy('checkpoint_id');
            } else {
                $recleanRequests = collect();
            }

            $inspectionData[] = (object)[
                'target_type' => $targetType,
                'target_id' => $id,
                'location' => $location,
                'machine' => $machine,
                'name' => $targetName,
                'subtext' => $targetSubtext,
                'checkpoints' => $checkpoints,
                'existing_logs' => $existingLogs,
                'reclean_requests' => $recleanRequests
            ];
        }

        return view('inspections.area_checklist', compact('department', 'session', 'inspectionData', 'targets', 'recleanFixMode'));
    }

    /**
     * Store the inspection result.
     */
    public function store(Request $request, InspectionSession $session, Location $location)
    {
        // For backwards compatibility, wrap in bulk-style logic
        $targetKey = $request->has('machine_id') ? "machine:".$request->machine_id : "loc:".$location->id;
        $bulkResults = ["targets" => [$targetKey => $request->results]];
        $bulkNotes = $request->has('notes') ? [$targetKey => $request->notes] : [];
        $bulkPhotos = $request->has('photos') ? [$targetKey => $request->photos] : [];
        
        // We'll just manually call our logic or redirect to a new storeBulk if we want consistency.
        // Let's implement storeBulk as the main handler.
        return $this->storeBulk($request, $session);
    }

    public function storeBulk(Request $request, InspectionSession $session)
    {
        if ($session->isLocked()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        $recleanFixMode = $session->status === 'completed' && $session->hasPendingRecleans();

        if ($session->status === 'completed' && !$recleanFixMode) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้จบงานไปแล้ว ไม่สามารถบันทึกเพิ่มได้');
        }

        // Structure: results[targets][loc:1][cp_id] = pass/fail
        // Structure: notes[loc:1][cp_id] = text
        // Structure: photos[loc:1][cp_id] = file

        $targetResults = $request->input('results.targets', []);

        // BUG-008 Fix: Pre-fetch checkpoint titles to avoid finding in loop
        $allCheckpointIds = [];
        foreach ($targetResults as $results) {
            foreach(array_keys($results) as $cpId) {
                $allCheckpointIds[] = $cpId;
            }
        }
        $checkpointTitles = Checkpoint::whereIn('id', array_unique($allCheckpointIds))->pluck('title', 'id');
        
        foreach ($targetResults as $targetKey => $results) {
            $parts = explode(':', $targetKey);
            $type = $parts[0];
            $targetId = $parts[1];

            $locationId = null;
            $machineId = null;

            if ($type === 'loc') {
                $locationId = $targetId;
            } else {
                $machine = \App\Models\Machine::find($targetId);
                $machineId = $targetId;
                $locationId = $machine->location_id;
            }

            foreach ($results as $checkpointId => $result) {
                if (!$result) continue;

                if ($recleanFixMode) {
                    $pendingReclean = InspectionLog::where('location_id', $locationId)
                        ->when($machineId, fn($q) => $q->where('machine_id', $machineId), fn($q) => $q->whereNull('machine_id'))
                        ->where('checkpoint_id', $checkpointId)
                        ->where('verification_status', 'reclean')
                        ->whereDoesntHave('rechecks')
                        ->exists();

                    if (!$pendingReclean) {
                        return back()->with('error', 'รายการนี้ไม่ได้อยู่ในสถานะ Re-clean ไม่สามารถบันทึกได้');
                    }
                }

                $note = $request->input("notes.$targetKey.$checkpointId");

                // Validation: Enforce Photo & Note on Fail
                if ($result === 'fail') {
                    if (empty($note)) {
                         return back()->with('error', 'กรุณาระบุสาเหตุที่ไม่ผ่าน / การแก้ไข (Correction) สำหรับรายการที่ไม่ผ่าน');
                    }
                    if (!$request->hasFile("photos.$targetKey.$checkpointId")) {
                        $cpTitle = $checkpointTitles[$checkpointId] ?? 'รายการที่ไม่ผ่าน';
                         return back()->with('error', "กรุณาถ่ายรูปหลักฐาน (Evidence Photo) สำหรับ: $cpTitle");
                    }
                }
                
                $logData = [
                    'session_id' => $session->id,
                    'location_id' => $locationId,
                    'machine_id' => $machineId,
                    'checkpoint_id' => $checkpointId,
                    'employee_id' => null,
                    'result' => $result,
                    'correction_action' => $note,
                    'inspected_at' => now(),
                    'checkpoint_title_snapshot' => $checkpointTitles[$checkpointId] ?? 'Unknown Checkpoint',

                    'dept_snapshot' => $session->department->dept_name,
                    'verified_at' => null,
                    'verifier_id' => null,
                    'verification_status' => null,
                    'verification_comment' => null,
                ];

                // Handle Photo Upload (Target-prefixed)
                if ($request->hasFile("photos.$targetKey.$checkpointId")) {
                    $file = $request->file("photos.$targetKey.$checkpointId");
                    $filename = 'evidence_' . time() . '_' . $session->id . '_' . $targetId . '_' . $checkpointId . '.webp';
                    $path = 'evidence/' . $filename;

                    try {
                        $image = Image::read($file);
                        $image->scale(width: 800);
                        $encoded = $image->toWebp(quality: 75);
                        Storage::disk('public')->put($path, $encoded);
                        $logData['photo_path'] = $path;
                    } catch (\Exception $e) {
                        $path = $file->store('evidence', 'public');
                        $logData['photo_path'] = $path;
                    }
                }

                // Parent Log (Re-clean logic)
                $pendingLog = InspectionLog::where('location_id', $locationId)
                    ->where('machine_id', $machineId)
                    ->where('checkpoint_id', $checkpointId)
                    ->where('verification_status', 'reclean')
                    ->whereDoesntHave('rechecks')
                    ->orderBy('inspected_at', 'desc')
                    ->first();

                if ($pendingLog) {
                    $isSameSessionRow = $pendingLog->session_id === $session->id
                        && (int) $pendingLog->location_id === (int) $locationId
                        && (int) $pendingLog->machine_id === (int) $machineId
                        && (int) $pendingLog->checkpoint_id === (int) $checkpointId;

                    if (!$isSameSessionRow) {
                        $logData['parent_id'] = $pendingLog->id;
                    }
                }

                InspectionLog::updateOrCreate(
                    [
                        'session_id' => $session->id,
                        'location_id' => $locationId,
                        'machine_id' => $machineId,
                        'checkpoint_id' => $checkpointId,
                    ],
                    $logData
                );
            }
        }

        // Check remaining targets to Auto-Complete
        $remainingCount = 0;
        
        // Recalculate remaining based on type (Logic matches Dashboard)
        if ($session->type === 'area') {
            // Count Valid Locations (Must have active area checkpoints)
            $totalTargets = Location::whereHas('checkpoints', function($q) {
                $q->where('type', 'area')->where('is_active', true);
            })->count();

            $inspectedCount = InspectionLog::where('session_id', $session->id)
                ->whereNull('machine_id')
                ->distinct('location_id')
                ->count('location_id');
            $remainingCount = max(0, $totalTargets - $inspectedCount);
            
        } elseif ($session->type === 'machine') {
            // Count Valid Machines (Must have active checkpoints)
            $totalTargets = \App\Models\Machine::where('is_active', true)
                ->whereHas('checkpoints', function($q) {
                    $q->where('is_active', true);
                })->count();

            $inspectedCount = InspectionLog::where('session_id', $session->id)
                ->whereNotNull('machine_id')
                ->distinct('machine_id')
                ->count('machine_id');
            $remainingCount = max(0, $totalTargets - $inspectedCount);
        }

        // Re-clean fixes must not auto-complete or start a new inspection round
        if ($recleanFixMode) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('success', 'บันทึกการแก้ไข Re-clean เรียบร้อยแล้ว');
        }

        // Check for Force Finish or Natural Completion
        if ($remainingCount === 0 || $request->input('save_action') === 'finish') {
            $session->update([
                'status' => 'completed',
            ]);
            $msg = 'บันทึกผลเรียบร้อยและจบงานแล้ว (Session Completed)';
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('success', $msg);
        } else {
            $msg = 'บันทึกข้อมูลเครื่องจักรเรียบร้อย (เหลือ ' . $remainingCount . ' รายการ)';
            return back()->with('success', $msg);
        }
    }

    private function determineShift()
    {
        $hour = now()->hour;
        if ($hour >= 6 && $hour < 14) return 'morning';
        if ($hour >= 14 && $hour < 22) return 'afternoon';
        return 'night';
    }
}
