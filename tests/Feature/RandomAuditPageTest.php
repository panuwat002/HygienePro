<?php

use App\Models\Department;
use App\Models\RandomAudit;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Nothing in the app has ever listed the random audit schedule. The dashboard
 * showed the audits due today and that was all, so "was the sampling plan
 * followed?" - the question an external audit asks - had no answer in the
 * system at all.
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 23, 9, 0));

    $this->production = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->pdk = Department::create([
        'dept_name' => 'Production (ห้องแคะ)', 'dept_code' => 'PDK', 'visibility_type' => 'isolated',
        'parent_department_id' => $this->production->id,
    ]);
    $this->packing = Department::create([
        'dept_name' => 'Packing', 'dept_code' => 'PK', 'visibility_type' => 'isolated',
    ]);

    $this->head = User::create([
        'name' => 'Production Head', 'email' => 'head@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->production->id,
    ]);
    $this->staff = User::create([
        'name' => 'Staff', 'email' => 'staff@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->production->id,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function auditFor(Department $dept, string $status = RandomAudit::PENDING, string $shift = 'morning'): RandomAudit
{
    return RandomAudit::factory()->create([
        'department_id' => $dept->id,
        'audit_date' => now()->toDateString(),
        'shift' => $shift,
        'status' => $status,
    ]);
}

it('lists this week audits to a supervisor', function () {
    auditFor($this->production);

    $this->actingAs($this->head)
        ->get(route('audits.index'))
        ->assertOk()
        ->assertSee('Production')
        ->assertSee('รอตรวจ');
});

it('is closed to somebody who is not expected to carry one out', function () {
    $this->actingAs($this->staff)
        ->get(route('audits.index'))
        ->assertForbidden();
});

/**
 * ห้องแคะ was split out of Production as its own department, and its head is
 * still Production's. Their audits are that head's business.
 */
it('shows a department head the audits of a sub-department', function () {
    auditFor($this->pdk);

    $this->actingAs($this->head)
        ->get(route('audits.index'))
        ->assertOk()
        ->assertSee('ห้องแคะ', false);
});

it('does not show one department the audits of another', function () {
    auditFor($this->packing);

    $this->actingAs($this->head)
        ->get(route('audits.index'))
        ->assertOk()
        ->assertDontSee('Packing');
});

it('counts each status for the week', function () {
    auditFor($this->production, RandomAudit::COMPLETED, 'morning');
    auditFor($this->pdk, RandomAudit::MISSED, 'afternoon');

    $response = $this->actingAs($this->head)->get(route('audits.index'));

    $counts = $response->viewData('counts');

    expect($counts[RandomAudit::COMPLETED])->toBe(1)
        ->and($counts[RandomAudit::MISSED])->toBe(1);
});

it('shows a chosen week rather than only the current one', function () {
    $lastWeek = now()->subWeek();

    RandomAudit::factory()->create([
        'department_id' => $this->production->id,
        'audit_date' => $lastWeek->toDateString(),
        'week_number' => $lastWeek->isoWeek(),
        'year' => $lastWeek->year,
    ]);

    $response = $this->actingAs($this->head)->get(route('audits.index', [
        'week' => $lastWeek->isoWeek(), 'year' => $lastWeek->year,
    ]));

    expect($response->viewData('audits'))->toHaveCount(1);
});

/**
 * The scheduled command retires overdue audits at 07:00. Between the day
 * ending and that running, a row still reads 'รอตรวจ' - the page says so
 * rather than letting it pass for outstanding work.
 */
it('flags an audit whose day has gone by as overdue without changing it', function () {
    $stale = RandomAudit::factory()->create([
        'department_id' => $this->production->id,
        'audit_date' => now()->subDays(2)->toDateString(),
    ]);

    $response = $this->actingAs($this->head)->get(route('audits.index'));

    expect($response->viewData('audits')->first()->is_overdue)->toBeTrue()
        ->and($stale->refresh()->status)->toBe(RandomAudit::PENDING);
});
