<?php

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\User;

/**
 * Every report scopes with `$user->department && ... === 'isolated'`. A user whose
 * department_id is null fails that first term, so no department filter is applied at all —
 * and UserController validates department_id as `nullable`, so such an account is one
 * unfilled field away in the normal admin form.
 */
beforeEach(function () {
    $this->deptA = Department::create([
        'dept_name' => 'Alpha', 'dept_code' => 'ALP', 'visibility_type' => 'isolated',
    ]);
    $this->deptB = Department::create([
        'dept_name' => 'Bravo', 'dept_code' => 'BRV', 'visibility_type' => 'isolated',
    ]);

    $inspector = User::create([
        'name' => 'Inspector B', 'email' => 'inspb@test.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->deptB->id,
    ]);

    $location = Location::create(['location_name' => 'Bravo Line']);
    $machine = Machine::create(['name' => 'Bravo Machine', 'location_id' => $location->id, 'is_active' => true]);
    $checkpoint = Checkpoint::create(['title' => 'Bravo CP', 'is_active' => true, 'type' => 'area']);

    $session = InspectionSession::create([
        'inspector_id' => $inspector->id, 'department_id' => $this->deptB->id,
        'inspection_date' => now(), 'shift' => 'morning', 'type' => 'machine',
        'status' => 'completed',
    ]);

    InspectionLog::create([
        'session_id' => $session->id, 'machine_id' => $machine->id,
        'checkpoint_id' => $checkpoint->id, 'result' => 'fail',
        'correction_action' => 'Needs fix', 'inspected_at' => now(),
    ]);
});

it('does not hand a manager with no department every department of data', function () {
    // role=manager passes the view-reports gate without ever touching a department.
    $orphan = User::create([
        'name' => 'No Dept Manager', 'email' => 'orphan@test.com',
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => null,
    ]);

    $response = $this->actingAs($orphan)->get(route('reports.offenders'));

    $response->assertOk();
    expect($response->getContent())->not->toContain('Bravo Machine');
});

it('does not leak another department through the daily report either', function () {
    $orphan = User::create([
        'name' => 'No Dept Manager 2', 'email' => 'orphan2@test.com',
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => null,
    ]);

    $response = $this->actingAs($orphan)->get(route('reports.daily'));

    $response->assertOk();
    expect($response->getContent())->not->toContain('Bravo');
});
