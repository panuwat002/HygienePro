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

    // The digest picks recipients by department + level 3/4.
    $this->supervisor = User::create([
        'name' => 'QA Sup', 'email' => 'qa@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->dept->id,
    ]);

    $this->session = InspectionSession::create([
        'inspector_id' => $this->supervisor->id,
        'department_id' => $this->dept->id,
        'type' => 'personnel',
        'inspection_date' => '2026-09-23',
        'shift' => 'morning',
        'round' => 1,
        'status' => 'completed',
    ]);

    // The digest only picks up sessions with something still awaiting a
    // supervisor, so give each one a log that has not been verified yet.
    $this->checkpoint = Checkpoint::create([
        'title' => 'Hand wash', 'is_active' => true, 'type' => 'person',
    ]);
    $this->employee = Employee::create([
        'employee_id' => 'E001', 'fullname' => 'Worker',
        'department_id' => $this->dept->id, 'qr_code_hash' => 'h1', 'is_active' => true,
    ]);

    pendingLogFor($this, $this->session);
});

/** A log with no verification_status - i.e. still waiting on a supervisor. */
function pendingLogFor($ctx, InspectionSession $session): void
{
    InspectionLog::create([
        'session_id' => $session->id,
        'checkpoint_id' => $ctx->checkpoint->id,
        'employee_id' => $ctx->employee->id,
        'result' => 'pass',
        'inspected_at' => now(),
        'verification_status' => null,
    ]);
}

/** Ages a session's updated_at without Eloquent stamping it back to now. */
function staleSession(InspectionSession $session, int $hours): void
{
    \Illuminate\Support\Facades\DB::table('inspection_sessions')
        ->where('id', $session->id)
        ->update([
            'notified_at' => now(),
            'is_locked' => false,
            'updated_at' => now()->subHours($hours),
        ]);
}

it('marks a session as notified once the digest goes out', function () {
    Notification::fake();

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    // The command writes notified_at through update(), which silently drops any
    // attribute missing from $fillable - leaving the session eligible forever.
    expect($this->session->fresh()->notified_at)->not->toBeNull();
});

it('does not send the same digest twice', function () {
    Notification::fake();

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();
    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    // Once per session, not once per scheduler tick for the rest of time.
    Notification::assertSentToTimes(
        $this->supervisor,
        PendingVerificationDigestNotification::class,
        1
    );
});

it('still picks up a session that has not been notified yet', function () {
    Notification::fake();

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    $newSession = InspectionSession::create([
        'inspector_id' => $this->supervisor->id,
        'department_id' => $this->dept->id,
        'type' => 'machine',
        'inspection_date' => '2026-09-23',
        'shift' => 'afternoon',
        'round' => 1,
        'status' => 'completed',
    ]);
    pendingLogFor($this, $newSession);

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    expect($newSession->fresh()->notified_at)->not->toBeNull();
    Notification::assertSentToTimes(
        $this->supervisor,
        PendingVerificationDigestNotification::class,
        2
    );
});

// EscalatePendingVerifications has the same mass-assignment gap on two more
// columns. It runs hourly, so an un-persisted marker means the same supervisor
// is reminded about the same session every hour until someone verifies it.
it('remembers that it reminded supervisors about a stale session', function () {
    Notification::fake();

    // save() would stamp updated_at to now, so age the row underneath Eloquent.
    staleSession($this->session, 6); // past the 4h reminder threshold

    $this->artisan('inspection:escalate-verifications')->assertSuccessful();

    expect($this->session->fresh()->reminded_at)->not->toBeNull();

    $this->artisan('inspection:escalate-verifications')->assertSuccessful();

    Notification::assertSentToTimes(
        $this->supervisor,
        \App\Notifications\VerificationReminderNotification::class,
        1
    );
});

it('remembers that it escalated a session to managers', function () {
    Notification::fake();

    $manager = User::create([
        'name' => 'QA Mgr', 'email' => 'mgr@example.com',
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => $this->dept->id,
    ]);

    staleSession($this->session, 30); // past the 24h escalation threshold

    $this->artisan('inspection:escalate-verifications')->assertSuccessful();

    expect($this->session->fresh()->escalated_at)->not->toBeNull();

    $this->artisan('inspection:escalate-verifications')->assertSuccessful();

    Notification::assertSentToTimes(
        $manager,
        \App\Notifications\VerificationEscalatedNotification::class,
        1
    );
});
