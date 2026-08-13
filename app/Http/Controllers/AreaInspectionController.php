<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Thrown from inside a bulk transaction when server-side validation of a single
 * checkpoint fails. Caught at the top of storeBulk so the whole transaction
 * rolls back — no half-written logs or CARs.
 */
class BulkValidationException extends \RuntimeException {}

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
            // Fix #12: The 'inspection_date' column has a 'date' cast on the model, so
            // Laravel writes it as 'Y-m-d 00:00:00' text on SQLite. A raw ->where()
            // against a 'Y-m-d' string then misses those rows. whereDate() coerces
            // both sides to the date-only form and works in every driver we target.
            $session = InspectionSession::where('department_id', $department->id)
                        ->whereDate('inspection_date', $today)
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
                
                // Skip empty locations
                if ($location->checkpoints()->count() == 0 && 
                    $location->machines()->count() == 0) {
                    continue;
                }

                $targetName = "การจัดการพื้นที่ (Area)";
                $targetSubtext = "จุดตรวจความสะอาดและพื้นที่ของ " . $location->location_name;
                $targetType = "loc";
                
                $checkpoints = $location->checkpoints()
                    ->where('type', 'area')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('checkpoints.id')
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
                    ->orderBy('sort_order')
                    ->orderBy('checkpoints.id')
                    ->with('category')
                    ->get()
                    ->groupBy('category.name');
            } else {
                // Skip unknown target types (e.g. 'shift:night') that are metadata,
                // not inspectable targets — they have no checkpoints to render.
                continue;
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
        if ($session->inspector_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403, 'Unauthorized: not session owner');
        }

        if ($session->isLocked()) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้ (Session Locked)');
        }

        $recleanFixMode = $session->status === 'completed' && $session->hasPendingRecleans();

        if ($session->status === 'completed' && !$recleanFixMode) {
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('error', 'เซสชันนี้จบงานไปแล้ว ไม่สามารถบันทึกเพิ่มได้');
        }

        // Anti-Cheat (Speed Trap): Level 1
        // Reject the submission when the previous per-checkpoint save by the same
        // inspector landed less than $minSeconds ago — prevents walking past
        // machines and swiping "pass" without reading. Only applies to the
        // per-checkpoint path (this method); the bulk-pass / bulk-no-production
        // endpoints are legitimate one-tap actions and are not throttled here.
        // The threshold is tunable via INSPECTION_SPEED_TRAP_SECONDS so QA can
        // relax it if inspectors report false positives from realistic pacing.
        //
        // Fix #9: Scope the trap to THIS session's logs. The prior query used
        // whereHas('session', ...) which turned every area/machine save into a
        // subquery across every log the inspector had ever created. Rapid
        // tap-through only makes sense inside a single active session, so the
        // narrower query is both cheaper and semantically closer to the intent.
        $minSeconds = (int) env('INSPECTION_SPEED_TRAP_SECONDS', 5);
        $lastLog = \App\Models\InspectionLog::where('session_id', $session->id)
            ->where(function ($q) {
                $q->whereNotNull('location_id')->orWhereNotNull('machine_id');
            })
            ->latest('created_at')
            ->first();

        if ($lastLog && $lastLog->created_at->diffInSeconds(now()) < $minSeconds) {
            \Illuminate\Support\Facades\Log::warning('Speed Trap Triggered (Area/Machine)', [
                'inspector_id' => $session->inspector_id,
                'session_id' => $session->id,
                'time_diff' => $lastLog->created_at->diffInSeconds(now()),
                'threshold' => $minSeconds,
                'ip' => $request->ip()
            ]);

            $msg = "คุณทำรายการเร็วเกินไป กรุณารอสักครู่ (ประมาณ {$minSeconds} วินาที) แล้วกดบันทึกใหม่อีกครั้ง";
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 429);
            }

            return redirect()->back()->with('error', $msg);
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

        // Collect CAR notifications inside the transaction and dispatch AFTER commit,
        // so a rollback never leaks emails to managers about work that didn't save.
        $pendingCarNotifications = [];
        // Old photo files to delete after the transaction commits — we only want to
        // touch disk once we're sure the DB write survived.
        $oldPhotosToDelete = [];

        try {
            DB::transaction(function () use (
                $request, $session, $recleanFixMode, $targetResults, $checkpointTitles,
                &$pendingCarNotifications, &$oldPhotosToDelete
            ) {
                $this->processBulkTargets(
                    $request, $session, $recleanFixMode, $targetResults, $checkpointTitles,
                    $pendingCarNotifications, $oldPhotosToDelete
                );
            });
        } catch (BulkValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            }
            return back()->with('error', $e->getMessage());
        }

        foreach ($oldPhotosToDelete as $oldPath) {
            Storage::disk('public')->delete($oldPath);
        }
        foreach ($pendingCarNotifications as [$managers, $notification]) {
            foreach ($managers as $manager) {
                $manager->notify($notification);
            }
        }

        return $this->finalizeBulkResponse($request, $session, $recleanFixMode);
    }

    /**
     * Per-target processing loop, extracted so the transaction body stays focused.
     * Any user-facing validation failure raises BulkValidationException, which
     * the caller catches to return the correct 400 response.
     */
    private function processBulkTargets(
        Request $request,
        InspectionSession $session,
        bool $recleanFixMode,
        array $targetResults,
        $checkpointTitles,
        array &$pendingCarNotifications,
        array &$oldPhotosToDelete
    ): void {
        // Fix #11: Pre-fetch every reclean-pending log that could match any
        // (location, machine, checkpoint) combo we're about to write. The
        // per-checkpoint loop used to hit InspectionLog twice per iteration —
        // once for the reclean-mode guard and once for parent-log linking —
        // and each query included a whereDoesntHave subquery. Batching drops
        // that to a single query indexed by "loc|mac|cp" for O(1) lookup below.
        $allCheckpointIds = collect($targetResults)
            ->flatMap(fn ($r) => array_keys($r))
            ->unique()
            ->all();
        $pendingRecleanIndex = collect();
        if (!empty($allCheckpointIds)) {
            $pendingRecleanIndex = InspectionLog::whereIn('checkpoint_id', $allCheckpointIds)
                ->where('verification_status', 'reclean')
                ->whereDoesntHave('rechecks')
                ->orderBy('inspected_at', 'desc')
                ->get()
                ->keyBy(fn ($log) => sprintf(
                    '%s|%s|%s',
                    $log->location_id ?? 'null',
                    $log->machine_id ?? 'null',
                    $log->checkpoint_id
                ));
        }

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
                if (!$machine) continue;
                $machineId = $targetId;
                $locationId = $machine->location_id;
            }

            foreach ($results as $checkpointId => $result) {
                if (!$result) continue;

                $recleanKey = sprintf('%s|%s|%s', $locationId ?? 'null', $machineId ?? 'null', $checkpointId);
                $pendingLogRow = $pendingRecleanIndex->get($recleanKey);

                if ($recleanFixMode) {
                    if (!$pendingLogRow) {
                        throw new BulkValidationException('รายการนี้ไม่ได้อยู่ในสถานะ Re-clean ไม่สามารถบันทึกได้');
                    }
                }

                $note = $request->input("notes.$targetKey.$checkpointId");

                // Validation: Enforce Photo & Note on Fail
                if ($result === 'fail') {
                    if (empty($note)) {
                        throw new BulkValidationException('กรุณาระบุสาเหตุที่ไม่ผ่าน / การแก้ไข (Correction) สำหรับรายการที่ไม่ผ่าน');
                    }
                    if (!$request->hasFile("photos.$targetKey.$checkpointId")) {
                        $cpTitle = $checkpointTitles[$checkpointId] ?? 'รายการที่ไม่ผ่าน';
                        throw new BulkValidationException("กรุณาถ่ายรูปหลักฐาน (Evidence Photo) สำหรับ: $cpTitle");
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

                // Loop Engineering: Auto-CAR triggers when fail
                if ($result === 'fail') {
                    $logData['verification_status'] = 'reclean';
                }

                // Fix #4: Look up any existing photo before writing the new one, so the
                // stale file can be cleaned up after the transaction commits. We defer
                // the actual Storage::delete until after commit — deleting inside the
                // transaction would leave orphaned files if we rolled back.
                $existingPhotoPath = InspectionLog::where('session_id', $session->id)
                    ->where('location_id', $locationId)
                    ->where('machine_id', $machineId)
                    ->where('checkpoint_id', $checkpointId)
                    ->value('photo_path');

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

                    if ($existingPhotoPath && $existingPhotoPath !== $path) {
                        $oldPhotosToDelete[] = $existingPhotoPath;
                    }
                }

                // Parent Log (Re-clean logic) — reuse the pre-fetched index from Fix #11.
                $pendingLog = $pendingLogRow;

                if ($pendingLog) {
                    $isSameSessionRow = $pendingLog->session_id === $session->id
                        && (int) $pendingLog->location_id === (int) $locationId
                        && (int) $pendingLog->machine_id === (int) $machineId
                        && (int) $pendingLog->checkpoint_id === (int) $checkpointId;

                    if (!$isSameSessionRow) {
                        $logData['parent_id'] = $pendingLog->id;
                    }
                }

                $log = InspectionLog::updateOrCreate(
                    [
                        'session_id' => $session->id,
                        'location_id' => $locationId,
                        'machine_id' => $machineId,
                        'checkpoint_id' => $checkpointId,
                    ],
                    $logData
                );

                // Loop Engineering: Auto-CAR creation.
                // Fix #7: Per-CAR notifications are suppressed here — the manager gets a
                // single SessionCarsSummaryNotification when the session completes
                // (see finalizeBulkResponse). $pendingCarNotifications is retained but
                // stays empty on the auto path; it's still useful if a future manual
                // escalation path decides to enqueue immediate notices.
                if ($log->result === 'fail') {
                    \App\Models\CorrectiveAction::firstOrCreate([
                        'inspection_log_id' => $log->id,
                    ], [
                        'status' => 'open',
                        'escalated_by' => $session->inspector_id,
                        'root_cause' => $log->correction_action ?? 'ระบบสั่งแก้ไขอัตโนมัติ เนื่องจากผลการตรวจไม่ผ่าน',
                        'due_date' => now()->addHours(24),
                    ]);
                }
            }
        }
    }

    /**
     * Post-transaction: pick between auto-complete, force-finish, and continue-session
     * responses. Kept out of the transaction body because a rollback should not have
     * flipped session status yet.
     */
    private function finalizeBulkResponse(Request $request, InspectionSession $session, bool $recleanFixMode)
    {
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
            // Fix #7: One summary notification of every auto-CAR opened during
            // this session, instead of one bell entry per fail.
            app(\App\Services\InspectionService::class)->notifyManagersOfSessionCars($session);
            $msg = 'บันทึกผลเรียบร้อยและจบงานแล้ว (Session Completed)';
            if ($request->wantsJson()) return response()->json(['success' => true, 'message' => $msg]);
            return redirect()->route('inspection.dashboard', $session->type)
                ->with('success', $msg);
        } else {
            $msg = 'บันทึกข้อมูลเครื่องจักรเรียบร้อย (เหลือ ' . $remainingCount . ' รายการ)';
            if ($request->wantsJson()) return response()->json(['success' => true, 'message' => $msg]);
            return back()->with('success', $msg);
        }
    }

    /**
     * Fix #13: Delegate to Shift::detectCurrent() so this controller and
     * InspectionController agree on which shift "now" belongs to.
     * The previous local implementation used hard-coded hour ranges
     * (6-14 / 14-22 / else), which drifted from the DB-driven definition
     * once the shifts table was tuned.
     */
    private function determineShift(): string
    {
        return \App\Models\Shift::detectCurrent();
    }

    /**
     * Store bulk no_production for all targets in a specific location
     */
    public function storeBulkNoProduction(Request $request, InspectionSession $session, $locationId)
    {
        if ($session->inspector_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: not session owner'], 403);
        }

        if ($session->isLocked()) {
            return response()->json(['success' => false, 'message' => 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้'], 400);
        }

        // Fix #2: Bulk actions must not fire during re-clean, otherwise they would
        // overwrite the reclean tag and hide the fact that a Supervisor asked for a
        // fix. Only the per-checkpoint save path is allowed to touch reclean rows.
        if ($session->status === 'completed' && $session->hasPendingRecleans()) {
            return response()->json([
                'success' => false,
                'message' => 'โหมด Re-clean บันทึกได้เฉพาะรายการที่ถูกสั่งแก้เท่านั้น ไม่สามารถทำ Bulk "ไม่มีผลิต" ได้',
            ], 400);
        }

        $targetsQuery = $request->input('targets_query');
        if (!$targetsQuery) {
            return response()->json(['success' => false, 'message' => 'ไม่พบข้อมูลเป้าหมายการตรวจ'], 400);
        }

        // Fix #1: A log the Supervisor has already touched (verification_status != null —
        // verified, reclean, approved, rejected) must not be silently converted to
        // no_production; that would erase their review. Refuse the whole batch and let
        // the Inspector clean up manually.
        $reviewedExists = InspectionLog::where('session_id', $session->id)
            ->where('location_id', $locationId)
            ->whereNotNull('verification_status')
            ->exists();
        if ($reviewedExists) {
            return response()->json([
                'success' => false,
                'message' => 'มีรายการที่ Supervisor ตรวจแล้วในห้องนี้ ไม่สามารถเปลี่ยนเป็น "ไม่มีผลิต" แบบทั้งกลุ่มได้',
            ], 400);
        }

        $targetArray = explode(',', $targetsQuery);
        $logsCreated = 0;
        DB::beginTransaction();
        try {

        foreach ($targetArray as $t) {
            $parts = explode(':', $t);
            if (count($parts) !== 2) continue;

            $type = $parts[0];
            $id = $parts[1];

            $targetLocationId = null;
            $machineId = null;
            $checkpoints = collect();

            if ($type === 'loc') {
                $loc = Location::find($id);
                if (!$loc || $loc->id != $locationId) continue;
                $targetLocationId = $loc->id;
                
                $checkpoints = $loc->checkpoints()
                    ->where('type', 'area')
                    ->where('is_active', true)
                    ->get();

                // Loop Engineering Fix: Auto-apply no_production to all machines inside this location
                $machines = $loc->machines()->where('is_active', true)->get();
                foreach ($machines as $mac) {
                    $macCheckpoints = $mac->checkpoints()->where('is_active', true)->get();
                    foreach ($macCheckpoints as $mCp) {
                        $existingLog = \App\Models\InspectionLog::where('session_id', $session->id)
                            ->where('location_id', $targetLocationId)
                            ->where('machine_id', $mac->id)
                            ->where('checkpoint_id', $mCp->id)
                            ->first();
                        
                        if ($existingLog) {
                            // Loop Engineering Fix: Overwrite existing log to no_production
                            $existingLog->update([
                                'result' => 'no_production',
                                'inspected_at' => now(),
                                'employee_id' => null,
                                'correction_action' => null,
                            ]);
                            $logsCreated++;
                        } else {
                            \App\Models\InspectionLog::create([
                                'session_id' => $session->id,
                                'location_id' => $targetLocationId,
                                'machine_id' => $mac->id,
                                'checkpoint_id' => $mCp->id,
                                'employee_id' => null,
                                'result' => 'no_production',
                                'correction_action' => null,
                                'inspected_at' => now(),
                                'checkpoint_title_snapshot' => $mCp->title,
                                'dept_snapshot' => $session->department->dept_name,
                                'verified_at' => null,
                                'verifier_id' => null,
                                'verification_status' => null,
                                'verification_comment' => null,
                            ]);
                            $logsCreated++;
                        }
                    }
                }
            } else if ($type === 'machine') {
                $machine = \App\Models\Machine::find($id);
                if (!$machine || $machine->location_id != $locationId) continue;
                $targetLocationId = $machine->location_id;
                $machineId = $machine->id;

                $checkpoints = $machine->checkpoints()
                    ->where('is_active', true)
                    ->get();
            }

            foreach ($checkpoints as $cp) {
                // Check if a log already exists
                $existingLog = \App\Models\InspectionLog::where('session_id', $session->id)
                    ->where('location_id', $targetLocationId)
                    ->where('machine_id', $machineId)
                    ->where('checkpoint_id', $cp->id)
                    ->first();
                
                // Loop Engineering Fix: Overwrite existing log to no_production
                if ($existingLog) {
                    $existingLog->update([
                        'result' => 'no_production',
                        'inspected_at' => now(),
                        'employee_id' => null,
                        'correction_action' => null,
                    ]);
                    $logsCreated++;
                } else {
                    // Create log with no_production result
                    InspectionLog::create(
                        [
                            'session_id' => $session->id,
                            'location_id' => $targetLocationId,
                            'machine_id' => $machineId,
                            'checkpoint_id' => $cp->id,
                            'employee_id' => null,
                            'result' => 'no_production',
                            'correction_action' => null,
                            'inspected_at' => now(),
                            'checkpoint_title_snapshot' => $cp->title,
                            'dept_snapshot' => $session->department->dept_name,
                            'verified_at' => null,
                            'verifier_id' => null,
                            'verification_status' => null,
                            'verification_comment' => null,
                        ]
                    );
                    $logsCreated++;
                }
            }
        }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => "อัพเดทสถานะไม่มีการผลิต จำนวน {$logsCreated} รายการ"
        ]);
    }

    /**
     * Store bulk pass for all targets in a specific location
     */
    public function storeBulkPass(Request $request, InspectionSession $session, $locationId)
    {
        if ($session->inspector_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: not session owner'], 403);
        }

        if ($session->isLocked()) {
            return response()->json(['success' => false, 'message' => 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้'], 400);
        }

        // Fix #2: Same reasoning as storeBulkNoProduction — bulk-pass during re-clean
        // would erase reclean tags that Supervisor set.
        if ($session->status === 'completed' && $session->hasPendingRecleans()) {
            return response()->json([
                'success' => false,
                'message' => 'โหมด Re-clean บันทึกได้เฉพาะรายการที่ถูกสั่งแก้เท่านั้น ไม่สามารถทำ Bulk "ผ่านทั้งหมด" ได้',
            ], 400);
        }

        $targetsQuery = $request->input('targets_query');
        if (!$targetsQuery) {
            return response()->json(['success' => false, 'message' => 'ไม่พบข้อมูลเป้าหมายการตรวจ'], 400);
        }

        $targetArray = explode(',', $targetsQuery);
        $logsCreated = 0;
        DB::beginTransaction();
        try {

        foreach ($targetArray as $t) {
            $parts = explode(':', $t);
            if (count($parts) !== 2) continue;

            $type = $parts[0];
            $id = $parts[1];

            $targetLocationId = null;
            $machineId = null;
            $checkpoints = collect();

            if ($type === 'loc') {
                $loc = Location::find($id);
                if (!$loc || $loc->id != $locationId) continue;
                $targetLocationId = $loc->id;
                
                $checkpoints = $loc->checkpoints()
                    ->where('type', 'area')
                    ->where('is_active', true)
                    ->get();
            } else if ($type === 'machine') {
                $machine = \App\Models\Machine::find($id);
                if (!$machine || $machine->location_id != $locationId) continue;
                $targetLocationId = $machine->location_id;
                $machineId = $machine->id;

                $checkpoints = $machine->checkpoints()
                    ->where('is_active', true)
                    ->get();
            }

            foreach ($checkpoints as $cp) {
                // Check if a log already exists
                $existingLog = \App\Models\InspectionLog::where('session_id', $session->id)
                    ->where('location_id', $targetLocationId)
                    ->where('machine_id', $machineId)
                    ->where('checkpoint_id', $cp->id)
                    ->first();
                
                // เงื่อนไขพิเศษ: ถ้ามีข้อมูลอยู่แล้ว (เช่น ติ๊ก ไม่มีผลิต หรือ ไม่ผ่าน ไปแล้ว) ให้ข้ามไปเลย ไม่ต้องเขียนทับ
                if ($existingLog) {
                    continue;
                }

                // Create log with pass result for remaining items
                InspectionLog::create(
                    [
                        'session_id' => $session->id,
                        'location_id' => $targetLocationId,
                        'machine_id' => $machineId,
                        'checkpoint_id' => $cp->id,
                        'employee_id' => null,
                        'result' => 'pass',
                        'correction_action' => null,
                        'inspected_at' => now(),
                        'checkpoint_title_snapshot' => $cp->title,
                        'dept_snapshot' => $session->department->dept_name,
                        'verified_at' => null,
                        'verifier_id' => null,
                        'verification_status' => null,
                        'verification_comment' => null,
                    ]
                );
                $logsCreated++;
            }
        }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => "อัพเดทสถานะผ่านทั้งหมด จำนวน {$logsCreated} จุดตรวจ"
        ]);
    }

    /**
     * Fix #5: Mark "no production" ONLY on machines that have no log yet.
     *
     * Complements storeBulkNoProduction (which cascades no_production to the
     * whole room, overwriting any existing entries). Real production case: the
     * room is still producing, but one or two machines in it are offline. The
     * inspector wants to close out just those idle machines without touching
     * the room's area checkpoints or the running machines' already-saved logs.
     *
     * Rules:
     *   - Skips loc: targets entirely (only touches machine: targets).
     *   - Skips any machine that already has ANY log in this session.
     *   - Same reclean-mode + supervisor-review guards as the other bulk endpoints.
     */
    public function storeBulkNoProductionRemainingMachines(Request $request, InspectionSession $session, $locationId)
    {
        if ($session->inspector_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: not session owner'], 403);
        }

        if ($session->isLocked()) {
            return response()->json(['success' => false, 'message' => 'เซสชันนี้ถูกล็อคแล้ว ไม่สามารถแก้ไขได้'], 400);
        }

        if ($session->status === 'completed' && $session->hasPendingRecleans()) {
            return response()->json([
                'success' => false,
                'message' => 'โหมด Re-clean บันทึกได้เฉพาะรายการที่ถูกสั่งแก้เท่านั้น ไม่สามารถทำ Bulk "ไม่มีผลิต" ได้',
            ], 400);
        }

        $targetsQuery = $request->input('targets_query');
        if (!$targetsQuery) {
            return response()->json(['success' => false, 'message' => 'ไม่พบข้อมูลเป้าหมายการตรวจ'], 400);
        }

        $targetArray = explode(',', $targetsQuery);
        $machinesTouched = 0;
        $logsCreated = 0;

        DB::beginTransaction();
        try {
            foreach ($targetArray as $t) {
                $parts = explode(':', $t);
                if (count($parts) !== 2) continue;
                [$type, $id] = $parts;

                if ($type !== 'machine') continue;

                $machine = \App\Models\Machine::find($id);
                if (!$machine || $machine->location_id != $locationId) continue;

                // Skip machines that already have any log in this session — this
                // endpoint only closes out the untouched ones.
                $alreadyTouched = InspectionLog::where('session_id', $session->id)
                    ->where('machine_id', $machine->id)
                    ->exists();
                if ($alreadyTouched) continue;

                $checkpoints = $machine->checkpoints()->where('is_active', true)->get();
                if ($checkpoints->isEmpty()) continue;

                foreach ($checkpoints as $cp) {
                    InspectionLog::create([
                        'session_id' => $session->id,
                        'location_id' => $machine->location_id,
                        'machine_id' => $machine->id,
                        'checkpoint_id' => $cp->id,
                        'employee_id' => null,
                        'result' => 'no_production',
                        'correction_action' => null,
                        'inspected_at' => now(),
                        'checkpoint_title_snapshot' => $cp->title,
                        'dept_snapshot' => $session->department->dept_name,
                        'verified_at' => null,
                        'verifier_id' => null,
                        'verification_status' => null,
                        'verification_comment' => null,
                    ]);
                    $logsCreated++;
                }
                $machinesTouched++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'success' => true,
            'machines_marked' => $machinesTouched,
            'logs_created' => $logsCreated,
            'message' => "บันทึก 'ไม่มีผลิต' ให้เครื่องจักรที่ยังไม่ตรวจ จำนวน {$machinesTouched} เครื่อง ({$logsCreated} จุดตรวจ)",
        ]);
    }
}
