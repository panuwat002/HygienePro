<?php

/**
 * Nobody told the managers.
 *
 * Every scheduled notification this system sends is about verification and is
 * addressed to QA supervisors. Approval - the step that closes a round and
 * locks the session - had no email, no LINE and, until this week, no card and
 * no tab. That is how 17,993 logs came to sit at 'verified' against 4,174 ever
 * approved: the work was not refused, it was invisible.
 */

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use App\Console\Commands\SendAwaitingApprovalDigest;
use App\Notifications\AwaitingApprovalDigestNotification;
use App\Support\VerificationGroups;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Carbon::setTestNow('2026-09-24 09:00:00');

    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $this->checkpoint = Checkpoint::create([
        'title' => 'Hand wash', 'is_active' => true, 'type' => 'person',
    ]);

    $this->inspector = User::create([
        'name' => 'Ketmanee', 'email' => 'ketmanee@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->qa->id,
    ]);

    $this->approvalRound = 0;
});

afterEach(function () {
    Carbon::setTestNow();
});

function qaManagerFor($ctx, string $email = 'wallika@example.com'): User
{
    return User::create([
        'name' => 'Wallika', 'email' => $email,
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => $ctx->qa->id,
    ]);
}

/**
 * One personnel round: several logs, all verified by QA, none approved.
 * That is one card on the awaiting_approval tab, whatever its log count.
 */
function personRoundAwaiting($ctx, int $logs = 3, ?string $status = 'verified', ?Carbon $at = null): InspectionSession
{
    $ctx->approvalRound++;
    $at ??= Carbon::now();

    $session = InspectionSession::create([
        'inspector_id' => $ctx->inspector->id, 'department_id' => $ctx->qa->id,
        'type' => 'personnel', 'inspection_date' => $at->toDateString(),
        'shift' => 'custom_' . $ctx->shift->id, 'round' => $ctx->approvalRound,
        'status' => 'completed',
    ]);

    for ($i = 0; $i < $logs; $i++) {
        $employee = Employee::create([
            'employee_id' => 'A' . $ctx->approvalRound . '-' . $i,
            'fullname' => 'Worker ' . $ctx->approvalRound . '-' . $i,
            'department_id' => $ctx->qa->id, 'shift_id' => $ctx->shift->id,
            'qr_code_hash' => 'ah' . $ctx->approvalRound . '-' . $i, 'is_active' => true,
        ]);

        InspectionLog::create([
            'session_id' => $session->id, 'checkpoint_id' => $ctx->checkpoint->id,
            'employee_id' => $employee->id, 'result' => 'pass', 'inspected_at' => $at,
            'verification_status' => $status,
            'verified_at' => $status === null ? null : $at,
        ]);
    }

    return $session;
}

function areaRoundAwaiting($ctx, ?string $status = 'verified', ?Carbon $at = null): InspectionSession
{
    $ctx->approvalRound++;
    $at ??= Carbon::now();

    $location = Location::create(['location_name' => 'ห้องบรรจุ ' . $ctx->approvalRound]);
    $location->checkpoints()->attach($ctx->checkpoint->id);

    $session = InspectionSession::create([
        'inspector_id' => $ctx->inspector->id, 'department_id' => $ctx->qa->id,
        'type' => 'area', 'inspection_date' => $at->toDateString(),
        'shift' => 'custom_' . $ctx->shift->id, 'round' => $ctx->approvalRound,
        'status' => 'completed',
    ]);

    InspectionLog::create([
        'session_id' => $session->id, 'checkpoint_id' => $ctx->checkpoint->id,
        'employee_id' => null, 'location_id' => $location->id,
        'result' => 'pass', 'inspected_at' => $at,
        'verification_status' => $status,
        'verified_at' => $status === null ? null : $at,
    ]);

    return $session;
}

it('tells a QA manager that work is waiting on them', function () {
    Notification::fake();
    $manager = qaManagerFor($this);
    personRoundAwaiting($this);

    $this->artisan('inspection:send-approval-digest')->assertSuccessful();

    Notification::assertSentTo($manager, AwaitingApprovalDigestNotification::class);
});

it('says nothing when the desk is clear', function () {
    Notification::fake();
    qaManagerFor($this);
    personRoundAwaiting($this, status: 'approved');
    personRoundAwaiting($this, status: 'auto_verified');
    personRoundAwaiting($this, status: null); // still QA's problem, not a manager's

    $this->artisan('inspection:send-approval-digest')->assertSuccessful();

    Notification::assertNothingSent();
});

/**
 * The person who verified it cannot approve it - that is the whole point of
 * the second signature. Mailing them changes nothing and trains them to
 * ignore the sender.
 */
