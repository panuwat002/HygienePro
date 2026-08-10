<?php

use App\Models\User;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Checkpoint;
use App\Models\InspectionSession;
use App\Models\InspectionLog;
use App\Models\CorrectiveAction;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    // Setup Departments
    $this->deptQA = Department::create([
        'dept_name' => 'Quality Assurance',
        'dept_code' => 'QA',
        'visibility_type' => 'global'
    ]);

    $this->deptPd = Department::create([
        'dept_name' => 'Production',
        'dept_code' => 'PD',
        'visibility_type' => 'isolated'
    ]);

    // Setup Users
    $this->admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'level' => 6,
        'department_id' => $this->deptQA->id,
    ]);

    $this->manager = User::create([
        'name' => 'QA Manager',
        'email' => 'manager@example.com',
        'password' => Hash::make('password'),
        'role' => 'manager',
        'level' => 5,
        'department_id' => $this->deptQA->id,
    ]);

    $this->supervisor = User::create([
        'name' => 'QA Supervisor',
        'email' => 'supervisor@example.com',
        'password' => Hash::make('password'),
        'role' => 'supervisor',
        'level' => 4,
        'department_id' => $this->deptQA->id,
    ]);

    $this->staff = User::create([
        'name' => 'QA Staff',
        'email' => 'staff@example.com',
        'password' => Hash::make('password'),
        'role' => 'staff',
        'level' => 2,
        'department_id' => $this->deptQA->id,
    ]);

    // Setup Checkpoints
    $this->checkpoint1 = Checkpoint::create([
        'title' => 'เล็บสั้น ตะไบเรียบร้อย',
        'description' => 'เล็บต้องไม่ยาวเกินปลายนิ้ว',
        'is_active' => true,
        'type' => 'person'
    ]);

    $this->checkpoint2 = Checkpoint::create([
        'title' => 'ไม่สวมเครื่องประดับ',
        'description' => 'ห้ามใส่แหวน นาฬิกา ต่างหู',
        'is_active' => true,
        'type' => 'person'
    ]);

    // Setup Employee
    $this->employee = Employee::create([
        'employee_id' => 'EMP001',
        'fullname' => 'Somchai Jaidee',
        'department_id' => $this->deptPd->id,
        'qr_code_hash' => 'hash_somchai_001',
        'is_active' => true
    ]);
});

test('non-QA staff cannot inspect', function () {
    $nonQAStaff = User::create([
        'name' => 'PD Staff',
        'email' => 'pdstaff@example.com',
        'password' => Hash::make('password'),
        'role' => 'staff',
        'level' => 2,
        'department_id' => $this->deptPd->id,
    ]);

    $response = $this->actingAs($nonQAStaff)
        ->post(route('inspection.start', 'personnel'), [
            'department_id' => $this->deptPd->id
        ]);

    $response->assertStatus(403);
});

test('QA staff can start session and store inspection logs', function () {
    $this->actingAs($this->staff);

    // 1. Start Inspection Session
    $response = $this->post(route('inspection.start', 'personnel'), [
        'department_id' => $this->deptPd->id
    ]);

    $session = InspectionSession::where('department_id', $this->deptPd->id)->first();
    expect($session)->not->toBeNull();
    expect($session->status)->toBe('in_progress');

    $response->assertRedirect(route('inspection.scan', $session->id));

    // 2. Submit logs (One Pass, One Fail with Correction)
    // First simulate file upload for the fail log
    $photo = \Illuminate\Http\UploadedFile::fake()->image('evidence.jpg');

    $response = $this->post(route('inspection.log.store', $session->id), [
        'employee_id' => $this->employee->id,
        'logs' => [
            $this->checkpoint1->id => [
                'checkpoint_id' => $this->checkpoint1->id,
                'result' => 'pass'
            ],
            $this->checkpoint2->id => [
                'checkpoint_id' => $this->checkpoint2->id,
                'result' => 'fail',
                'correction' => 'ถอดเครื่องประดับเรียบร้อย',
                'photo' => $photo
            ]
        ]
    ]);

    $response->assertRedirect(route('inspection.scan', $session->id));

    // Check saved logs
    $logPass = InspectionLog::where('session_id', $session->id)
        ->where('checkpoint_id', $this->checkpoint1->id)
        ->first();
    $logFail = InspectionLog::where('session_id', $session->id)
        ->where('checkpoint_id', $this->checkpoint2->id)
        ->first();

    expect($logPass->result)->toBe('pass');
    expect($logFail->result)->toBe('fail');
    expect($logFail->correction_action)->toBe('ถอดเครื่องประดับเรียบร้อย');
    expect($logFail->photo_path)->not->toBeNull();
});

