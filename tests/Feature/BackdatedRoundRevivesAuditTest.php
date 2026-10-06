<?php

use App\Models\Department;
use App\Models\RandomAudit;
use App\Models\Shift;
use App\Models\User;
use App\Services\InspectionService;
use Illuminate\Support\Carbon;

/**
 * closeMissed() retires a random audit at 07:00 the morning after its date -
 * which is exactly when a round delayed by a late shift roster is still
 * waiting to be typed in. Recording that day's work afterwards left the audit
 * standing as 'ไม่ได้ตรวจ' against a department that had in fact done it.
 *
 * A backdated round can now answer for one. An ordinary round cannot: a round
 * walked today says nothing about an audit drawn for last week.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-06 09:00:00');

    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->supervisor = User::create([
        'name' => 'QA Supervisor', 'email' => 'sup@example.com', 'password' => bcrypt('x'),
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->qa->id,
    ]);

    Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    // Drawn for the 5th, written off at 07:00 on the 6th.
    $this->audit = RandomAudit::factory()->create([
        'department_id' => $this->dept->id,
        'audit_date' => '2026-10-05',
        'shift' => 'morning',
        'status' => RandomAudit::MISSED,
    ]);

    $this->service = app(InspectionService::class);
});

afterEach(fn () => Carbon::setTestNow());

function roundFor($ctx, ?string $backdateTo): \App\Models\InspectionSession
{
    return $ctx->service->startSession(
        $ctx->supervisor, $ctx->dept->id, 'area', false, 'morning', false, null,
        $backdateTo,
        $backdateTo ? 'ได้รับตารางกะจากฝ่ายผลิตล่าช้า' : null
    );
}

it('is taken up again by a round recorded for the day it was drawn for', function () {
    $session = roundFor($this, '2026-10-05');

    expect($this->audit->refresh()->status)->toBe(RandomAudit::IN_PROGRESS)
        ->and($session->refresh()->random_audit_id)->toBe($this->audit->id);
});

it('reaches ตรวจแล้ว when that round is finished', function () {
    $session = roundFor($this, '2026-10-05');
    $this->service->finishSession($session);

    $this->audit->refresh();

    expect($this->audit->status)->toBe(RandomAudit::COMPLETED)
        ->and($this->audit->completed_at)->not->toBeNull()
        ->and($this->audit->auditor_id)->toBe($this->supervisor->id);
});

/**
 * The limit that keeps this from being a way to clear the board. A round
 * walked today answers for today.
 */
it('is left alone by an ordinary round walked today', function () {
    roundFor($this, null);

    expect($this->audit->refresh()->status)->toBe(RandomAudit::MISSED);
});

it('is left alone by a backdated round for a different day', function () {
    roundFor($this, '2026-10-04');

    expect($this->audit->refresh()->status)->toBe(RandomAudit::MISSED);
});

it('is left alone by a backdated round on a different shift', function () {
    $this->audit->update(['shift' => 'afternoon']);

    roundFor($this, '2026-10-05');

    expect($this->audit->refresh()->status)->toBe(RandomAudit::MISSED);
});

/**
 * "ตรวจแล้ว" must not come to mean two different things. The schedule page
 * says which of them were recorded after the fact.
 */
it('says on the schedule that the record arrived late', function () {
    $session = roundFor($this, '2026-10-05');
    $this->service->finishSession($session);

    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->qa->id,
    ]);

    $this->actingAs($admin)
        ->get(route('audits.index', ['week' => Carbon::parse('2026-10-05')->isoWeek(), 'year' => 2026]))
        ->assertSuccessful()
        ->assertSee('ตรวจแล้ว')
        ->assertSee('บันทึกย้อนหลัง');
});
