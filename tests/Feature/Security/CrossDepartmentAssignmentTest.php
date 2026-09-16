<?php

use App\Models\User;
use App\Models\Department;
use App\Models\InspectionSession;
use App\Models\InspectionLog;
use App\Models\CorrectiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it prevents cross-department assignment of CARs', function () {
    $deptA = Department::create(['dept_code' => 'DA', 'dept_name' => 'Dept A', 'visibility_type' => 'isolated']);
    $deptB = Department::create(['dept_code' => 'DB', 'dept_name' => 'Dept B', 'visibility_type' => 'isolated']);
    
    $managerA = User::factory()->create([
        'role' => 'manager',
        'level' => 5,
        'department_id' => $deptA->id,
    ]);

    $userB = User::factory()->create([
        'role' => 'staff',
        'level' => 2,
        'department_id' => $deptB->id,
    ]);

    $sessionA = InspectionSession::factory()->create(['department_id' => $deptA->id]);
    $logA = InspectionLog::factory()->create(['session_id' => $sessionA->id, 'verification_status' => 'reclean']);
    $carA = CorrectiveAction::factory()->create(['inspection_log_id' => $logA->id, 'escalated_by' => $managerA->id, 'status' => 'open']);

    $this->actingAs($managerA);
    
    $response = $this->post(route('corrective.assign'), [
        'action_id' => $carA->id,
        'assigned_to' => $userB->id,
    ]);

    $response->assertSessionHas('error');
    
    $carA->refresh();
    expect($carA->assigned_to)->not->toBe($userB->id);
});