test('session locking prevents further edits once approved by manager', function () {
    // 1. Create completed session and verified logs
    $session = InspectionSession::create([
        'department_id' => $this->deptPd->id,
        'inspection_date' => now()->toDateString(),
        'shift' => 'morning',
        'inspector_id' => $this->staff->id,
        'status' => 'completed',
        'type' => 'personnel',
        'round' => 1
    ]);

    $log = InspectionLog::create([
        'session_id' => $session->id,
        'checkpoint_id' => $this->checkpoint1->id,
        'employee_id' => $this->employee->id,
        'result' => 'pass',
        'inspected_at' => now(),
        'verification_status' => 'verified',
        'verified_at' => now(),
        'verifier_id' => $this->supervisor->id
    ]);

    // 2. Manager approves and locks session
    $this->actingAs($this->manager);
    $response = $this->post(route('inspection.approve'), [
        'ids' => [$log->id]
    ]);

    $session->refresh();
    $log->refresh();

    expect($session->is_locked)->toBeTrue();
    expect($log->verification_status)->toBe('approved');

    // 3. Attempting to add logs to locked session fails
    $this->actingAs($this->staff);
    $response = $this->post(route('inspection.log.store', $session->id), [
        'employee_id' => $this->employee->id,
        'logs' => [
            $this->checkpoint2->id => [
                'checkpoint_id' => $this->checkpoint2->id,
                'result' => 'pass'
            ]
        ]
    ]);

    $response->assertRedirect(route('inspection.dashboard', $session->type));
    $response->assertSessionHas('error');
});

test('escalated corrective action closing automatically approves corresponding log', function () {
    // 1. Create completed session and failed log
    $session = InspectionSession::create([
        'department_id' => $this->deptPd->id,
        'inspection_date' => now()->toDateString(),
        'shift' => 'morning',
        'inspector_id' => $this->staff->id,
        'status' => 'completed',
        'type' => 'personnel',
        'round' => 1
    ]);

    $log = InspectionLog::create([
        'session_id' => $session->id,
        'checkpoint_id' => $this->checkpoint1->id,
        'employee_id' => $this->employee->id,
        'result' => 'fail',
        'note' => 'กระเบื้องแตก',
        'inspected_at' => now(),
    ]);

    // 2. Supervisor escalates to CAR
    $this->actingAs($this->supervisor);
    $response = $this->post(route('corrective.escalate'), [
        'log_id' => $log->id,
        'note' => 'กระเบื้องชำรุด ต้องการการซ่อมแซม',
        'due_date' => now()->addDays(5)->toDateString()
    ]);

    $car = CorrectiveAction::where('inspection_log_id', $log->id)->first();
    expect($car)->not->toBeNull();
    expect($car->status)->toBe('open');

    // 3. Resolve CAR with fake proof image
    $proof = \Illuminate\Http\UploadedFile::fake()->image('resolved_proof.jpg');
    $response = $this->post(route('corrective.resolve'), [
        'action_id' => $car->id,
        'action_taken' => 'ซ่อมเปลี่ยนกระเบื้องเรียบร้อยแล้ว',
        'proof_image' => $proof
    ]);

    $car->refresh();
    expect($car->status)->toBe('resolved');
    expect($car->action_taken)->toBe('ซ่อมเปลี่ยนกระเบื้องเรียบร้อยแล้ว');

    // 4. Close CAR (should auto-approve log)
    $response = $this->post(route('corrective.close'), [
        'action_id' => $car->id
    ]);

    $car->refresh();
    $log->refresh();

    expect($car->status)->toBe('closed');
    expect($log->verification_status)->toBe('approved');
});

