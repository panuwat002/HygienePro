<?php

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;

/**
 * Two things the employee page could not do.
 *
 * Searching skipped `fullname` - the one column the list prints as the
 * person's name - and looked at `fname`/`lname`, which are nullable, were
 * added in a later migration, and are empty for everyone imported from Excel.
 *
 * And nothing anywhere could set `is_active`, although the weekly roster, the
 * inspection round's target list and the area dashboard all filter on it. The
 * only way to take a leaver off the roster was to delete them, which fails on
 * inspection_logs' RESTRICT foreign key for anyone ever inspected.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production (ห้องแคะ)', 'dept_code' => 'PDK', 'visibility_type' => 'isolated',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 9,
        'department_id' => $this->dept->id,
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 07.00-16.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '07:00:00', 'end_time' => '16:00:00',
    ]);

    // Imported from Excel: a fullname and nothing in fname/lname.
    $this->imported = Employee::create([
        'employee_id' => '65990', 'prefix' => 'น.ส.', 'fullname' => 'มา ติน ซาน',
        'department_id' => $this->dept->id, 'shift_id' => $this->shift->id,
        'qr_code_hash' => 'jt0xpAiScS3', 'is_active' => true,
    ]);
});

function searchEmployees($test, array $params)
{
    return $test->actingAs($test->admin)
        ->get(route('employees.index', $params))
        ->assertSuccessful()
        ->viewData('employees');
}

it('finds somebody by the name printed on their own row', function () {
    expect(searchEmployees($this, ['search' => 'มา ติน ซาน'])->pluck('id')->all())
        ->toBe([$this->imported->id]);
});

it('finds somebody by part of that name', function () {
    expect(searchEmployees($this, ['search' => 'ติน'])->pluck('id')->all())
        ->toBe([$this->imported->id]);
});

it('finds somebody by employee code', function () {
    expect(searchEmployees($this, ['search' => '65990'])->pluck('id')->all())
        ->toBe([$this->imported->id]);
});

/**
 * A code pasted from Excel or read by a scanner carries whitespace.
 */
it('ignores space around what was typed', function () {
    expect(searchEmployees($this, ['search' => '  65990 '])->pluck('id')->all())
        ->toBe([$this->imported->id]);
});

/**
 * The reported symptom: searching from page 6 showed "ยังไม่มีข้อมูลพนักงาน"
 * over a row that was really there, because ?page=6 outlived the filter.
 */
it('does not strand a search on a page number left over from browsing', function () {
    $this->actingAs($this->admin)
        ->get(route('employees.index', ['search' => '65990', 'page' => 6]))
        ->assertRedirect(route('employees.index', ['search' => '65990']));
});

it('shows only people who still work here by default', function () {
    $left = Employee::create([
        'employee_id' => '65991', 'fullname' => 'จอ มิน อู', 'department_id' => $this->dept->id,
        'shift_id' => $this->shift->id, 'qr_code_hash' => 'sbITwrSI5ml', 'is_active' => false,
    ]);

    $ids = searchEmployees($this, [])->pluck('id')->all();

    expect($ids)->toContain($this->imported->id)
        ->and($ids)->not->toContain($left->id);

    expect(searchEmployees($this, ['status' => 'inactive'])->pluck('id')->all())->toBe([$left->id]);
    expect(searchEmployees($this, ['status' => 'all'])->pluck('id')->all())->toHaveCount(2);
});

it('takes a leaver off the roster without touching their record', function () {
    $this->actingAs($this->admin)
        ->post(route('employees.set-active', $this->imported->id), ['is_active' => 0])
        ->assertSessionHas('success');

    expect($this->imported->refresh()->is_active)->toBeFalse();

    // The weekly roster is the screen this exists for.
    $roster = $this->actingAs($this->admin)
        ->get(route('departments.roster', $this->dept->id))
        ->assertSuccessful()
        ->viewData('employees');

    expect($roster->pluck('id')->all())->not->toContain($this->imported->id);
});

it('brings somebody back', function () {
    $this->imported->update(['is_active' => false]);

    $this->actingAs($this->admin)
        ->post(route('employees.set-active', $this->imported->id), ['is_active' => 1])
        ->assertSessionHas('success');

    expect($this->imported->refresh()->is_active)->toBeTrue();
});

/**
 * Deleting an inspected employee hit inspection_logs' RESTRICT key and came
 * back as a raw 500 with nothing saying what to do instead.
 */
