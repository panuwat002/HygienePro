<?php

/**
 * ห้องแคะ is part of Production, not a department beside it.
 *
 * The room has no air shower, so its staff carry nine checkpoints where the
 * rest of Production carries ten, and QA wants it inspected and reported on its
 * own. Departments are the only grouping this system inspects by - a personnel
 * round targets department + shift - so the room has to become one.
 *
 * But a user belongs to exactly one department, and acknowledging a finding
 * requires being in the department it was raised against. Split the room out
 * naively and Production's head can no longer acknowledge anything in it: the
 * corrective action loop dies for those employees. A department that knows its
 * parent keeps that loop intact.
 */

use App\Models\Department;
use App\Models\User;

beforeEach(function () {
    $this->production = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);

    $this->pickingRoom = Department::create([
        'dept_name' => 'Production (ห้องแคะ)', 'dept_code' => 'PDK',
        'visibility_type' => 'isolated',
        'parent_department_id' => $this->production->id,
    ]);

    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);

    $this->productionHead = User::create([
        'name' => 'Thanayut', 'email' => 'pd-head@example.com',
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => $this->production->id,
    ]);
});

it('knows which department it belongs under', function () {
    expect($this->pickingRoom->parent->id)->toBe($this->production->id)
        ->and($this->production->children->pluck('id')->all())->toBe([$this->pickingRoom->id])
        ->and($this->production->parent)->toBeNull();
});

it('lets the parent department head acknowledge a finding in the sub-department', function () {
    expect($this->productionHead->canAcknowledge($this->pickingRoom->id))->toBeTrue();
});

it('still lets them acknowledge their own department', function () {
    expect($this->productionHead->canAcknowledge($this->production->id))->toBeTrue();
});

/**
 * Covering a child is not covering the whole plant. QA raises findings against
 * Production; Production must not gain a say over QA's own rounds.
 */
it('does not let that reach across to an unrelated department', function () {
    expect($this->productionHead->canAcknowledge($this->qa->id))->toBeFalse();
});

it('does not let a sub-department head acknowledge upward into the parent', function () {
    $roomHead = User::create([
        'name' => 'Room Head', 'email' => 'room-head@example.com',
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => $this->pickingRoom->id,
    ]);

    expect($roomHead->canAcknowledge($this->pickingRoom->id))->toBeTrue()
        ->and($roomHead->canAcknowledge($this->production->id))->toBeFalse();
});

it('keeps QA out of acknowledging, parent or not', function () {
    $qaManager = User::create([
        'name' => 'Wallika', 'email' => 'qa-parent@example.com',
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => $this->qa->id,
    ]);

    expect($qaManager->canAcknowledge($this->pickingRoom->id))->toBeFalse();
});

it('still refuses someone too junior to acknowledge', function () {
    $operator = User::create([
        'name' => 'Operator', 'email' => 'pd-operator@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->production->id,
    ]);

    expect($operator->canAcknowledge($this->pickingRoom->id))->toBeFalse();
});

/**
 * The department head is found by looking for a manager inside the department.
 * A sub-department usually has no user of its own, so the list would show a
 * blank where Production's head belongs - which reads as "nobody owns this".
 */
it('shows the parent head when the sub-department has none of its own', function () {
    expect($this->pickingRoom->responsibleManager()?->id)->toBe($this->productionHead->id);
});

it('prefers a head of its own when there is one', function () {
    $roomHead = User::create([
        'name' => 'Room Head', 'email' => 'own-head@example.com',
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => $this->pickingRoom->id,
    ]);

    expect($this->pickingRoom->fresh()->responsibleManager()?->id)->toBe($roomHead->id);
});

it('lets an admin create a department under another', function () {
    $admin = User::create([
        'name' => 'Admin', 'email' => 'dept-admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 9,
        'department_id' => $this->qa->id,
    ]);

    $this->actingAs($admin)
        ->post(route('departments.store'), [
            'dept_name' => 'Production (ห้องบรรจุ)',
            'dept_code' => 'PDB',
            'visibility_type' => 'isolated',
            'parent_department_id' => $this->production->id,
        ])
        ->assertRedirect(route('departments.index'));

    expect(Department::where('dept_code', 'PDB')->first()->parent_department_id)
        ->toBe($this->production->id);
});

/**
 * A department that is its own parent, or the parent of its own parent, would
 * make canAcknowledge and any future roll-up walk in circles.
 */
it('refuses to file a department under itself', function () {
    $admin = User::create([
        'name' => 'Admin', 'email' => 'loop-admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 9,
        'department_id' => $this->qa->id,
    ]);

    $this->actingAs($admin)
        ->put(route('departments.update', $this->production), [
            'dept_name' => 'Production',
            'dept_code' => 'PD',
            'visibility_type' => 'isolated',
            'parent_department_id' => $this->production->id,
        ])
        ->assertSessionHasErrors('parent_department_id');
});

it('refuses to file a department under its own child', function () {
    $admin = User::create([
        'name' => 'Admin', 'email' => 'loop2-admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 9,
        'department_id' => $this->qa->id,
    ]);

    $this->actingAs($admin)
        ->put(route('departments.update', $this->production), [
            'dept_name' => 'Production',
            'dept_code' => 'PD',
            'visibility_type' => 'isolated',
            'parent_department_id' => $this->pickingRoom->id,
        ])
        ->assertSessionHasErrors('parent_department_id');
});
