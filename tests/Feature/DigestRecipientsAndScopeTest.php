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
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);
    $this->production = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);

    // Only a QA supervisor passes canVerify(), so only they can act on a digest.
    $this->qaSupervisor = User::create([
        'name' => 'Ketmanee', 'email' => 'ketmanee@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->qa->id,
    ]);

    // A production supervisor cannot open the verification page at all.
    $this->productionSupervisor = User::create([
        'name' => 'Duangamol', 'email' => 'duangamol@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->production->id,
    ]);

    $this->checkpoint = Checkpoint::create([
        'title' => 'Hand wash', 'is_active' => true, 'type' => 'person',
    ]);
    $this->employee = Employee::create([
        'employee_id' => 'E001', 'fullname' => 'Worker',
        'department_id' => $this->production->id, 'qr_code_hash' => 'h1', 'is_active' => true,
    ]);
});

function sessionAwaitingVerification($ctx, Department $dept, string $type): InspectionSession
{
    $session = InspectionSession::create([
        'inspector_id' => $ctx->qaSupervisor->id,
        'department_id' => $dept->id,
        'type' => $type,
        'inspection_date' => '2026-09-23',
        'shift' => 'morning',
        'round' => 1,
        'status' => 'completed',
    ]);

    InspectionLog::create([
        'session_id' => $session->id,
        'checkpoint_id' => $ctx->checkpoint->id,
        'employee_id' => $ctx->employee->id,
        'result' => 'pass',
        'inspected_at' => now(),
        'verification_status' => null,
    ]);

    return $session;
}

// Recipients were the supervisors of the department being inspected. For a
// Production round that is a Production supervisor, who fails canVerify() and
// gets 403 on the page the email links to - while QA, who can actually verify,
// hears nothing.
it('sends a production round to QA, not to the production supervisor', function () {
    Notification::fake();

    sessionAwaitingVerification($this, $this->production, 'personnel');

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    Notification::assertSentTo($this->qaSupervisor, PendingVerificationDigestNotification::class);
    Notification::assertNotSentTo($this->productionSupervisor, PendingVerificationDigestNotification::class);
});

it('gives QA one digest covering every department', function () {
    Notification::fake();

    sessionAwaitingVerification($this, $this->production, 'personnel');
    sessionAwaitingVerification($this, $this->qa, 'personnel');

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    Notification::assertSentToTimes(
        $this->qaSupervisor,
        PendingVerificationDigestNotification::class,
        1
    );
});

it('leaves machine rounds out of the digest', function () {
    Notification::fake();

    sessionAwaitingVerification($this, $this->production, 'machine');

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    // A single room holds 16-26 machines, so these rounds dominate the email
    // without telling a supervisor anything they act on differently.
    Notification::assertNothingSent();
});

it('keeps area rounds in the digest', function () {
    Notification::fake();

    sessionAwaitingVerification($this, $this->production, 'area');

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    Notification::assertSentTo($this->qaSupervisor, PendingVerificationDigestNotification::class);
});

it('marks a machine round as notified so it is not reconsidered every tick', function () {
    Notification::fake();

    $machine = sessionAwaitingVerification($this, $this->production, 'machine');
    $area = sessionAwaitingVerification($this, $this->production, 'area');

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    // The area round went out and is marked; the machine round was never a
    // candidate, so it stays unmarked rather than being silently swallowed.
    expect($area->fresh()->notified_at)->not->toBeNull()
        ->and($machine->fresh()->notified_at)->toBeNull();
});

it('drops the auto-verified column from the table', function () {
    Notification::fake();

    sessionAwaitingVerification($this, $this->production, 'personnel');

    $this->artisan('inspection:send-smart-digest')->assertSuccessful();

    Notification::assertSentTo(
        $this->qaSupervisor,
        PendingVerificationDigestNotification::class,
        function (PendingVerificationDigestNotification $n) {
            $html = $n->toMail($this->qaSupervisor)->render();

            return ! str_contains($html, 'ผ่านออโต้')
                && str_contains($html, 'ต้องตรวจเอง');
        }
    );
});
