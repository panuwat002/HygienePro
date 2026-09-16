<?php

use App\Models\User;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('saving a department does not demote the admin who is editing it', function () {
    $dept = Department::create([
        'dept_code' => 'QA',
        'dept_name' => 'Quality Assurance',
        'visibility_type' => 'global',
    ]);

    // Matches the seeded IT Admin: role=admin, level=6, sitting in a department.
    $admin = User::factory()->create([
        'role' => 'admin',
        'level' => 6,
        'department_id' => $dept->id,
    ]);

    $this->actingAs($admin);

    // The edit form always renders the manager_id select, so an empty option is
    // submitted on a routine save that only touches dept_code.
    $this->put(route('departments.update', $dept), [
        'dept_code' => 'QA2',
        'dept_name' => 'Quality Assurance',
        'visibility_type' => 'global',
        'manager_id' => '',
    ]);

    $admin->refresh();

    expect($admin->role)->toBe('admin')
        ->and($admin->level)->toBe(6);
});

test('promoting a manager does not demote other admins in that department', function () {
    $dept = Department::create([
        'dept_code' => 'QA',
        'dept_name' => 'Quality Assurance',
        'visibility_type' => 'global',
    ]);

    $actingAdmin = User::factory()->create([
        'role' => 'admin',
        'level' => 9,
        'department_id' => $dept->id,
    ]);

    $otherAdmin = User::factory()->create([
        'role' => 'admin',
        'level' => 9,
        'department_id' => $dept->id,
    ]);

    $newManager = User::factory()->create([
        'role' => 'staff',
        'level' => 2,
        'department_id' => $dept->id,
    ]);

    $this->actingAs($actingAdmin);

    $this->put(route('departments.update', $dept), [
        'dept_code' => 'QA',
        'dept_name' => 'Quality Assurance',
        'visibility_type' => 'global',
        'manager_id' => $newManager->id,
    ]);

    $otherAdmin->refresh();
    $newManager->refresh();

    // The intended effect still happens.
    expect($newManager->role)->toBe('manager')
        ->and($newManager->level)->toBe(5);

    // But admins are not collateral damage.
    expect($otherAdmin->role)->toBe('admin')
        ->and($otherAdmin->level)->toBe(9);
});

test('clearing the manager still demotes an actual manager', function () {
    $dept = Department::create([
        'dept_code' => 'PR',
        'dept_name' => 'Production',
        'visibility_type' => 'isolated',
    ]);

    $admin = User::factory()->create([
        'role' => 'admin',
        'level' => 9,
        'department_id' => null,
    ]);

    $manager = User::factory()->create([
        'role' => 'manager',
        'level' => 5,
        'department_id' => $dept->id,
    ]);

    $this->actingAs($admin);

    $this->put(route('departments.update', $dept), [
        'dept_code' => 'PR',
        'dept_name' => 'Production',
        'visibility_type' => 'isolated',
        'manager_id' => '',
    ]);

    $manager->refresh();

    expect($manager->role)->toBe('staff')
        ->and($manager->level)->toBe(3);
});
