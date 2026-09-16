<?php

use App\Models\User;
use App\Models\Department;
use App\Models\InspectionSession;
use App\Models\InspectionLog;
use App\Models\CorrectiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it prevents inspectors from auto-approving logs when closing a CAR', function () {
    $dept = Department::create(['dept_code' => 'T1', 'dept_name' => 'Test', 'visibility_type' => 'isolated']);
    
    // Create an Inspector (level 2) who escalated the issue
    $inspector = User::factory()->create([
        'role' => 'staff',
        'level' => 2,
        'department_id' => $dept->id,
    ]);

    // Create a Session
    $session = InspectionSession::factory()->create([
        'department_id' => $dept->id,
        'inspector_id' => $inspector->id,
        'status' => 'completed',
    ]);

    // Create a failed log (reclean)
    $log = InspectionLog::factory()->create([
        'session_id' => $session->id,
        'verification_status' => 'reclean',
        'result' => 'fail',
    ]);

    // Create the CAR assigned/escalated by the inspector
    $car = CorrectiveAction::factory()->create([
        'inspection_log_id' => $log->id,
        'escalated_by' => $inspector->id,
        'status' => 'resolved',
    ]);

    // Inspector logs in and closes the CAR
    $this->actingAs($inspector);
    
    $response = $this->post(route('corrective.close'), [
        'action_id' => $car->id,
    ]);

    $response->assertSessionHas('success');
    
    // Refresh models
    $car->refresh();
    $log->refresh();

    // The CAR should be closed
    expect($car->status)->toBe('closed');
    
    // The Log should NOT be approved. It should remain 'reclean' 
    // because the closer was just an Inspector (Level 2).
    expect($log->verification_status)->toBe('reclean');
});

test('it auto-verifies log if closed by supervisor', function () {
    $dept = Department::create(['dept_code' => 'T2', 'dept_name' => 'Test2', 'visibility_type' => 'isolated']);
    
    $supervisor = User::factory()->create([
        'role' => 'supervisor',
        'level' => 4,
        'department_id' => $dept->id,
    ]);

    $session = InspectionSession::factory()->create(['department_id' => $dept->id]);
    $log = InspectionLog::factory()->create(['session_id' => $session->id, 'verification_status' => 'reclean']);
    $car = CorrectiveAction::factory()->create(['inspection_log_id' => $log->id, 'escalated_by' => $supervisor->id]);

    $this->actingAs($supervisor);
    $this->post(route('corrective.close'), ['action_id' => $car->id]);

    $log->refresh();
    expect($log->verification_status)->toBe('verified');
});

test('it auto-approves log if closed by manager', function () {
    $dept = Department::create(['dept_code' => 'T3', 'dept_name' => 'Test3', 'visibility_type' => 'isolated']);
    
    $manager = User::factory()->create([
        'role' => 'manager',
        'level' => 5,
        'department_id' => $dept->id,
    ]);

    $session = InspectionSession::factory()->create(['department_id' => $dept->id]);
    $log = InspectionLog::factory()->create(['session_id' => $session->id, 'verification_status' => 'reclean']);
    $car = CorrectiveAction::factory()->create(['inspection_log_id' => $log->id, 'escalated_by' => $manager->id]);

    $this->actingAs($manager);
    $this->post(route('corrective.close'), ['action_id' => $car->id]);

    $log->refresh();
    expect($log->verification_status)->toBe('approved');
});
