<?php

/**
 * A corrective action that closed itself.
 *
 * On UAT one CAR read: cause "ขอบมุมโต๊ะมีคราบสกปรก", nobody assigned, no
 * resolved_at, "ไม่ได้บันทึกวิธีแก้ไข" - and status Closed. Nobody had wiped
 * the table and nobody claimed they had.
 *
 * The workflow did it. verify() promoted every CAR on the round to 'verified',
 * including ones nobody had touched, and managerApprove() then closed
 * everything at 'open', 'assigned', 'resolved' or 'verified'. So a finding
 * could travel open → verified → closed without a single person acting on it.
 *
 * Approving an inspection and closing a corrective action are different
 * statements: one says the inspection result is accepted, the other says the
 * problem is fixed and somebody checked. Conflating them destroys the evidence
 * an auditor asks for first - what did you do about it?
 */

use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\User;

beforeEach(function () {
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->verifier = User::factory()->create([
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->qa->id,
    ]);

    $this->approver = User::factory()->create([
        'role' => 'manager', 'level' => 5, 'department_id' => $this->qa->id,
    ]);

    // The approver must not be the inspector - managerApprove refuses to let
    // anyone sign off a round they walked themselves.
    $this->session = InspectionSession::factory()->create([
        'department_id' => $this->qa->id,
        'inspector_id' => $this->verifier->id,
    ]);

    $this->log = InspectionLog::factory()->create([
        'session_id' => $this->session->id,
        'result' => 'fail',
    ]);
});

function carAt($ctx, string $status, ?string $actionTaken = null): CorrectiveAction
{
    return CorrectiveAction::factory()->create([
        'inspection_log_id' => $ctx->log->id,
        'escalated_by' => $ctx->verifier->id,
        'status' => $status,
        'action_taken' => $actionTaken,
        'resolved_at' => $actionTaken ? now() : null,
    ]);
}

function verifyTheRound($ctx)
{
    return $ctx->actingAs($ctx->verifier)->post(route('inspection.verify'), [
        'ids' => [$ctx->log->id],
        'status' => 'verified',
    ]);
}

function approveTheRound($ctx)
{
    return $ctx->actingAs($ctx->approver)->post(route('inspection.approve'), [
        'ids' => [$ctx->log->id],
    ]);
}

it('does not mark work nobody has done as verified', function () {
    $car = carAt($this, 'open');

    verifyTheRound($this);

    expect($car->refresh()->status)->toBe('open');
});

it('does not mark assigned-but-unfinished work as verified either', function () {
    $car = carAt($this, 'assigned');

    verifyTheRound($this);

    expect($car->refresh()->status)->toBe('assigned');
});

it('still verifies work somebody actually resolved', function () {
    $car = carAt($this, 'resolved', 'เช็ดขอบมุมโต๊ะและล้างด้วยน้ำยา');

    verifyTheRound($this);

    expect($car->refresh()->status)->toBe('verified');
});

it('does not close a corrective action nobody has worked on', function () {
    $car = carAt($this, 'open');

    approveTheRound($this);

    expect($car->refresh()->status)->toBe('open')
        ->and($car->closed_at)->toBeNull();
});

it('leaves an assigned action open for the person it was assigned to', function () {
    $car = carAt($this, 'assigned');

    approveTheRound($this);

    expect($car->refresh()->status)->toBe('assigned');
});

it('still closes an action that was resolved and recorded', function () {
    $car = carAt($this, 'resolved', 'เช็ดขอบมุมโต๊ะและล้างด้วยน้ำยา');

    approveTheRound($this);

    expect($car->refresh()->status)->toBe('closed')
        ->and($car->closed_at)->not->toBeNull();
});

it('still closes an action QA had already verified', function () {
    $car = carAt($this, 'verified', 'เช็ดขอบมุมโต๊ะและล้างด้วยน้ำยา');

    approveTheRound($this);

    expect($car->refresh()->status)->toBe('closed');
});

/**
 * Approving the round still works - it just stops making a claim about work it
 * has no evidence for. The finding stays on the Issues page where somebody can
 * still act on it.
 */
it('approves the round regardless, and leaves the finding standing', function () {
    $car = carAt($this, 'open');

    approveTheRound($this);

    expect($this->log->refresh()->verification_status)->toBe('approved')
        ->and($car->refresh()->status)->toBe('open');
});

/**
 * The last line of defence. Every route above is fixed, but a future one
 * should not be able to reopen this hole quietly.
 */
it('refuses to close an action with no record of what was done', function () {
    $car = carAt($this, 'resolved');

    expect(fn () => $car->update(['status' => 'closed', 'closed_at' => now()]))
        ->toThrow(LogicException::class);
});

it('refuses to call an action verified with no record of what was done', function () {
    $car = carAt($this, 'assigned');

    expect(fn () => $car->update(['status' => 'verified']))
        ->toThrow(LogicException::class);
});

it('allows the close once the work is written down', function () {
    $car = carAt($this, 'resolved', 'เช็ดขอบมุมโต๊ะและล้างด้วยน้ำยา');

    $car->update(['status' => 'closed', 'closed_at' => now()]);

    expect($car->refresh()->status)->toBe('closed');
});

it('tells someone closing by hand what is missing, instead of failing', function () {
    $car = carAt($this, 'resolved');

    $this->actingAs($this->verifier)
        ->post(route('corrective.close'), ['action_id' => $car->id])
        ->assertSessionHas('error');

    expect($car->refresh()->status)->toBe('resolved');
});
