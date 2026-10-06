<?php

/**
 * The dashboard's "Active Inspections" badge turned red once a round had been
 * open two hours, which on a 07.00-16.00 shift is the job being done. Every
 * normal morning therefore showed red, and an admin asking "should I force
 * this closed?" was being asked a question the screen had invented.
 *
 * The badge now measures against the end of the shift and against the moment
 * the auto-close job is due to act, so red means only one thing: the system
 * should already have closed this round and did not.
 */

use App\Models\Department;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    Http::fake();

    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'badge-admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 5,
        'department_id' => $this->dept->id,
    ]);
    $this->admin->forceFill(['email_verified_at' => now()])->save();

    $this->inspector = User::create([
        'name' => 'Inspector', 'email' => 'badge-inspector@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->dept->id,
    ]);
    $this->inspector->forceFill(['email_verified_at' => now()])->save();

    // The shift from the screenshot that prompted this: 07.00-16.00.
    Shift::create(['shift_name' => 'morning', 'start_time' => '07:00:00', 'end_time' => '16:00:00']);
});

afterEach(function () {
    Carbon::setTestNow();
});

function roundInProgress($ctx, string $shift = 'morning', ?string $date = null): InspectionSession
{
    return InspectionSession::create([
        'inspector_id'    => $ctx->inspector->id,
        'department_id'   => $ctx->dept->id,
        'type'            => 'personnel',
        'inspection_date' => $date ?? now()->toDateString(),
        'shift'           => $shift,
        'round'           => 1,
        'status'          => 'in_progress',
        'is_locked'       => false,
    ]);
}

test('shiftEndsAt reads the end of the shift on the round own date', function () {
    Carbon::setTestNow('2026-10-06 09:14:00');
    $session = roundInProgress($this);

    expect($session->shiftEndsAt()->toDateTimeString())->toBe('2026-10-06 16:00:00');
});

test('autoCloseDueAt is the shift end plus the configured grace', function () {
    Carbon::setTestNow('2026-10-06 09:14:00');
    config(['inspection.auto_close.grace_hours' => 2]);
    $session = roundInProgress($this);

    expect($session->autoCloseDueAt()->toDateTimeString())->toBe('2026-10-06 18:00:00');
});

test('a shift with no end time leaves both unknown rather than guessing', function () {
    Carbon::setTestNow('2026-10-06 09:14:00');
    Shift::create(['shift_name' => 'openended', 'start_time' => '07:00:00', 'end_time' => null]);
    $session = roundInProgress($this, 'openended');

    expect($session->shiftEndsAt())->toBeNull()
        ->and($session->autoCloseDueAt())->toBeNull();
});

test('autoCloseDueAt promises nothing while auto-close is switched off', function () {
    Carbon::setTestNow('2026-10-06 09:14:00');
    config(['inspection.auto_close.enabled' => false]);
    $session = roundInProgress($this);

    expect($session->autoCloseDueAt())->toBeNull();
});

test('a round seven hours into its own shift is not flagged', function () {
    // 09:14 start, now 16:30 - the exact row that prompted this. Under the old
    // rule it was red; the shift has not ended, so there is nothing to say.
    Carbon::setTestNow('2026-10-06 15:30:00');
    roundInProgress($this);

    $html = $this->actingAs($this->admin)->get('/dashboard')->assertSuccessful()->getContent();

    expect($html)->toContain('เลิกกะ 16:00 น.')
        ->and($html)->not->toContain('ยังไม่ได้ปิดรอบ')
        ->and($html)->not->toContain('เลยเวลาที่ระบบควรปิดให้แล้ว');
});

test('past the shift but inside the grace, it says when the system will close it', function () {
    Carbon::setTestNow('2026-10-06 17:00:00');
    config(['inspection.auto_close.grace_hours' => 2]);
    roundInProgress($this);

    $html = $this->actingAs($this->admin)->get('/dashboard')->assertSuccessful()->getContent();

    expect($html)->toContain('ระบบจะปิดให้ 18:00 น.')
        ->and($html)->not->toContain('เลยเวลาที่ระบบควรปิดให้แล้ว');
});

test('past the moment the system should have closed it, the badge turns red', function () {
    Carbon::setTestNow('2026-10-06 19:00:00');
    config(['inspection.auto_close.grace_hours' => 2]);
    roundInProgress($this);

    $html = $this->actingAs($this->admin)->get('/dashboard')->assertSuccessful()->getContent();

    expect($html)->toContain('เลยเวลาที่ระบบควรปิดให้แล้ว')
        ->and($html)->toContain('bg-danger');
});

test('a round the system can never close says so instead of staying silent', function () {
    Carbon::setTestNow('2026-10-06 17:00:00');
    Shift::create(['shift_name' => 'openended', 'start_time' => '07:00:00', 'end_time' => null]);
    roundInProgress($this, 'openended');

    $html = $this->actingAs($this->admin)->get('/dashboard')->assertSuccessful()->getContent();

    expect($html)->toContain('ไม่ทราบเวลาเลิกกะ');
});

test('the dashboard and the auto-close job agree on when the round is due', function () {
    // Two copies of this calculation is how the screen comes to promise 18:00
    // while the job closes at some other time. The job now reads the session.
    Carbon::setTestNow('2026-10-06 09:14:00');
    $session = roundInProgress($this);

    expect(app(\App\Services\InspectionService::class)->shiftEndAt($session)?->toDateTimeString())
        ->toBe($session->shiftEndsAt()->toDateTimeString());
});