test('shift card count is not zeroed out when other shift has been fully inspected', function () {
    $morningShift = \App\Models\Shift::create([
        'shift_name' => 'กะเช้า',
        'start_time' => '06:00:00',
        'end_time' => '18:00:00',
    ]);
    $nightShift = \App\Models\Shift::create([
        'shift_name' => 'กะดึก',
        'start_time' => '18:00:00',
        'end_time' => '06:00:00',
    ]);

    $morning1 = Employee::create([
        'employee_id' => 'M001', 'fullname' => 'Morning One',
        'department_id' => $this->deptPd->id, 'qr_code_hash' => 'hash_m1',
        'is_active' => true, 'shift_id' => $morningShift->id,
    ]);
    $morning2 = Employee::create([
        'employee_id' => 'M002', 'fullname' => 'Morning Two',
        'department_id' => $this->deptPd->id, 'qr_code_hash' => 'hash_m2',
        'is_active' => true, 'shift_id' => $morningShift->id,
    ]);
    $night1 = Employee::create([
        'employee_id' => 'N001', 'fullname' => 'Night One',
        'department_id' => $this->deptPd->id, 'qr_code_hash' => 'hash_n1',
        'is_active' => true, 'shift_id' => $nightShift->id,
    ]);

    $session = InspectionSession::create([
        'department_id' => $this->deptPd->id,
        'inspection_date' => now()->toDateString(),
        'shift' => 'morning',
        'inspector_id' => $this->staff->id,
        'status' => 'completed',
        'type' => 'personnel',
        'round' => 1,
    ]);
    foreach ([$morning1, $morning2] as $emp) {
        InspectionLog::create([
            'session_id' => $session->id,
            'checkpoint_id' => $this->checkpoint1->id,
            'employee_id' => $emp->id,
            'result' => 'pass',
            'inspected_at' => now(),
        ]);
    }

    $this->actingAs($this->staff);
    $response = $this->getJson(
        route('inspection.stats', ['type' => 'personnel', 'department' => $this->deptPd->id]) . '?shift=night'
    );

    $response->assertStatus(200);
    $cards = collect($response->json('locations'));

    $nightCard = $cards->firstWhere('id', 'shift_night');
    expect($nightCard)->not->toBeNull();
    // Regression: night must report its real population even when morning was fully inspected.
    // Previously this returned 0 because the controller subtracted "inspected in other shifts"
    // from every shift unconditionally.
    expect($nightCard['employees_count'])->toBe(1);
});

test('inspector can finish session even when it contains corrected fail logs', function () {
    // Regression: finishSession used to block redirect back with error whenever ANY log had
    // result=fail, even though the workflow expects fails (with correction) to pass through to
    // Supervisor verification. That block silently swallowed itself on the dashboard (no flash
    // display), so users experienced "Finish button does nothing".
    $session = InspectionSession::create([
        'department_id' => $this->deptPd->id,
        'inspection_date' => now()->toDateString(),
        'shift' => 'morning',
        'inspector_id' => $this->staff->id,
        'status' => 'in_progress',
        'type' => 'personnel',
        'round' => 1,
    ]);

    InspectionLog::create([
        'session_id' => $session->id,
        'checkpoint_id' => $this->checkpoint1->id,
        'employee_id' => $this->employee->id,
        'result' => 'pass',
        'inspected_at' => now(),
    ]);
    InspectionLog::create([
        'session_id' => $session->id,
        'checkpoint_id' => $this->checkpoint2->id,
        'employee_id' => $this->employee->id,
        'result' => 'fail',
        'correction_action' => 'ถอดเครื่องประดับเรียบร้อย',
        'photo_path' => 'inspections/fake.jpg',
        'inspected_at' => now(),
    ]);

    $this->actingAs($this->staff);
    $response = $this->post(route('inspection.finish', $session->id));

    $response->assertRedirect(route('inspection.dashboard', $session->type));
    $response->assertSessionHas('success');
    $response->assertSessionMissing('error');

    $session->refresh();
    expect($session->status)->toBe('completed');
});

/* ------------------------------------------------------------------ */
/* Area/Machine bulk-endpoints — data-integrity regression suite     */
/* ------------------------------------------------------------------ */

function makeAreaSession($ownerId, $deptId, $status = 'in_progress') {
    return InspectionSession::create([
        'department_id' => $deptId,
        'inspection_date' => now()->toDateString(),
        'shift' => 'morning',
        'inspector_id' => $ownerId,
        'status' => $status,
        'type' => 'area',
        'round' => 1,
    ]);
}

