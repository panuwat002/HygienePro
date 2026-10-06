<?php

use App\Models\Department;
use App\Models\Location;
use App\Models\Machine;
use App\Models\User;

/**
 * A machine carries no department of its own: it is owned by whoever runs the
 * room it stands in. So putting a machine in the right room IS how its
 * department gets fixed - and doing that one machine at a time, on a room
 * holding twenty-six of them, is work that does not get finished. A machine
 * left in the wrong room sends its findings to the wrong department.
 */
beforeEach(function () {
    $this->production = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->qa->id,
    ]);

    $this->bottling = Location::create([
        'location_name' => 'ห้อง บรรจุน้ำขวด', 'department_id' => $this->production->id,
    ]);
    $this->lab = Location::create([
        'location_name' => 'ห้องแล็บ', 'department_id' => $this->qa->id,
    ]);

    $this->machines = collect(['Capper หัวที่ 3', 'Capper หัวที่ 4', 'เครื่องซีล'])
        ->map(fn ($name) => Machine::create([
            'location_id' => $this->lab->id, 'name' => $name, 'is_active' => true,
        ]));
});

function moveMachines($ctx, $machines, int $locationId)
{
    return $ctx->actingAs($ctx->admin)->post(route('machines.bulk-location'), [
        'machine_ids' => collect($machines)->pluck('id')->all(),
        'location_id' => $locationId,
    ]);
}

it('moves every machine selected into one area', function () {
    moveMachines($this, $this->machines, $this->bottling->id)->assertSessionHas('success');

    foreach ($this->machines as $machine) {
        expect($machine->fresh()->location_id)->toBe($this->bottling->id);
    }
});

/**
 * The point of the move: the department follows, without being set anywhere on
 * the machine.
 */
it('changes which department the machines answer to', function () {
    moveMachines($this, $this->machines, $this->bottling->id);

    foreach ($this->machines as $machine) {
        expect($machine->fresh()->location->department_id)->toBe($this->production->id);
    }
});

it('leaves the machines that were not selected alone', function () {
    $untouched = $this->machines->last();

    moveMachines($this, $this->machines->take(2), $this->bottling->id);

    expect($untouched->fresh()->location_id)->toBe($this->lab->id);
});

it('refuses an area that does not exist', function () {
    $this->actingAs($this->admin)->post(route('machines.bulk-location'), [
        'machine_ids' => [$this->machines->first()->id],
        'location_id' => 99999,
    ])->assertSessionHasErrors('location_id');

    expect($this->machines->first()->fresh()->location_id)->toBe($this->lab->id);
});

it('refuses a request that selects nothing', function () {
    $this->actingAs($this->admin)->post(route('machines.bulk-location'), [
        'machine_ids' => [],
        'location_id' => $this->bottling->id,
    ])->assertSessionHasErrors('machine_ids');
});

/**
 * Which department an area belongs to is what the move is really for, so the
 * chooser says it rather than listing room names alone.
 */
it('names each area department on the move list', function () {
    $this->actingAs($this->admin)
        ->get(route('machines.index'))
        ->assertSuccessful()
        ->assertSee('ห้อง บรรจุน้ำขวด')
        ->assertSee('Production');
});
