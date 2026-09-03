<?php

use App\Models\Checkpoint;
use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\RandomAuditEscalationNotification;
use App\Notifications\SessionCarsSummaryNotification;
use App\Services\InspectionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * A round reopened with "ตรวจต่อ" is finished more than once. Announcements fire once, but
 * two things must still get through on the second finish: CARs opened during the continuation,
 * and a random-audit escalation whose threshold is only crossed on that continuation.
 */
beforeEach(function () {
    Notification::fake();
    Mail::fake();
    Http::fake();

    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qaDept = Department::create([
        'dept_name' => 'QA', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);
    $this->inspector = User::create([
        'name' => 'Inspector', 'email' => 'inspector@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->qaDept->id,
    ]);
    $this->manager = User::create([
        'name' => 'Manager', 'email' => 'manager@example.com',
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => $this->dept->id,
    ]);
    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => 'กะเช้า',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);
    $this->checkpoint = Checkpoint::create(['title' => 'CP', 'type' => 'person', 'is_active' => true]);

    Carbon::setTestNow(Carbon::today()->setTime(9, 0));
    $this->service = app(InspectionService::class);
});

afterEach(fn () => Carbon::setTestNow());

function makeWorker($ctx, string $code): Employee
{
    return Employee::create([
        'employee_id' => $code, 'fullname' => 'Worker ' . $code,
        'department_id' => $ctx->dept->id, 'shift_id' => $ctx->shift->id,
        'qr_code_hash' => hash('sha256', $code), 'is_active' => true,
    ]);
}

function logResult($ctx, InspectionSession $session, Employee $emp, string $result): InspectionLog
{
    return InspectionLog::create([
        'session_id' => $session->id, 'employee_id' => $emp->id,
        'checkpoint_id' => $ctx->checkpoint->id, 'result' => $result, 'inspected_at' => now(),
    ]);
}

function openRound($ctx, bool $sampling = false): InspectionSession
{
    return $ctx->service->startSession(
        $ctx->inspector, $ctx->dept->id, 'personnel', false,
        'custom_' . $ctx->shift->id, $sampling, $sampling ? 10 : null
    );
}

/* ---------------- C2: CARs opened during a continuation ---------------- */

it('tells managers about CARs opened during a continuation', function () {
    $session = openRound($this);
    $first = logResult($this, $session, makeWorker($this, 'E1'), 'fail');
    CorrectiveAction::create([
        'inspection_log_id' => $first->id, 'status' => 'open',
        'escalated_by' => $this->inspector->id,
    ]);

    $this->service->finishSession($session);
    Notification::assertSentToTimes($this->manager, SessionCarsSummaryNotification::class, 1);

    // ตรวจต่อ, find one more problem, finish again.
    $again = $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false, 'custom_' . $this->shift->id
    );
    Carbon::setTestNow(now()->addMinutes(30));
    $second = logResult($this, $again, makeWorker($this, 'E2'), 'fail');
    CorrectiveAction::create([
        'inspection_log_id' => $second->id, 'status' => 'open',
        'escalated_by' => $this->inspector->id,
    ]);

    $this->service->finishSession($again);

    Notification::assertSentToTimes($this->manager, SessionCarsSummaryNotification::class, 2);

    // The second summary covers only what the continuation found, not the whole round again.
    Notification::assertSentTo($this->manager, SessionCarsSummaryNotification::class,
        fn ($n) => $n->carsCount === 1 && $n->carsPreview[0]['car_id'] !== $first->id);
});

it('stays quiet when a continuation finds nothing new', function () {
    $session = openRound($this);
    $log = logResult($this, $session, makeWorker($this, 'E1'), 'fail');
    CorrectiveAction::create([
        'inspection_log_id' => $log->id, 'status' => 'open',
        'escalated_by' => $this->inspector->id,
    ]);

    $this->service->finishSession($session);

    $again = $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false, 'custom_' . $this->shift->id
    );
    Carbon::setTestNow(now()->addMinutes(30));
    logResult($this, $again, makeWorker($this, 'E2'), 'pass');
    $this->service->finishSession($again);

    // Still just the one summary from the first finish - no repeat of the old CAR.
    Notification::assertSentToTimes($this->manager, SessionCarsSummaryNotification::class, 1);
});

/* ---------------- C3: escalation is a control, not an announcement ---------------- */

it('escalates a random audit whose fail rate only crosses the line on the continuation', function () {
    $session = openRound($this, sampling: true);

    // 1 of 5 failed = 20%, which is not over the threshold.
    foreach (range(1, 5) as $i) {
        logResult($this, $session, makeWorker($this, 'E' . $i), $i === 1 ? 'fail' : 'pass');
    }

    $this->service->finishSession($session);
    expect($session->refresh()->audit_escalated_at)->toBeNull();
    Notification::assertNotSentTo($this->manager, RandomAuditEscalationNotification::class);

    // ตรวจต่อ: a second failure takes it to 2 of 5 = 40%.
    $again = $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false, 'custom_' . $this->shift->id, true, 10
    );
    Carbon::setTestNow(now()->addMinutes(30));
    logResult($this, $again, Employee::where('employee_id', 'E2')->firstOrFail(), 'fail');

    $this->service->finishSession($again);

    expect($again->refresh()->audit_escalated_at)->not->toBeNull();
    Notification::assertSentToTimes($this->manager, RandomAuditEscalationNotification::class, 1);
});

it('escalates a round at most once', function () {
    $session = openRound($this, sampling: true);

    // 2 of 4 failed = 50% on the very first finish.
    foreach (range(1, 4) as $i) {
        logResult($this, $session, makeWorker($this, 'E' . $i), $i <= 2 ? 'fail' : 'pass');
    }

    $this->service->finishSession($session);
    $escalatedAt = $session->refresh()->audit_escalated_at;
    expect($escalatedAt)->not->toBeNull();

    // Reopen and finish THIS round again. (Going through startSession would pick up the
    // recheck session the escalation just created, which is a different round entirely.)
    Carbon::setTestNow(now()->addMinutes(30));
    $session->update(['status' => 'in_progress']);
    logResult($this, $session, makeWorker($this, 'E5'), 'fail');
    $this->service->finishSession($session);

    Notification::assertSentToTimes($this->manager, RandomAuditEscalationNotification::class, 1);
    expect($session->refresh()->audit_escalated_at->toDateTimeString())->toBe($escalatedAt->toDateTimeString());
});