test('storeBulkNoProduction is blocked when session is in reclean-fix mode', function () {
    $location = \App\Models\Location::create(['location_name' => 'Test Area']);
    $areaCp = Checkpoint::create([
        'title' => 'พื้นสะอาด', 'description' => 'clean floor',
        'is_active' => true, 'type' => 'area',
    ]);
    $location->checkpoints()->attach($areaCp->id);

    $session = makeAreaSession($this->staff->id, $this->deptPd->id, 'completed');
    // Existing reclean log makes the session enter recleanFixMode
    InspectionLog::create([
        'session_id' => $session->id,
        'location_id' => $location->id,
        'checkpoint_id' => $areaCp->id,
        'result' => 'fail',
        'correction_action' => 'สกปรก',
        'verification_status' => 'reclean',
        'inspected_at' => now(),
    ]);

    $this->actingAs($this->staff);
    $response = $this->postJson(
        route('inspection.area.bulk-no-production', ['session' => $session->id, 'location' => $location->id]),
        ['targets_query' => "loc:{$location->id}"]
    );

    $response->assertStatus(400);
    expect(InspectionLog::where('session_id', $session->id)
        ->where('result', 'no_production')->count())->toBe(0);
});

test('storeBulkNoProduction refuses to overwrite logs already reviewed by supervisor', function () {
    $location = \App\Models\Location::create(['location_name' => 'Reviewed Area']);
    $areaCp = Checkpoint::create([
        'title' => 'ผนัง', 'description' => 'wall',
        'is_active' => true, 'type' => 'area',
    ]);
    $location->checkpoints()->attach($areaCp->id);

    $session = makeAreaSession($this->staff->id, $this->deptPd->id);
    // A log the supervisor has already touched — this must not be silently overwritten.
    InspectionLog::create([
        'session_id' => $session->id,
        'location_id' => $location->id,
        'checkpoint_id' => $areaCp->id,
        'result' => 'pass',
        'verification_status' => 'verified',
        'verifier_id' => $this->supervisor->id,
        'verified_at' => now(),
        'inspected_at' => now(),
    ]);

    $this->actingAs($this->staff);
    $response = $this->postJson(
        route('inspection.area.bulk-no-production', ['session' => $session->id, 'location' => $location->id]),
        ['targets_query' => "loc:{$location->id}"]
    );

    $response->assertStatus(400);
    $reviewedLog = InspectionLog::where('session_id', $session->id)
        ->where('location_id', $location->id)
        ->where('checkpoint_id', $areaCp->id)->first();
    expect($reviewedLog->result)->toBe('pass'); // not touched
    expect($reviewedLog->verification_status)->toBe('verified');
});

test('storeBulk rolls back created logs when a later checkpoint fails validation', function () {
    $location = \App\Models\Location::create(['location_name' => 'Tx Test']);
    $cpOk = Checkpoint::create(['title' => 'ok', 'description' => '', 'is_active' => true, 'type' => 'area']);
    $cpMissingPhoto = Checkpoint::create(['title' => 'no photo', 'description' => '', 'is_active' => true, 'type' => 'area']);
    $location->checkpoints()->attach([$cpOk->id, $cpMissingPhoto->id]);

    $session = makeAreaSession($this->staff->id, $this->deptPd->id);
    $this->actingAs($this->staff);

    // Second checkpoint is fail with a note but no photo → server rejects. Under the fix, the
    // first checkpoint's pass log should ALSO be rolled back rather than half-written.
    $response = $this->post(
        route('inspection.area.store', ['session' => $session->id, 'location' => $location->id]),
        [
            'results' => ['targets' => [
                "loc:{$location->id}" => [
                    $cpOk->id => 'pass',
                    $cpMissingPhoto->id => 'fail',
                ],
            ]],
            'notes' => ["loc:{$location->id}" => [
                $cpMissingPhoto->id => 'missing photo test',
            ]],
        ]
    );

    expect(InspectionLog::where('session_id', $session->id)->count())->toBe(0);
});

