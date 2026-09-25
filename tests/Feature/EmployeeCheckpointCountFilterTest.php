<?php

/**
 * Finding the people who belong in ห้องแคะ.
 *
 * Staff there skip the air shower, so they carry nine checkpoints where the
 * rest of Production carries ten. That difference is already in the data, but
 * nothing on screen lets you act on it: the only way to separate 111 people
 * into two groups was to read a badge on each row, one page of twenty at a
 * time, six pages deep.
 *
 * The count is a way to FIND them once, not a way to identify them forever -
 * add an eleventh checkpoint and every number here moves. What the filter is
 * for is the one-time move into their own department, which is the identity
 * that lasts.
 */

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;

beforeEach(function () {
    $this->production = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 07.00-16.00', 'start_time' => '07:00:00', 'end_time' => '16:00:00',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'cp-filter-admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 9,
        'department_id' => $this->production->id,
    ]);

    $this->checkpoints = collect(range(1, 10))->map(fn ($i) => Checkpoint::create([
        'title' => 'Check ' . $i, 'is_active' => true, 'type' => 'person',
    ]));

    $this->staffSeq = 0;
});

function staffWithCheckpoints($ctx, int $howMany): Employee
{
    $ctx->staffSeq++;

    $employee = Employee::create([
        'employee_id' => 'C' . $ctx->staffSeq, 'fullname' => 'Worker ' . $ctx->staffSeq,
        'department_id' => $ctx->production->id, 'shift_id' => $ctx->shift->id,
        'qr_code_hash' => 'ch' . $ctx->staffSeq, 'is_active' => true,
    ]);

    $employee->checkpoints()->attach($ctx->checkpoints->take($howMany)->pluck('id'));

    return $employee;
}

it('narrows the roster to everyone on a given number of checkpoints', function () {
    $nine = staffWithCheckpoints($this, 9);
    staffWithCheckpoints($this, 10);
    staffWithCheckpoints($this, 10);

    $response = $this->actingAs($this->admin)
        ->get(route('employees.index', ['checkpoint_count' => 9]))
        ->assertSuccessful();

    $listed = $response->viewData('employees');

    expect($listed->pluck('id')->all())->toBe([$nine->id]);
});

it('leaves the roster whole when no count is asked for', function () {
    staffWithCheckpoints($this, 9);
    staffWithCheckpoints($this, 10);

    $listed = $this->actingAs($this->admin)
        ->get(route('employees.index'))
        ->assertSuccessful()
        ->viewData('employees');

    expect($listed->total())->toBe(2);
});

/**
 * Somebody with no checkpoints at all is a configuration mistake - they will
 * be inspected against nothing - so zero has to be reachable rather than
 * silently reading as "no filter".
 */
it('can single out people with no checkpoints at all', function () {
    $none = staffWithCheckpoints($this, 0);
    staffWithCheckpoints($this, 10);

    $listed = $this->actingAs($this->admin)
        ->get(route('employees.index', ['checkpoint_count' => 0]))
        ->assertSuccessful()
        ->viewData('employees');

    expect($listed->pluck('id')->all())->toBe([$none->id]);
});

it('offers the counts that actually exist, with how many people are on each', function () {
    staffWithCheckpoints($this, 9);
    staffWithCheckpoints($this, 9);
    staffWithCheckpoints($this, 10);

    $breakdown = $this->actingAs($this->admin)
        ->get(route('employees.index'))
        ->assertSuccessful()
        ->viewData('checkpointCountOptions');

    // Keyed by checkpoint count, valued by headcount.
    expect($breakdown->toArray())->toBe([9 => 2, 10 => 1]);
});

it('filters the bulk checkpoint screen the same way', function () {
    $nine = staffWithCheckpoints($this, 9);
    staffWithCheckpoints($this, 10);

    $listed = $this->actingAs($this->admin)
        ->get(route('employees.bulk-person', ['checkpoint_count' => 9]))
        ->assertSuccessful()
        ->viewData('employees');

    expect($listed->pluck('id')->all())->toBe([$nine->id]);
});

it('combines with the department filter rather than replacing it', function () {
    $otherDept = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);

    $mine = staffWithCheckpoints($this, 9);
    $theirs = staffWithCheckpoints($this, 9);
    $theirs->update(['department_id' => $otherDept->id]);

    $listed = $this->actingAs($this->admin)
        ->get(route('employees.index', [
            'checkpoint_count' => 9,
            'department_id' => $this->production->id,
        ]))
        ->assertSuccessful()
        ->viewData('employees');

    expect($listed->pluck('id')->all())->toBe([$mine->id]);
});
