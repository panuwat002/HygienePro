<?php

use App\Models\Department;
use App\Models\Location;
use App\Models\Machine;
use App\Models\User;

/**
 * Hundreds of machines, fifteen a page, and nothing to narrow them by. Finding
 * one meant paging through the lot - and picking out a room's worth to move
 * was impossible, because "select all" reaches only the page in front of you.
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
    $this->orphanRoom = Location::create(['location_name' => 'ห้อง ใหม่']);

    $this->capper = Machine::create([
        'location_id' => $this->bottling->id, 'name' => 'Capper หัวที่ 3',
        'code' => 'CAP-03', 'is_active' => true,
    ]);
    $this->sealer = Machine::create([
        'location_id' => $this->bottling->id, 'name' => 'เครื่องซีล',
        'is_active' => false,
    ]);
    $this->orphan = Machine::create([
        'location_id' => $this->orphanRoom->id, 'name' => 'เครื่องใหม่', 'is_active' => true,
    ]);
});

function machineList($ctx, array $params = [])
{
    return $ctx->actingAs($ctx->admin)
        ->get(route('machines.index', $params))
        ->assertSuccessful()
        ->viewData('machines')
        ->pluck('id');
}

it('finds a machine by name', function () {
    expect(machineList($this, ['search' => 'ซีล'])->all())->toBe([$this->sealer->id]);
});

it('finds a machine by code', function () {
    expect(machineList($this, ['search' => 'CAP-03'])->all())->toBe([$this->capper->id]);
});

it('ignores space around what was typed', function () {
    expect(machineList($this, ['search' => '  CAP-03 '])->all())->toBe([$this->capper->id]);
});

it('narrows to one area', function () {
    $found = machineList($this, ['location_id' => $this->bottling->id]);

    expect($found)->toHaveCount(2)
        ->and($found)->not->toContain($this->orphan->id);
});

it('narrows to one department through the area', function () {
    $found = machineList($this, ['department_id' => $this->production->id]);

    expect($found)->toHaveCount(2)
        ->and($found)->not->toContain($this->orphan->id);
});

/**
 * The list somebody actually has to work through: machines whose area has no
 * owner still send their findings to whoever walked the round.
 */
it('lists the machines whose area still has no owner', function () {
    expect(machineList($this, ['department_id' => 'none'])->all())->toBe([$this->orphan->id]);
});

it('narrows by status', function () {
    expect(machineList($this, ['status' => 'inactive'])->all())->toBe([$this->sealer->id]);
});

it('combines the filters', function () {
    $found = machineList($this, [
        'location_id' => $this->bottling->id,
        'status' => 'active',
    ]);

    expect($found->all())->toBe([$this->capper->id]);
});

/**
 * Selecting a room's worth to move means being able to get them on one page.
 */
it('shows more per page when asked', function () {
    $this->actingAs($this->admin)
        ->get(route('machines.index', ['per_page' => 100]))
        ->assertSuccessful()
        ->assertViewHas('machines', fn ($machines) => $machines->perPage() === 100);
});

it('ignores a per page size nobody offered', function () {
    $this->actingAs($this->admin)
        ->get(route('machines.index', ['per_page' => 99999]))
        ->assertSuccessful()
        ->assertViewHas('machines', fn ($machines) => $machines->perPage() === 15);
});

it('does not strand a search on a page number left over from browsing', function () {
    $this->actingAs($this->admin)
        ->get(route('machines.index', ['search' => 'ซีล', 'page' => 4]))
        ->assertRedirect(route('machines.index', ['search' => 'ซีล']));
});
