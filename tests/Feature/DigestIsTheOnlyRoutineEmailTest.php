<?php

use App\Models\Department;
use App\Models\InspectionSession;
use App\Models\User;
use App\Services\InspectionService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);

    $this->supervisor = User::create([
        'name' => 'Ketmanee', 'email' => 'ketmanee@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->qa->id,
    ]);

    $this->session = InspectionSession::create([
        'inspector_id' => $this->supervisor->id,
        'department_id' => $this->qa->id,
        'type' => 'personnel',
        'inspection_date' => '2026-09-23',
        'shift' => 'morning',
        'round' => 1,
        'status' => 'completed',
    ]);

    $this->service = app(InspectionService::class);
});

function callProtected(object $object, string $method, ...$args)
{
    $ref = new ReflectionMethod($object, $method);
    $ref->setAccessible(true);

    return $ref->invoke($object, ...$args);
}

// A round used to mail its supervisors three times - started, finished, and
// again when verified - on top of the digest that already lists the same work.
// The routine two now travel in the hourly digest only.
it('sends no email when a round starts', function () {
    Mail::fake();

    callProtected($this->service, 'notifySupervisorsStarted', $this->session);

    Mail::assertNothingSent();
});

it('still raises the in-app notification when a round starts', function () {
    Notification::fake();

    callProtected($this->service, 'notifySupervisorsStarted', $this->session);

    // The bell keeps working; only the mail copy is gone.
    Notification::assertSentTo(
        $this->supervisor,
        \App\Notifications\InspectionStartedNotification::class
    );
});

it('sends no email when a round finishes', function () {
    Mail::fake();

    callProtected($this->service, 'notifySupervisorsFinished', $this->session);

    Mail::assertNothingSent();
});

it('runs the digest hourly rather than twice an hour', function () {
    $digest = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'inspection:send-smart-digest'));

    expect($digest)->not->toBeNull()
        ->and($digest->expression)->toBe('0 * * * *');
});