test('storeBulkNoProductionRemainingMachines only closes machines with no existing log', function () {
    $location = \App\Models\Location::create(['location_name' => 'Mixed Room']);

    $mCp = Checkpoint::create(['title' => 'gear', 'description' => '', 'is_active' => true, 'type' => 'area']);

    // 3 machines: one already inspected (pass), two untouched.
    $mDone = \App\Models\Machine::create(['location_id' => $location->id, 'name' => 'Running', 'is_active' => true]);
    $mIdle1 = \App\Models\Machine::create(['location_id' => $location->id, 'name' => 'Idle 1', 'is_active' => true]);
    $mIdle2 = \App\Models\Machine::create(['location_id' => $location->id, 'name' => 'Idle 2', 'is_active' => true]);
    foreach ([$mDone, $mIdle1, $mIdle2] as $m) {
        $m->checkpoints()->attach($mCp->id);
    }

    $session = makeAreaSession($this->staff->id, $this->deptPd->id);
    // The "Running" machine was already inspected in this session.
    InspectionLog::create([
        'session_id' => $session->id,
        'location_id' => $location->id,
        'machine_id' => $mDone->id,
        'checkpoint_id' => $mCp->id,
        'result' => 'pass',
        'inspected_at' => now(),
    ]);

    $this->actingAs($this->staff);
    $targets = "machine:{$mDone->id},machine:{$mIdle1->id},machine:{$mIdle2->id}";
    $response = $this->postJson(
        route('inspection.area.bulk-no-production-remaining', ['session' => $session->id, 'location' => $location->id]),
        ['targets_query' => $targets]
    );

    $response->assertStatus(200);
    expect($response->json('machines_marked'))->toBe(2);

    // Running machine untouched
    expect(InspectionLog::where('session_id', $session->id)
        ->where('machine_id', $mDone->id)->first()->result)->toBe('pass');
    // Idle machines now marked no_production
    expect(InspectionLog::where('session_id', $session->id)
        ->where('machine_id', $mIdle1->id)->first()->result)->toBe('no_production');
    expect(InspectionLog::where('session_id', $session->id)
        ->where('machine_id', $mIdle2->id)->first()->result)->toBe('no_production');
});

test('storeBulkNoProductionRemainingMachines is blocked in reclean-fix mode', function () {
    $location = \App\Models\Location::create(['location_name' => 'Reclean Guard Room']);
    $areaCp = Checkpoint::create(['title' => 'floor', 'description' => '', 'is_active' => true, 'type' => 'area']);
    $location->checkpoints()->attach($areaCp->id);

    $session = makeAreaSession($this->staff->id, $this->deptPd->id, 'completed');
    InspectionLog::create([
        'session_id' => $session->id,
        'location_id' => $location->id,
        'checkpoint_id' => $areaCp->id,
        'result' => 'fail',
        'correction_action' => 'need clean',
        'verification_status' => 'reclean',
        'inspected_at' => now(),
    ]);

    $mCp = Checkpoint::create(['title' => 'gear', 'description' => '', 'is_active' => true, 'type' => 'area']);
    $m = \App\Models\Machine::create(['location_id' => $location->id, 'name' => 'M', 'is_active' => true]);
    $m->checkpoints()->attach($mCp->id);

    $this->actingAs($this->staff);
    $response = $this->postJson(
        route('inspection.area.bulk-no-production-remaining', ['session' => $session->id, 'location' => $location->id]),
        ['targets_query' => "machine:{$m->id}"]
    );

    $response->assertStatus(400);
    expect(InspectionLog::where('session_id', $session->id)
        ->where('machine_id', $m->id)->count())->toBe(0);
});

test('storeBulk deletes the previous photo file when a log photo is replaced', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $location = \App\Models\Location::create(['location_name' => 'Photo Test']);
    $cp = Checkpoint::create(['title' => 'wall', 'description' => '', 'is_active' => true, 'type' => 'area']);
    $location->checkpoints()->attach($cp->id);

    $session = makeAreaSession($this->staff->id, $this->deptPd->id);

    // Seed an existing fail log with a photo file already on disk.
    \Illuminate\Support\Facades\Storage::disk('public')->put('evidence/old.jpg', 'oldbytes');
    InspectionLog::create([
        'session_id' => $session->id,
        'location_id' => $location->id,
        'checkpoint_id' => $cp->id,
        'result' => 'fail',
        'correction_action' => 'first fail',
        'photo_path' => 'evidence/old.jpg',
        'inspected_at' => now(),
    ]);

    // Sleep long enough to clear the 3s speed-trap window before hitting the endpoint again.
    \Illuminate\Support\Carbon::setTestNow(now()->addSeconds(10));

    $this->actingAs($this->staff);
    $newPhoto = \Illuminate\Http\UploadedFile::fake()->image('new.jpg', 800, 600);
    $response = $this->post(
        route('inspection.area.store', ['session' => $session->id, 'location' => $location->id]),
        [
            'results' => ['targets' => [
                "loc:{$location->id}" => [$cp->id => 'fail'],
            ]],
            'notes' => ["loc:{$location->id}" => [$cp->id => 'second fail']],
            'photos' => ["loc:{$location->id}" => [$cp->id => $newPhoto]],
        ]
    );

    expect(\Illuminate\Support\Facades\Storage::disk('public')->exists('evidence/old.jpg'))->toBeFalse();
});
