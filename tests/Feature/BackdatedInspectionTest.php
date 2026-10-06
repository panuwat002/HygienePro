<?php

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use App\Services\InspectionService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * A round could only ever be dated by the clock. QA received the shift roster
 * from Production a day late, could not pick a shift without it, and 5 Oct
 * 2026 was left with no record at all - the system offered no third option
 * between losing the day and claiming it happened today.
 *
 * It can now say which day's work a round covers. What makes that evidence
 * rather than fabrication is that it is never silent: only somebody who may
 * verify can do it, a reason is required in words, it reaches back seven days
 * and no further, and the day it was really typed stays in created_at for the
 * form to print.
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
    $this->inspector = User::create([
        'name' => 'Inspector', 'email' => 'inspector@example.com', 'password' => bcrypt('x'),
        'role' => 'staff', 'level' => 2, 'department_id' => $this->qa->id,
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $this->service = app(InspectionService::class);
    $this->reason = 'ได้รับตารางกะจากฝ่ายผลิตล่าช้า 1 วัน';
});

afterEach(fn () => Carbon::setTestNow());

function backdatedRound($ctx, string $date, ?string $reason = null, ?User $as = null): InspectionSession
{
    return $ctx->service->startSession(
        $as ?? $ctx->supervisor,
        $ctx->dept->id,
        'area',
        false,
        null,
        false,
        null,
        $date,
        $reason ?? $ctx->reason
    );
}

it('dates the round to the day the work covers', function () {
    $session = backdatedRound($this, '2026-10-05');

    expect($session->inspection_date->toDateString())->toBe('2026-10-05');
});

/**
 * The whole point: both facts are kept, so the form can say which is which.
 */
it('keeps the day it was really typed', function () {
    $session = backdatedRound($this, '2026-10-05');

    expect($session->created_at->toDateString())->toBe('2026-10-06')
        ->and($session->isBackdated())->toBeTrue();
});

it('records who authorised it and why', function () {
    $session = backdatedRound($this, '2026-10-05');

    expect($session->backdated_by)->toBe($this->supervisor->id)
        ->and($session->backdated_reason)->toBe($this->reason);
});

it('refuses somebody who cannot sign a round off', function () {
    expect(fn () => backdatedRound($this, '2026-10-05', as: $this->inspector))
        ->toThrow(ValidationException::class);
});

it('refuses a round with no reason given', function () {
    expect(fn () => backdatedRound($this, '2026-10-05', reason: ''))
        ->toThrow(ValidationException::class);

    expect(fn () => backdatedRound($this, '2026-10-05', reason: 'ลืม'))
        ->toThrow(ValidationException::class);
});

it('refuses a date in the future', function () {
    expect(fn () => backdatedRound($this, '2026-10-07'))
        ->toThrow(ValidationException::class);
});

it('refuses a date further back than the limit', function () {
    expect(fn () => backdatedRound($this, '2026-09-28'))
        ->toThrow(ValidationException::class);

    // Seven days back exactly is still allowed.
    expect(backdatedRound($this, '2026-09-29')->inspection_date->toDateString())
        ->toBe('2026-09-29');
});

/**
 * Asking for today is not backdating. It must not stamp a reason or raise the
 * banner, or every ordinary round started through a form carrying the field
 * would print as an exception.
 */
it('treats a round dated today as an ordinary round', function () {
    $session = backdatedRound($this, '2026-10-06');

    expect($session->isBackdated())->toBeFalse()
        ->and($session->backdated_reason)->toBeNull()
        ->and($session->backdated_by)->toBeNull();
});

it('leaves an ordinary round completely alone', function () {
    $session = $this->service->startSession($this->supervisor, $this->dept->id, 'area');

    expect($session->inspection_date->toDateString())->toBe('2026-10-06')
        ->and($session->isBackdated())->toBeFalse();
});

/**
 * Two places decide "has this target already been covered on this round's
 * date?" by matching a log's inspected_at against the session's
 * inspection_date. A log left on the day it was typed would make a backdated
 * round re-list everyone it had just inspected.
 */
