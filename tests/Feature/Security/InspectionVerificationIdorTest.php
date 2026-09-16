<?php

use App\Models\User;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Checkpoint;
use App\Models\InspectionSession;
use App\Models\InspectionLog;
use App\Models\CorrectiveAction;

test('it prevents supervisors from verifying logs outside their department (IDOR)', function () {
    // 1. Setup Departments
    $deptA = Department::create(['dept_name' => 'Dept A', 'dept_code' => 'DA', 'visibility_type' => 'isolated']);
    $deptB = Department::create(['dept_name' => 'Dept B', 'dept_code' => 'DB', 'visibility_type' => 'isolated']);

    // 2. Setup Supervisor A (Attacker)
    $supervisorA = User::create([
        'name' => 'Supervisor A',
        'email' => 'supA@test.com',
        'password' => bcrypt('password'),
        'role' => 'supervisor',
        'level' => 4,
        'department_id' => $deptA->id
    ]);

    // 3. Setup Target Log in Dept B (Victim)
    $cp = Checkpoint::create(['title' => 'Test CP', 'is_active' => true, 'type' => 'person']);
    $empB = Employee::create([
        'employee_id' => 'B1', 
        'fullname' => 'Emp B', 
        'department_id' => $deptB->id, 
        'qr_code_hash' => 'hashB', 
        'is_active' => true
    ]);
    
    // Create random inspector
    $inspector = User::create([
        'name' => 'Inspector B',
        'email' => 'inspB@test.com',
        'password' => bcrypt('password'),
        'role' => 'staff',
        'level' => 2,
        'department_id' => $deptB->id
    ]);

    $sessionB = InspectionSession::create([
        'inspector_id' => $inspector->id,
        'department_id' => $deptB->id,
        'inspection_date' => now(),
        'shift' => 'morning',
        'type' => 'personnel',
        'status' => 'completed'
    ]);

    $logB = InspectionLog::create([
        'session_id' => $sessionB->id,
        'employee_id' => $empB->id,
        'checkpoint_id' => $cp->id,
        'result' => 'fail',
        'correction_action' => 'Needs fix',
        'inspected_at' => now(),
        'verification_status' => null
    ]);

    // 4. Attempt to exploit IDOR via verify endpoint
    $this->actingAs($supervisorA);

    $response = $this->post(route('inspection.verify'), [
        'ids' => [$logB->id],
        'status' => 'reclean',
        'comment' => 'Attacker reclean comment'
    ]);

    // Should be blocked either by Gate (403) or by our IDOR check (redirect 302 with error)
    expect(in_array($response->status(), [302, 403]))->toBeTrue();

    // 5. Verify the log was NOT modified
    $logB->refresh();
    expect($logB->verification_status)->toBeNull();
    expect($logB->verification_comment)->toBeNull();
});
