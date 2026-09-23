<?php

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\User;
use App\Notifications\PendingVerificationDigestNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'QA', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);

    $this->supervisor = User::create([
        'name' => 'QA Sup', 'email' => 'qa@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->dept->id,
    ]);

    $this->checkpoint = Checkpoint::create([
        'title' => 'Hand wash', 'is_active' => true, 'type' => 'person',
    ]);

    $this->employee = Employee::create([
        'employee_id' => 'E001', 'fullname' => 'Worker',
        'department_id' => $this->dept->id, 'qr_code_hash' => 'h1', 'is_active' => true,
    ]);
});

function completedSession($ctx, string $shift = 'morning'): InspectionSession
{
    return InspectionSession::create([
        'inspector_id' => $ctx->supervisor->id,
        'department_id' => $ctx->dept->id,
        'type' => 'personnel',
        'inspection_date' => '2026-09-23',
        'shift' => $shift,
        'round' => 1,
        'status' => 'completed',
    ]);
}

function addLog($ctx, InspectionSession $session, ?string $verificationStatus): InspectionLog
{
    return InspectionLog::create([
        'session_id' => $session->id,
        'checkpoint_id' => $ctx->checkpoint->id,
        'employee_id' => $ctx->employee->id,
        'result' => 'pass',
        'inspected_at' => now(),
        'verification_status' => $verificationStatus,
    ]);
}

// The digest selected every completed session with notified_at still null,
// without asking whether anything was actually awaiting verification. Rows
// showing 0 pending and 0 auto-verified made up most of a 67-row email.
it('leaves out a session that has nothing awaiting verification', function () {
    Notification::fake();

    $done = completedSession($this);
    addLog($this, $done, 'verified');

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    Notification::assertNothingSent();
});

it('leaves out a session that has no logs at all', function () {
    Notification::fake();

    completedSession($this);

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    Notification::assertNothingSent();
});

it('includes a session with at least one log still pending', function () {
    Notification::fake();

    $pending = completedSession($this);
    addLog($this, $pending, null);

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    Notification::assertSentTo($this->supervisor, PendingVerificationDigestNotification::class);
});

it('sends only the actionable sessions when both kinds exist', function () {
    Notification::fake();

    $done = completedSession($this, 'morning');
    addLog($this, $done, 'verified');

    $pending = completedSession($this, 'afternoon');
    addLog($this, $pending, null);

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    Notification::assertSentTo(
        $this->supervisor,
        PendingVerificationDigestNotification::class,
        function (PendingVerificationDigestNotification $n) use ($pending) {
            return $n->sessions->pluck('id')->all() === [$pending->id];
        }
    );
});

it('counts pending logs without a query per row', function () {
    Notification::fake();

    $session = completedSession($this);
    addLog($this, $session, null);
    addLog($this, $session, null);
    addLog($this, $session, 'auto_verified');

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    Notification::assertSentTo(
        $this->supervisor,
        PendingVerificationDigestNotification::class,
        function (PendingVerificationDigestNotification $n) {
            $row = $n->sessions->first();

            // Counted by the query, so rendering the table does not fire two
            // more queries for every row in it.
            return $row->pending_logs_count === 2
                && $row->auto_verified_logs_count === 1;
        }
    );
});

it('caps the table and says how many rows it left out', function () {
    Notification::fake();

    foreach (range(1, 14) as $i) {
        $s = completedSession($this, "shift-{$i}");
        addLog($this, $s, null);
    }

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    Notification::assertSentTo(
        $this->supervisor,
        PendingVerificationDigestNotification::class,
        function (PendingVerificationDigestNotification $n) {
            $html = $n->toMail($this->supervisor)->render();

            // 14 pending sessions, 10 rows shown, 4 named as the remainder.
            return substr_count($html, '<tr style="border-bottom') === 10
                && str_contains($html, '4');
        }
    );
});
