<?php

use App\Models\Department;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use App\Services\InspectionService;
use Illuminate\Support\Carbon;

/**
 * Finishing a round used to force the next start into a brand-new round, which re-listed
 * every target the inspector had already covered. "ตรวจต่อ" reopens the same round instead;
 * "เริ่มรอบใหม่" keeps the old behaviour.
 */
beforeEach(function () {
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
    Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => 'กะเช้า',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);
    Carbon::setTestNow(Carbon::today()->setTime(9, 0));
    $this->service = app(InspectionService::class);
});

afterEach(fn () => Carbon::setTestNow());

function finishedAreaRound($ctx): InspectionSession
{
    $session = $ctx->service->startSession($ctx->inspector, $ctx->dept->id, 'area');
    $ctx->service->finishSession($session);

    return $session->refresh();
}

it('reopens the same round when the inspector chooses ตรวจต่อ', function () {
    $first = finishedAreaRound($this);
    expect($first->status)->toBe('completed');

    $again = $this->service->startSession($this->inspector, $this->dept->id, 'area');

    expect($again->id)->toBe($first->id)
        ->and($again->round)->toBe(1)
        ->and($again->status)->toBe('in_progress');
});

it('starts a fresh round when the inspector chooses เริ่มรอบใหม่', function () {
    $first = finishedAreaRound($this);

    $second = $this->service->startSession($this->inspector, $this->dept->id, 'area', true);

    expect($second->id)->not->toBe($first->id)
        ->and($second->round)->toBe(2)
        ->and($second->status)->toBe('in_progress');
});

it('forces a new round once the round has been verified', function () {
    $first = finishedAreaRound($this);
    $first->update(['verified_at' => now(), 'verified_by' => $this->inspector->id]);

    $second = $this->service->startSession($this->inspector, $this->dept->id, 'area');

    expect($second->id)->not->toBe($first->id)
        ->and($second->round)->toBe(2);
});

it('forces a new round once the round has been locked', function () {
    $first = finishedAreaRound($this);
    $first->update(['is_locked' => true]);

    $second = $this->service->startSession($this->inspector, $this->dept->id, 'area');

    expect($second->id)->not->toBe($first->id)
        ->and($second->round)->toBe(2);
});

it('keeps logs from the earlier part of the round visible after ตรวจต่อ', function () {
    $first = finishedAreaRound($this);
    $checkpoint = \App\Models\Checkpoint::create([
        'title' => 'พื้นสะอาด', 'type' => 'area', 'is_active' => true,
    ]);
    \App\Models\InspectionLog::create([
        'session_id' => $first->id, 'checkpoint_id' => $checkpoint->id,
        'result' => 'pass', 'inspected_at' => now(),
    ]);

    $again = $this->service->startSession($this->inspector, $this->dept->id, 'area');

    // The bulk checklist scopes "already inspected" to session_id, so reopening the same
    // session is what makes the earlier work show as done.
    expect($again->logs()->count())->toBe(1);
});

it('announces a finished round once, not again after ตรวจต่อ', function () {
    $first = finishedAreaRound($this);
    $notifiedAt = $first->finished_notified_at;

    expect($notifiedAt)->not->toBeNull();

    $again = $this->service->startSession($this->inspector, $this->dept->id, 'area');
    $this->service->finishSession($again);

    expect($again->refresh()->finished_notified_at->toDateTimeString())
        ->toBe($notifiedAt->toDateTimeString());
});

it('carries the button intent from the dashboard form through to the round', function () {
    // 'machine' is the bulk route the area/machine dashboard posts to.
    $first = $this->service->startSession($this->inspector, $this->dept->id, 'machine');
    $this->service->finishSession($first);

    // "ตรวจต่อ" -> same round, reopened.
    $this->actingAs($this->inspector)->post(route('inspection.start', 'machine'), [
        'department_id' => $this->dept->id,
        'force_new_round' => '0',
    ]);

    expect(InspectionSession::where('type', 'machine')->count())->toBe(1)
        ->and($first->refresh()->status)->toBe('in_progress')
        ->and($first->round)->toBe(1);

    $this->service->finishSession($first);

    // "เริ่มรอบใหม่" -> a second round alongside it.
    $this->actingAs($this->inspector)->post(route('inspection.start', 'machine'), [
        'department_id' => $this->dept->id,
        'force_new_round' => '1',
    ]);

    expect(InspectionSession::where('type', 'machine')->count())->toBe(2)
        ->and(InspectionSession::where('type', 'machine')->latest('id')->first()->round)->toBe(2);
});

it('reports whether a finished round can still be continued', function () {
    $first = finishedAreaRound($this);

    expect($this->service->canReopen($first))->toBeTrue();

    $first->update(['verified_at' => now()]);
    expect($this->service->canReopen($first->refresh()))->toBeFalse();
});

it('does not offer to continue a round that is still open', function () {
    $open = $this->service->startSession($this->inspector, $this->dept->id, 'area');

    // canReopen is only about finished rounds; an open one is simply resumed.
    expect($this->service->canReopen($open))->toBeFalse();
});
