<?php

use App\Models\Department;
use App\Models\User;

/**
 * A user's department decides what they can see, and a user without one sees no reports at
 * all. That has to be visible from the user list rather than one edit screen at a time.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 5,
        'department_id' => $this->dept->id,
    ]);
});

it('shows each user their department on the list', function () {
    User::create([
        'name' => 'Somchai', 'email' => 'somchai@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->dept->id,
    ]);

    $response = $this->actingAs($this->admin)->get(route('users.index'));

    $response->assertOk()
        ->assertSee('แผนก')
        ->assertSee('Production')
        ->assertSee('PD');
});

it('flags a user who has no department', function () {
    User::create([
        'name' => 'Orphan', 'email' => 'orphan@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => null,
    ]);

    $response = $this->actingAs($this->admin)->get(route('users.index'));

    $response->assertOk()
        ->assertSee('ไม่มีแผนก')
        ->assertSee('มองไม่เห็นรายงาน');
});

it('does not flag an admin, who is not confined to a department', function () {
    $soloAdmin = User::create([
        'name' => 'Solo Admin', 'email' => 'solo@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 5,
        'department_id' => null,
    ]);

    $response = $this->actingAs($soloAdmin)->get(route('users.index'));

    $response->assertOk()->assertDontSee('มองไม่เห็นรายงาน');
});
