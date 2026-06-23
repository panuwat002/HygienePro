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
