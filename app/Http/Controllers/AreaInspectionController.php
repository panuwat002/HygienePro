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
        // Check if this location has machines
        $machines = $location->machines()->where('is_active', true)->get();

        // If specific machine is requested OR location has no machines, proceed to checklist
        $machineId = $request->query('machine_id');
        $machine = $machineId ? \App\Models\Machine::find($machineId) : null;

        if ($machines->count() > 0 && !$machine) {
            return view('inspections.select_machine', compact('department', 'location', 'machines'));
        }

        // 1. Get Checkpoints
        // If Machine is selected, get Machine's checkpoints
        // Else get Location's checkpoints (legacy/area behavior)
        if ($machine) {
            $checkpoints = $machine->checkpoints()
                            ->where('is_active', true)
                            ->with('category')
                            ->get()
                            ->groupBy('category.name');
        } else {
            $checkpoints = $location->checkpoints()
                            ->where('type', 'area')
                            ->where('is_active', true)
                            ->with('category')
                            ->get()
                            ->groupBy('category.name');
        }

        // 2. Determine Session
        $shift = $this->determineShift();
        $today = now()->toDateString();
        
        $session = InspectionSession::firstOrCreate(
            [
                'department_id' => $department->id,
                'inspection_date' => $today,
                'shift' => $shift,
            ],
            [
                'inspector_id' => Auth::id(),
                'status' => 'in_progress'
            ]
        );

        // 3. Get existing logs
        $query = InspectionLog::where('session_id', $session->id)
                        ->where('location_id', $location->id);
        
        if ($machine) {
            $query->where('machine_id', $machine->id);
        } else {
            $query->whereNull('machine_id'); // Only non-machine area logs
        }

        $existingLogs = $query->get()->keyBy('checkpoint_id');

        return view('inspections.area_checklist', compact('department', 'location', 'checkpoints', 'session', 'existingLogs', 'machine'));
    }

    /**
     * Store the inspection result.
     */
    public function store(Request $request, InspectionSession $session, Location $location)
    {
        $request->validate([
            'results' => 'required|array',
            'results.*' => 'in:pass,fail',
        ]);

        $machineId = $request->input('machine_id');

        foreach ($request->results as $checkpointId => $result) {
            $note = $request->input("notes.$checkpointId");
            
            // Validation: logic (If Fail, Correction is generally expected)
            // But we can be lenient or strict. Let's strict as per user request implicit.
            /*
            if ($result === 'fail' && empty($note)) {
                // Ideally this would be validated earlier, but array validation in Laravel is cleaner with form requests.
                // For now, allow save but maybe mark logic? Or just save null.
            }
            */
            
            $logData = [
                'session_id' => $session->id,
                'location_id' => $location->id,
                'machine_id' => $machineId,
                'checkpoint_id' => $checkpointId,
                'employee_id' => null,
                'result' => $result,
                'correction_action' => $note, // Save note as correction_action
                'inspected_at' => now(),
                'checkpoint_title_snapshot' => Checkpoint::find($checkpointId)->title,
                'dept_snapshot' => $session->department->dept_name,
            ];

            // Handle Photo Upload
            if ($request->hasFile("photos.$checkpointId")) {
                $file = $request->file("photos.$checkpointId");
                $filename = 'evidence_' . time() . '_' . $session->id . '_' . $checkpointId . '.webp';
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

            InspectionLog::updateOrCreate(
                [
                    'session_id' => $session->id,
                    'location_id' => $location->id,
                    'machine_id' => $machineId,
                    'checkpoint_id' => $checkpointId,
                ],
                $logData
            );
        }
        
        // Handle failed items with correction actions if needed (can be added later)

        return redirect()->route('inspection.dashboard')
            ->with('success', 'บันทึกผลการตรวจพื้นที่เรียบร้อยแล้ว');
    }

    private function determineShift()
    {
        $hour = now()->hour;
        if ($hour >= 6 && $hour < 14) return 'morning';
        if ($hour >= 14 && $hour < 22) return 'afternoon';
        return 'night';
    }
}
