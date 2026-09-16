<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * EmployeeController, EmployeesExport and InspectionScheduleController all scope with
 * `$user->department && ... === 'isolated'`. A user whose department_id is null fails
 * that first term, so no department filter is applied at all and they see — and can
 * delete — every department's records. UserController validates department_id as
 * `nullable`, so such an account is one unfilled field away in the normal admin form.
 *
 * User::isRestrictedToOwnDepartment() already fails closed for this case; these tests
 * pin the employee paths to it.
 */
beforeEach(function () {
    $this->deptA = Department::create([
        'dept_name' => 'Alpha', 'dept_code' => 'ALP', 'visibility_type' => 'isolated',
    ]);

    $this->alphaEmployee = Employee::factory()->create([
        'fullname' => 'Alpha Secret Person',
        'department_id' => $this->deptA->id,
    ]);

    // Supervisor with no department: passes the manage-employees gate on level >= 4.
    $this->departmentless = User::factory()->create([
        'role' => 'supervisor', 'level' => 4, 'department_id' => null,
    ]);
});

test('a departmentless user cannot list another department\'s employees', function () {
    $this->actingAs($this->departmentless);

    $response = $this->get(route('employees.index'));

    $response->assertOk();
    $response->assertDontSee('Alpha Secret Person');
});

test('a departmentless user cannot bulk-delete another department\'s employees', function () {
    $this->actingAs($this->departmentless);

    $this->post(route('employees.bulk-delete'), [
        'employee_ids' => [$this->alphaEmployee->id],
    ]);

    expect(Employee::find($this->alphaEmployee->id))->not->toBeNull();
});

test('a departmentless user cannot export another department\'s employees', function () {
    $this->actingAs($this->departmentless);

    $export = new \App\Exports\EmployeesExport($this->departmentless);

    expect($export->collection()->pluck('id'))
        ->not->toContain($this->alphaEmployee->id);
});

test('an admin without a department still sees everything', function () {
    $admin = User::factory()->create([
        'role' => 'admin', 'level' => 9, 'department_id' => null,
    ]);

    $this->actingAs($admin);

    $this->get(route('employees.index'))->assertSee('Alpha Secret Person');
});

test('an isolated-department user still sees their own employees', function () {
    $ownUser = User::factory()->create([
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->deptA->id,
    ]);

    $this->actingAs($ownUser);

    $this->get(route('employees.index'))->assertSee('Alpha Secret Person');
});