it('refuses to delete somebody whose inspection history would go with them', function () {
    $session = InspectionSession::factory()->create(['department_id' => $this->dept->id]);

    InspectionLog::create([
        'session_id' => $session->id, 'employee_id' => $this->imported->id,
        'checkpoint_id' => Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person'])->id,
        'result' => 'pass', 'inspected_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->delete(route('employees.destroy', $this->imported->id))
        ->assertSessionHas('error');

    expect(Employee::find($this->imported->id))->not->toBeNull();
});

it('still deletes somebody who was never inspected', function () {
    $this->actingAs($this->admin)
        ->delete(route('employees.destroy', $this->imported->id))
        ->assertSessionHas('success');

    expect(Employee::find($this->imported->id))->toBeNull();
});

/**
 * One inspected person in a bulk selection must neither be deleted nor stop
 * the rest of the selection being deleted.
 */
it('deletes the rest of a bulk selection and says who it kept', function () {
    $fresh = Employee::create([
        'employee_id' => '65992', 'fullname' => 'แทจ แทจ อ่อง', 'department_id' => $this->dept->id,
        'shift_id' => $this->shift->id, 'qr_code_hash' => 'KDBmdjIdH', 'is_active' => true,
    ]);

    $session = InspectionSession::factory()->create(['department_id' => $this->dept->id]);
    InspectionLog::create([
        'session_id' => $session->id, 'employee_id' => $this->imported->id,
        'checkpoint_id' => Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person'])->id,
        'result' => 'pass', 'inspected_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->post(route('employees.bulk-delete'), [
            'employee_ids' => [$this->imported->id, $fresh->id],
        ])
        ->assertSessionHas('warning');

    expect(Employee::find($fresh->id))->toBeNull()
        ->and(Employee::find($this->imported->id))->not->toBeNull();
});

/**
 * The root cause of "ค้นหาแล้วไม่เจอ", and the one my earlier fixes missed.
 *
 * The filter bar always submits every field, so a search carries
 * `checkpoint_count=` even when no checkpoint filter was chosen. Laravel's
 * ConvertEmptyStringsToNull turns that empty string into null before the
 * controller sees it, so the `!== ''` guard - written to let a deliberate "0"
 * through, which filled() would have dropped - never fires. The request then
 * filters on (int) null === 0, meaning "people with no checkpoints at all".
 *
 * Browsing never triggered it: /employees?page=6 carries no checkpoint_count
 * key, so has() is false. Only submitting the form did.
 */
it('does not filter on zero checkpoints just because the filter was left blank', function () {
    $checkpoint = Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person']);
    $this->imported->checkpoints()->attach($checkpoint->id);

    // Exactly what the filter bar submits when only the search box is filled.
    $found = searchEmployees($this, [
        'search' => '65990',
        'department_id' => '',
        'shift_id' => '',
        'checkpoint_count' => '',
        'status' => 'active',
    ]);

    expect($found->pluck('id')->all())->toBe([$this->imported->id]);
});

it('still filters on zero when zero is deliberately chosen', function () {
    $checkpoint = Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person']);
    $this->imported->checkpoints()->attach($checkpoint->id);

    $bare = Employee::create([
        'employee_id' => '65993', 'fullname' => 'ไม่มีจุดตรวจ เลย', 'department_id' => $this->dept->id,
        'shift_id' => $this->shift->id, 'qr_code_hash' => 'none1', 'is_active' => true,
    ]);

    expect(searchEmployees($this, ['checkpoint_count' => '0'])->pluck('id')->all())
        ->toBe([$bare->id]);
});

it('still filters on a real checkpoint count', function () {
    $checkpoint = Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person']);
    $this->imported->checkpoints()->attach($checkpoint->id);

    expect(searchEmployees($this, ['checkpoint_count' => '1'])->pluck('id')->all())
        ->toBe([$this->imported->id]);
});

/**
 * The bulk-checkpoint page carried its own copy of both filters and had the
 * same two faults. It is the page you go to precisely to find people by their
 * checkpoint count, so a blank filter silently meaning "zero" hurt most here.
 */
it('applies the same filters on the bulk checkpoint page', function () {
    $checkpoint = Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person']);
    $this->imported->checkpoints()->attach($checkpoint->id);

    $found = $this->actingAs($this->admin)
        ->get(route('employees.bulk-person', [
            'search' => 'มา ติน ซาน',
            'department_id' => '',
            'checkpoint_count' => '',
        ]))
        ->assertSuccessful()
        ->viewData('employees');

    expect($found->pluck('id')->all())->toBe([$this->imported->id]);
});
