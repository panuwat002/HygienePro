<?php

/**
 * The 08:30 CAR notice announced everything that was not 'closed' as
 * "ค้างแก้ไขเกินกำหนด", with an hour count measured to the present moment.
 *
 * A finding fixed and written up on the 7th was therefore still being named
 * in the group chat on the 9th at "ล่าช้า 47 ชม." - the hours were counting
 * QA's unfinished verification and printing it as the department's unfinished
 * repair. The people who had done the work were publicly blamed for it daily,
 * and the person actually holding it up was never mentioned.
 *
 * One word, "overdue", answering two questions with different owners.
 */

use App\Models\CorrectiveAction;
use App\Models\User;
use App\Notifications\OverdueCARLineNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Carbon::setTestNow('2026-10-09 08:30:00');
    config(['inspection.car.verify_reminder_hours' => 24]);
});

afterEach(fn () => Carbon::setTestNow());

function notYetFixed(string $due = '2026-10-07 08:00:00'): CorrectiveAction
{
    return CorrectiveAction::factory()->create([
        'status' => 'open',
        'due_date' => $due,
        'root_cause' => 'ท่อระบายน้ำมีน้ำขัง',
    ]);
}

function fixedAt(string $resolvedAt, string $due = '2026-10-07 08:00:00'): CorrectiveAction
{
    $resolver = User::factory()->create(['name' => 'Ms. Ketmanee Tansayan']);

    return CorrectiveAction::factory()->create([
        'status' => 'resolved',
        'due_date' => $due,
        'resolved_at' => $resolvedAt,
        'resolved_by' => $resolver->id,
        'action_taken' => 'ล้างทำความสะอาด',
        'root_cause' => 'ท่อระบายน้ำมีน้ำขัง',
    ]);
}

test('work that is genuinely not done is addressed to the department', function () {
    $message = (new OverdueCARLineNotification(collect([notYetFixed()]), collect()))->toLine(null);

    expect($message)->toContain('ยังไม่ได้แก้ไข เกินกำหนดแล้ว')
        ->and($message)->toContain('แผนกเจ้าของปัญหาต้องดำเนินการ')
        ->and($message)->not->toContain('รอ QA ทวนสอบ');
});

test('work that is done is never announced as not done', function () {
    $message = (new OverdueCARLineNotification(collect(), collect([fixedAt('2026-10-07 16:33:00')])))->toLine(null);

    expect($message)->not->toContain('ยังไม่ได้แก้ไข')
        ->and($message)->toContain('แก้ไขแล้ว รอ QA ทวนสอบ')
        ->and($message)->toContain('แผนกที่แก้ไขทำงานเสร็จแล้ว');
});

test('the waiting list names who fixed it and how long QA has had it', function () {
    $message = (new OverdueCARLineNotification(collect(), collect([fixedAt('2026-10-07 16:33:00')])))->toLine(null);

    expect($message)->toContain('แก้ไขโดย: Ms. Ketmanee Tansayan')
        ->and($message)->toContain('รอทวนสอบ 39 ชม.');
});

test('the delay on a repair stops when the repair stops', function () {
    // Due 07/10 08:00, fixed 07/10 16:33 - eight hours late, and eight hours
    // late forever. The old message printed 47 and climbing, because it was
    // measuring to the present moment.
    $action = fixedAt('2026-10-07 16:33:00');

    $onThe9th = (new OverdueCARLineNotification(collect(), collect([$action])))->toLine(null);

    Carbon::setTestNow('2026-10-20 08:30:00');
    $elevenDaysLater = (new OverdueCARLineNotification(collect(), collect([$action])))->toLine(null);

    expect($onThe9th)->toContain('แก้ช้ากว่ากำหนด 8 ชม.')
        ->and($elevenDaysLater)->toContain('แก้ช้ากว่ากำหนด 8 ชม.');
});

test('a repair finished on time carries no delay at all', function () {
    $action = fixedAt('2026-10-06 09:00:00', '2026-10-07 08:00:00');

    $message = (new OverdueCARLineNotification(collect(), collect([$action])))->toLine(null);

    expect($message)->not->toContain('แก้ช้ากว่ากำหนด');
});

test('both lists appear when both are true, each with its own owner', function () {
    $message = (new OverdueCARLineNotification(
        collect([notYetFixed()]),
        collect([fixedAt('2026-10-07 16:33:00')])
    ))->toLine(null);

    expect($message)->toContain('ยังไม่ได้แก้ไข เกินกำหนดแล้ว')
        ->and($message)->toContain('แก้ไขแล้ว รอ QA ทวนสอบ');
});

test('the command does not count a fixed finding as overdue', function () {
    Mail::fake();
    Notification::fake();
    User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
    fixedAt('2026-10-07 16:33:00');

    $this->artisan('car:check-overdue')->assertSuccessful();

    // The complaint that started this: the department had finished, and the
    // report still went out naming them.
    Mail::assertNothingSent();
});

test('the command still mails a finding nobody has fixed', function () {
    Mail::fake();
    Notification::fake();
    User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
    notYetFixed();

    $this->artisan('car:check-overdue')->assertSuccessful();

    Mail::assertSent(\App\Mail\CAROverdueReport::class);
});

test('a finding fixed within the reminder window is left alone entirely', function () {
    Mail::fake();
    Notification::fake();
    User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
    fixedAt('2026-10-09 06:00:00'); // two and a half hours ago

    $this->artisan('car:check-overdue')->assertSuccessful();

    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

test('a verified finding is not chased for verification', function () {
    Mail::fake();
    Notification::fake();
    User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
    CorrectiveAction::factory()->create([
        'status' => 'verified',
        'due_date' => '2026-10-07 08:00:00',
        'resolved_at' => '2026-10-07 16:33:00',
        'action_taken' => 'ล้างทำความสะอาด',
    ]);

    $this->artisan('car:check-overdue')->assertSuccessful();

    Mail::assertNothingSent();
    Notification::assertNothingSent();
});