it('does not mail someone who has no approve button', function () {
    Notification::fake();
    qaManagerFor($this);
    personRoundAwaiting($this);

    $this->artisan('inspection:send-approval-digest')->assertSuccessful();

    Notification::assertNotSentTo($this->inspector, AwaitingApprovalDigestNotification::class);
});

/**
 * The dashboard card counts rounds, not logs. An email that counts logs would
 * say 3 where the card says 1, and the number a manager acts on has to be the
 * number they were told.
 */
it('counts rounds the way the page counts them', function () {
    Notification::fake();
    $manager = qaManagerFor($this);

    personRoundAwaiting($this, logs: 5);
    personRoundAwaiting($this, logs: 5);
    areaRoundAwaiting($this);

    $this->artisan('inspection:send-approval-digest')->assertSuccessful();

    Notification::assertSentTo($manager, AwaitingApprovalDigestNotification::class,
        function (AwaitingApprovalDigestNotification $n) {
            return $n->total === 3 && $n->personCount === 2 && $n->areaCount === 1;
        });
});

it('leads with the oldest round, because that is the one rotting', function () {
    Notification::fake();
    $manager = qaManagerFor($this);

    personRoundAwaiting($this, at: Carbon::parse('2026-09-23 10:00:00'));
    $oldest = personRoundAwaiting($this, at: Carbon::parse('2026-09-18 10:00:00'));
    personRoundAwaiting($this, at: Carbon::parse('2026-09-24 08:00:00'));

    $this->artisan('inspection:send-approval-digest')->assertSuccessful();

    Notification::assertSentTo($manager, AwaitingApprovalDigestNotification::class,
        function (AwaitingApprovalDigestNotification $n) use ($oldest) {
            return $n->rows[0]['session_id'] === $oldest->id
                && $n->rows[0]['waiting_days'] === 6;
        });
});

it('names the shift rather than its database key', function () {
    Notification::fake();
    $manager = qaManagerFor($this);
    personRoundAwaiting($this);

    $this->artisan('inspection:send-approval-digest')->assertSuccessful();

    Notification::assertSentTo($manager, AwaitingApprovalDigestNotification::class,
        function (AwaitingApprovalDigestNotification $n) {
            return $n->rows[0]['shift'] === 'กะเช้า 08.00-17.00';
        });
});

it('points the in-app notification at the tab that holds the work', function () {
    $manager = qaManagerFor($this);
    personRoundAwaiting($this);

    $this->artisan('inspection:send-approval-digest')->assertSuccessful();

    $notification = $manager->fresh()->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['url'])->toContain('tab=awaiting_approval')
        ->and($notification->data['count'])->toBe(1);
});

it('mails it and rings the bell by default', function () {
    Notification::fake();
    $manager = qaManagerFor($this);
    personRoundAwaiting($this);

    $this->artisan('inspection:send-approval-digest')->assertSuccessful();

    Notification::assertSentTo($manager, AwaitingApprovalDigestNotification::class,
        fn ($n, $channels) => in_array('mail', $channels) && in_array('database', $channels));
});

/**
 * Opting out of the email is not opting out of the work. The bell keeps
 * ringing; only the copy that lands in an inbox is dropped.
 */
it('keeps the bell for a manager who turned the email off', function () {
    Notification::fake();
    $manager = qaManagerFor($this);
    $manager->forceFill(['notification_preferences' => ['email_awaiting_approval' => false]])->save();
    personRoundAwaiting($this);

    $this->artisan('inspection:send-approval-digest')->assertSuccessful();

    Notification::assertSentTo($manager->fresh(), AwaitingApprovalDigestNotification::class,
        fn ($n, $channels) => $channels === ['database']);
});

/**
 * Notification::fake() never renders the mail body, so a broken template
 * would sail through every test above. This one builds the real MailMessage.
 */
it('renders an email a manager can act on', function () {
    $manager = qaManagerFor($this);
    areaRoundAwaiting($this, at: Carbon::parse('2026-09-20 10:00:00'));
    personRoundAwaiting($this);

    $command = app(SendAwaitingApprovalDigest::class);
    $buildRows = new ReflectionMethod($command, 'rows');
    $buildRows->setAccessible(true);
    $rows = $buildRows->invoke($command, app(VerificationGroups::class)->awaitingApproval());

    $notification = new AwaitingApprovalDigestNotification(
        total: 2, personCount: 1, areaCount: 1, rows: $rows,
    );

    $html = (string) $notification->toMail($manager)->render();

    expect($html)
        ->toContain('ห้องบรรจุ')                 // the area round is named
        ->toContain('กะเช้า 08.00-17.00')        // not custom_N
        ->toContain('4 วัน')                     // 20 Sep to 24 Sep
        ->toContain('tab=awaiting_approval');    // the button goes somewhere useful
});

it('is scheduled once a day rather than every hour', function () {
    $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'inspection:send-approval-digest'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 9 * * *');
});
