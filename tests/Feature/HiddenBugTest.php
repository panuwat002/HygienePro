<?php

use App\Models\User;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Checkpoint;
use App\Models\InspectionSession;
use App\Models\InspectionLog;
use App\Models\CorrectiveAction;

test('it does not create duplicate CARs when escalating an auto-created CAR', function () {
    $dept = Department::create(['dept_name' => 'Test', 'dept_code' => 'T1', 'visibility_type' => 'isolated']);
    $user = User::create(['name' => 'Staff', 'email' => 's@t.com', 'password' => '123', 'role' => 'staff', 'level' => 2, 'department_id' => $dept->id]);
    $cp = Checkpoint::create(['title' => 'CP', 'is_active' => true, 'type' => 'person']);
    $emp = Employee::create(['employee_id' => '1', 'fullname' => 'Emp', 'department_id' => $dept->id, 'qr_code_hash' => 'x', 'is_active' => true]);

    $service = app(\App\Services\InspectionService::class);
    $shift = \App\Models\Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => 'กะเช้า',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);
    $session = $service->startSession($user, $dept->id, 'personnel', false, 'custom_' . $shift->id);
    
    // Store log via service (this auto-creates CAR)
    $log = $service->storeLog($session, $emp->id, $cp->id, ['result' => 'fail', 'correction' => 'Auto']);
    
    // Count CARs
    $carCount = CorrectiveAction::where('inspection_log_id', $log->id)->count();
    expect($carCount)->toBe(1);

    // Supervisor escalates (simulating the web request)
    $supervisor = User::create(['name' => 'Sup', 'email' => 'sup@t.com', 'password' => '123', 'role' => 'supervisor', 'level' => 4, 'department_id' => $dept->id]);
    $this->actingAs($supervisor);
    $response = $this->post(route('corrective.escalate'), [
        'log_id' => $log->id,
        'note' => 'Manual escalate',
        'due_date' => now()->addDays(1)->toDateString()
    ]);
    
    $response->assertSessionHas('success');
    
    // CAR count should STILL be 1 (it should update or merge, not duplicate)
    $carCountAfter = CorrectiveAction::where('inspection_log_id', $log->id)->count();
    expect($carCountAfter)->toBe(1);
});
