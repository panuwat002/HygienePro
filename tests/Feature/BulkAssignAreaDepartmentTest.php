<?php

use App\Models\Department;
use App\Models\Location;
use App\Models\User;

/**
 * Every area has to be told who runs it before findings raised in it reach the
 * right people, and a plant has dozens of rooms that mostly belong to the same
 * department. Opening each one in turn to set the same value is the kind of
 * work that does not get finished - and a room left unassigned quietly bills
 * its findings to whoever walked the round, which is the thing this was all
 * for.
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

    $this->rooms = collect(['ห้อง เจาะมะพร้าว', 'ห้อง บรรจุน้ำขวด', 'ห้อง ต้ม'])
        ->map(fn ($name) => Location::create(['location_name' => $name]));
});

function assignRooms($ctx, $rooms, $departmentId)
{
    return $ctx->actingAs($ctx->admin)->post(route('locations.bulk-department'), [
        'location_ids' => collect($rooms)->pluck('id')->all(),
        'department_id' => $departmentId,
    ]);
}

it('sets one department on every area selected', function () {
    assignRooms($this, $this->rooms, $this->production->id)->assertSessionHas('success');

    foreach ($this->rooms as $room) {
        expect($room->fresh()->department_id)->toBe($this->production->id);
    }
});

it('leaves the areas that were not selected alone', function () {
    $untouched = $this->rooms->last();

    assignRooms($this, $this->rooms->take(2), $this->production->id);

    expect($untouched->fresh()->department_id)->toBeNull();
});

/**
 * Assigned by mistake, handed back to the fallback.
 */
it('clears the department when none is chosen', function () {
    assignRooms($this, $this->rooms, $this->production->id);
    assignRooms($this, $this->rooms, '')->assertSessionHas('success');

    foreach ($this->rooms as $room) {
        expect($room->fresh()->department_id)->toBeNull();
    }
});

it('refuses a department that does not exist', function () {
    $this->actingAs($this->admin)->post(route('locations.bulk-department'), [
        'location_ids' => [$this->rooms->first()->id],
        'department_id' => 99999,
    ])->assertSessionHasErrors('department_id');

    expect($this->rooms->first()->fresh()->department_id)->toBeNull();
});

it('refuses a request that selects nothing', function () {
    $this->actingAs($this->admin)->post(route('locations.bulk-department'), [
        'location_ids' => [],
        'department_id' => $this->production->id,
    ])->assertSessionHasErrors('location_ids');
});

/**
 * Which rooms still have no owner has to be obvious from the list, or the job
 * of assigning them never looks finished or unfinished.
 */
it('shows on the list which areas still have no owner', function () {
    $this->rooms->first()->update(['department_id' => $this->production->id]);

    $this->actingAs($this->admin)
        ->get(route('locations.index'))
        ->assertSuccessful()
        ->assertSee('Production')
        ->assertSee('ยังไม่ระบุ');
});
