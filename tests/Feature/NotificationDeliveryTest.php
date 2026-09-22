<?php

use App\Models\Department;
use App\Models\User;
use App\Models\InspectionSession;
use App\Services\InspectionService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Http::fake(); // never hit the external AI service or LINE during tests
});

/**
 * Returns every command string registered on the scheduler, e.g.
 * "'php' 'artisan' car:check-overdue".
 */
function scheduledCommands(): array
{
    return collect(app(Schedule::class)->events())
        ->map(fn ($event) => (string) $event->command)
        ->all();
}

it('schedules the overdue CAR check', function () {
    // car:check-overdue exists as a command but was never registered on the
    // scheduler, so the overdue-CAR email/LINE report could never fire on its own.
    $matching = array_filter(
        scheduledCommands(),
        fn ($command) => str_contains($command, 'car:check-overdue')
    );

    expect($matching)->not->toBeEmpty(
        'car:check-overdue is not registered in routes/console.php'
    );
});

it('does not claim emails were sent when nobody is a recipient', function () {
    // A QA supervisor who has opted out of finished-pass mail.
    $qa = Department::create([
        'dept_name' => 'QA', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);
    $supervisor = User::create([
        'name' => 'QA Sup', 'email' => 'qa@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $qa->id,
    ]);
    $supervisor->forceFill([
        'email_verified_at' => now(),
        'notification_preferences' => ['email_session_finished_pass' => false],
    ])->save();

    $session = InspectionSession::create([
        'inspector_id' => $supervisor->id,
        'department_id' => $qa->id,
        'type' => 'personnel',
        'inspection_date' => '2026-09-22',
        'shift' => 'morning',
        'round' => 1,
        'status' => 'completed',
    ]);

    Mail::fake();
    Log::spy();

    $service = app(InspectionService::class);
    $notify = new ReflectionMethod($service, 'notifySupervisorsFinished');
    $notify->setAccessible(true);
    $notify->invoke($service, $session);

    Mail::assertNothingSent();

    // The old log line sat outside the `if (count($emails) > 0)` guard, so it
    // reported success even when no mail was handed to the transport at all.
    Log::shouldNotHaveReceived('info', [
        \Mockery::pattern('/finished emails sent to QA Supervisors/'),
    ]);
});

it('logs the recipient count when finished emails do go out', function () {
    $qa = Department::create([
        'dept_name' => 'QA', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);
    $supervisor = User::create([
        'name' => 'QA Sup', 'email' => 'qa@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $qa->id,
    ]);
    $supervisor->forceFill([
        'email_verified_at' => now(),
        'notification_preferences' => ['email_session_finished_pass' => true],
    ])->save();

    $session = InspectionSession::create([
        'inspector_id' => $supervisor->id,
        'department_id' => $qa->id,
        'type' => 'personnel',
        'inspection_date' => '2026-09-22',
        'shift' => 'morning',
        'round' => 1,
        'status' => 'completed',
    ]);

    Mail::fake();

    $service = app(InspectionService::class);
    $notify = new ReflectionMethod($service, 'notifySupervisorsFinished');
    $notify->setAccessible(true);
    $notify->invoke($service, $session);

    Mail::assertSent(\App\Mail\InspectionSessionFinished::class);
});

it('defaults every email event to on for UAT', function (string $event) {
    $dept = Department::create([
        'dept_name' => 'QA', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);
    $user = User::create([
        'name' => 'Fresh User', 'email' => 'fresh@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $dept->id,
    ]);

    // A user who has never touched their preferences should receive every event,
    // so UAT can exercise all of them without editing each account first.
    expect($user->wantsEmailFor($event))->toBeTrue();
})->with([
    'email_session_started',
    'email_session_finished_pass',
    'email_session_finished_fail',
    'email_order_reclean',
    'email_session_verified',
    'email_car_new',
    'email_car_resolved',
    'email_car_closed',
    'email_car_overdue',
]);

it('still honours a user who switched an event off', function () {
    $dept = Department::create([
        'dept_name' => 'QA', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);
    $user = User::create([
        'name' => 'Opted Out', 'email' => 'opted@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $dept->id,
    ]);
    $user->forceFill([
        'notification_preferences' => ['email_session_started' => false],
    ])->save();

    expect($user->wantsEmailFor('email_session_started'))->toBeFalse()
        ->and($user->wantsEmailFor('email_session_verified'))->toBeTrue();
});