it('puts the logs on the day the round covers, not the day they were typed', function () {
    $session = backdatedRound($this, '2026-10-05');

    $employee = Employee::create([
        'employee_id' => 'E1', 'fullname' => 'พนักงาน หนึ่ง',
        'department_id' => $this->dept->id, 'shift_id' => $this->shift->id,
        'qr_code_hash' => 'h1', 'is_active' => true,
    ]);

    $log = InspectionLog::create([
        'session_id' => $session->id, 'employee_id' => $employee->id,
        'checkpoint_id' => Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person'])->id,
        'result' => 'pass', 'inspected_at' => now(),
    ]);

    expect($log->fresh()->inspected_at->toDateString())->toBe('2026-10-05')
        // When it was really typed is still here.
        ->and($log->fresh()->created_at->toDateString())->toBe('2026-10-06');
});

it('leaves the logs of an ordinary round on the clock', function () {
    $session = $this->service->startSession($this->supervisor, $this->dept->id, 'area');

    $log = InspectionLog::create([
        'session_id' => $session->id,
        'checkpoint_id' => Checkpoint::create(['title' => 'พื้นที่สะอาด', 'is_active' => true, 'type' => 'area'])->id,
        'result' => 'pass', 'inspected_at' => now(),
    ]);

    expect($log->fresh()->inspected_at->toDateString())->toBe('2026-10-06');
});

/**
 * The round that could not be entered on 5 Oct, entered on the 6th, found by
 * the report for the day it covers.
 */
it('appears in the daily report for the day it covers', function () {
    backdatedRound($this, '2026-10-05');

    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->qa->id,
    ]);

    $listed = $this->actingAs($admin)
        ->get(route('reports.daily', ['date' => '2026-10-05']))
        ->assertSuccessful()
        ->viewData('sessions');

    expect($listed)->toHaveCount(1);
});

/**
 * The guard that makes the whole thing honest. A backdated form that does not
 * announce itself is not evidence; it is a claim that the work happened on a
 * day it did not.
 */
it('declares itself on the printed form', function () {
    $session = backdatedRound($this, '2026-10-05');

    $employee = Employee::create([
        'employee_id' => 'E2', 'fullname' => 'พนักงาน สอง',
        'department_id' => $this->dept->id, 'shift_id' => $this->shift->id,
        'qr_code_hash' => 'h2', 'is_active' => true,
    ]);

    InspectionLog::create([
        'session_id' => $session->id, 'employee_id' => $employee->id,
        'checkpoint_id' => Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person'])->id,
        'result' => 'pass', 'inspected_at' => now(),
    ]);

    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin2@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->qa->id,
    ]);

    $captured = [];
    Illuminate\Support\Facades\View::composer('reports.pdf.daily', function ($view) use (&$captured) {
        $captured = $view->getData();
    });

    $this->actingAs($admin)->get(route('reports.export.pdf', [
        'date' => '2026-10-05', 'report_type' => 'all',
    ]))->assertSuccessful();

    $html = view('reports.pdf.daily', $captured)->render();

    expect($html)->toContain('บันทึกย้อนหลัง')
        ->and($html)->toContain('06/10/2026')
        ->and($html)->toContain($this->reason)
        ->and($html)->toContain('QA Supervisor');
});

it('says nothing about backdating on an ordinary form', function () {
    $session = $this->service->startSession($this->supervisor, $this->dept->id, 'area');

    InspectionLog::create([
        'session_id' => $session->id,
        'checkpoint_id' => Checkpoint::create(['title' => 'พื้นที่สะอาด', 'is_active' => true, 'type' => 'area'])->id,
        'result' => 'pass', 'inspected_at' => now(),
    ]);

    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin3@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->qa->id,
    ]);

    $captured = [];
    Illuminate\Support\Facades\View::composer('reports.pdf.daily', function ($view) use (&$captured) {
        $captured = $view->getData();
    });

    $this->actingAs($admin)->get(route('reports.export.pdf', [
        'date' => '2026-10-06', 'report_type' => 'all',
    ]))->assertSuccessful();

    expect(view('reports.pdf.daily', $captured)->render())->not->toContain('บันทึกย้อนหลัง');
});
