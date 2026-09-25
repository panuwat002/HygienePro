<?php

use App\Models\Department;
use App\Models\RandomAudit;
use App\Models\Shift;
use App\Models\User;
use App\Services\InspectionService;
use App\Support\RandomAuditLoop;
use Illuminate\Support\Carbon;

/**
 * `audit:generate` has drawn a department, a date and a shift every week since
 * July, and nothing ever wrote random_audits.status again - so every audit the
 * system has ever scheduled is still 'pending', auditor_id/started_at/
 * completed_at have never held a value, and isMissed() was never called.
 *
 * These are the three moments that now move an audit: a supervisor's round
 * claims it, finishing that round completes it, and a day going by without
 * either records it missed.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qaDept = Department::create([
        'dept_name' => 'QA', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->supervisor = User::create([
        'name' => 'QA Supervisor', 'email' => 'sup@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->qaDept->id,
    ]);
    $this->inspector = User::create([
        'name' => 'Inspector', 'email' => 'inspector@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->qaDept->id,
    ]);

    $this->morning = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    // 09:00 so Shift::detectCurrent() reports 'morning' and the business day is today.
    Carbon::setTestNow(Carbon::today()->setTime(9, 0));

    $this->service = app(InspectionService::class);
    $this->loop = app(RandomAuditLoop::class);

    $this->audit = RandomAudit::factory()->create([
        'department_id' => $this->dept->id,
        'audit_date' => now()->toDateString(),
        'shift' => 'morning',
    ]);
});

afterEach(fn () => Carbon::setTestNow());

it('is claimed by a supervisor round on the day and shift it was drawn for', function () {
    $session = $this->service->startSession($this->supervisor, $this->dept->id, 'area');

    $this->audit->refresh();

    expect($this->audit->status)->toBe(RandomAudit::IN_PROGRESS)
        ->and($this->audit->auditor_id)->toBe($this->supervisor->id)
        ->and($this->audit->started_at)->not->toBeNull()
        ->and($session->refresh()->random_audit_id)->toBe($this->audit->id)
        ->and($session->is_audit)->toBeTrue();
});

/**
 * The audit is a cross-check ON the ordinary rounds. If the usual inspector's
 * usual round ticked it off, the schedule would close itself.
 */
it('is not claimed by an ordinary inspector doing an ordinary round', function () {
    $session = $this->service->startSession($this->inspector, $this->dept->id, 'area');

    expect($this->audit->refresh()->status)->toBe(RandomAudit::PENDING)
        ->and($session->refresh()->random_audit_id)->toBeNull();
});

it('is not claimed by a round on another department', function () {
    $other = Department::create([
        'dept_name' => 'Packing', 'dept_code' => 'PK', 'visibility_type' => 'isolated',
    ]);

    $this->service->startSession($this->supervisor, $other->id, 'area');

    expect($this->audit->refresh()->status)->toBe(RandomAudit::PENDING);
});

it('is not claimed by a round on a different shift', function () {
    $this->audit->update(['shift' => 'afternoon']);

    $this->service->startSession($this->supervisor, $this->dept->id, 'area');

    expect($this->audit->refresh()->status)->toBe(RandomAudit::PENDING);
});

/**
 * A personnel round stores its shift as keys ('custom_3'), never as 'morning',
 * so the two can only meet on the canonical shift_type.
 */
it('matches a personnel round that names the shift by key', function () {
    $session = $this->service->startSession(
        $this->supervisor, $this->dept->id, 'personnel', false, 'custom_' . $this->morning->id
    );

    expect($this->audit->refresh()->status)->toBe(RandomAudit::IN_PROGRESS)
        ->and($session->refresh()->random_audit_id)->toBe($this->audit->id);
});

it('is completed when the round that claimed it is finished', function () {
    $session = $this->service->startSession($this->supervisor, $this->dept->id, 'area');
    $this->service->finishSession($session);

    $this->audit->refresh();

    expect($this->audit->status)->toBe(RandomAudit::COMPLETED)
        ->and($this->audit->completed_at)->not->toBeNull();
});

it('keeps the original completion time when a reopened round is finished again', function () {
    $session = $this->service->startSession($this->supervisor, $this->dept->id, 'area');
    $this->service->finishSession($session);
    $first = $this->audit->refresh()->completed_at;

    Carbon::setTestNow(now()->addHours(2));
    $reopened = $this->service->startSession($this->supervisor, $this->dept->id, 'area');
    $this->service->finishSession($reopened);

    expect($this->audit->refresh()->completed_at->toDateTimeString())
        ->toBe($first->toDateTimeString());
});

/**
 * "เริ่มรอบใหม่" closes the old round without finishing it. The audit must
 * move across to the new round rather than stay stuck on the abandoned one.
 */
it('moves to the new round when the claiming round is abandoned', function () {
    $first = $this->service->startSession($this->supervisor, $this->dept->id, 'area');
    $second = $this->service->startSession($this->supervisor, $this->dept->id, 'area', true);

    expect($second->id)->not->toBe($first->id)
        ->and($second->refresh()->random_audit_id)->toBe($this->audit->id)
        ->and($first->refresh()->random_audit_id)->toBeNull();
});

it('marks audits whose day has passed as missed', function () {
    $stale = RandomAudit::factory()->overdue()->create(['department_id' => $this->dept->id]);

    expect($this->loop->closeMissed())->toBe(1);
    expect($stale->refresh()->status)->toBe(RandomAudit::MISSED);
    expect($this->audit->refresh()->status)->toBe(RandomAudit::PENDING);
});

it('marks an audit somebody started but never finished as missed', function () {
    $stale = RandomAudit::factory()->overdue()->create([
        'department_id' => $this->dept->id,
        'status' => RandomAudit::IN_PROGRESS,
    ]);

    $this->loop->closeMissed();

    expect($stale->refresh()->status)->toBe(RandomAudit::MISSED);
});

it('leaves a completed audit alone however old it is', function () {
    $done = RandomAudit::factory()->overdue(30)->create([
        'department_id' => $this->dept->id,
        'status' => RandomAudit::COMPLETED,
    ]);

    $this->loop->closeMissed();

    expect($done->refresh()->status)->toBe(RandomAudit::COMPLETED);
});

/**
 * A round opened at 02:00 counts as the previous day's work - the rule
 * startSession() dates a session by. Retiring yesterday's audit at 02:00 would
 * cut off a night round that is still entitled to satisfy it.
 */
it('does not retire yesterday audit while the night round can still satisfy it', function () {
    Carbon::setTestNow(Carbon::today()->setTime(2, 0));

    $yesterday = RandomAudit::factory()->create([
        'department_id' => $this->dept->id,
        'audit_date' => now()->subDay()->toDateString(),
    ]);

    expect($this->loop->closeMissed())->toBe(0)
        ->and($yesterday->refresh()->status)->toBe(RandomAudit::PENDING);
});

it('reports how many it retired through the scheduled command', function () {
    // Different days: one audit per department/date/shift is a unique index.
    foreach ([2, 3, 4] as $daysAgo) {
        RandomAudit::factory()->overdue($daysAgo)->create(['department_id' => $this->dept->id]);
    }

    $this->artisan('audit:close-missed')
        ->expectsOutputToContain('Marked 3 random audit(s) as missed.')
        ->assertSuccessful();
});

/**
 * The dashboard card has always printed {{ $audit->shift_label }} and this
 * model never had one, so that line rendered empty.
 */
it('gives the shift a label the card can print', function () {
    expect($this->audit->shift_label)->toBe('กะเช้า');
});
