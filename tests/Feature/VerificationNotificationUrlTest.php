<?php

use App\Models\Department;
use App\Models\InspectionSession;
use App\Models\User;
use App\Notifications\PendingVerificationDigestNotification;
use App\Notifications\VerificationEscalatedNotification;
use App\Notifications\VerificationReminderNotification;

beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'QA', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);

    $this->user = User::create([
        'name' => 'QA Sup', 'email' => 'qa@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->dept->id,
    ]);
});

function makeSession($ctx, string $type): InspectionSession
{
    return InspectionSession::create([
        'inspector_id' => $ctx->user->id,
        'department_id' => $ctx->dept->id,
        'type' => $type,
        'inspection_date' => '2026-09-23',
        'shift' => 'morning',
        'round' => 1,
        'status' => 'completed',
    ]);
}

// These three all pointed at route('inspection.verification.dashboard'), which
// has never existed - the route is named 'inspection.verification'. Every
// delivery threw RouteNotFoundException, and once the scheduler started running
// the failures repeated hourly: 1584 lines of them in laravel.log.
it('builds a reachable url for the pending verification digest', function () {
    $session = makeSession($this, 'personnel');

    $payload = (new PendingVerificationDigestNotification(collect([$session])))
        ->toArray($this->user);

    expect($payload['url'])->toContain('/verification');
});

it('builds a reachable url for the verification reminder', function (string $type) {
    $session = makeSession($this, $type);

    $payload = (new VerificationReminderNotification($session))->toArray($this->user);

    expect($payload['url'])->toContain('/verification');
})->with(['personnel', 'machine', 'area']);

it('builds a reachable url for the verification escalation', function (string $type) {
    $session = makeSession($this, $type);

    $payload = (new VerificationEscalatedNotification($session))->toArray($this->user);

    expect($payload['url'])->toContain('/verification');
})->with(['personnel', 'machine', 'area']);

// filter_type only understands person|area|machine; a session's own type says
// 'personnel'. Passing it through unmapped silently lands the supervisor on the
// person tab even when the round was an area round.
it('maps the session type onto a filter_type the page accepts', function (string $type, string $expected) {
    $session = makeSession($this, $type);

    $payload = (new VerificationReminderNotification($session))->toArray($this->user);

    expect($payload['url'])->toContain("filter_type={$expected}");
})->with([
    ['personnel', 'person'],
    ['machine', 'machine'],
    ['area', 'area'],
]);

it('renders the digest email without a routing error', function () {
    $session = makeSession($this, 'personnel');

    $mail = (new PendingVerificationDigestNotification(collect([$session])))
        ->toMail($this->user);

    expect($mail->render())->toContain('/verification');
});
