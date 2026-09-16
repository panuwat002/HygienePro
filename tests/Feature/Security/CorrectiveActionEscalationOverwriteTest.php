<?php

use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * escalate() used updateOrCreate() keyed on inspection_log_id alone, with no check for
 * an existing CAR or its state. Any level-4 user in the target department could re-POST
 * the escalate endpoint for a log whose CAR was already closed and have it rewritten:
 * status back to open, root_cause replaced, and escalated_by set to themselves.
 *
 * escalated_by is what close() authorises on, so overwriting it is privilege escalation,
 * not just an audit-trail problem.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PRD', 'visibility_type' => 'isolated',
    ]);

    $this->originalEscalator = User::factory()->create([
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->dept->id,
    ]);

    $this->attacker = User::factory()->create([
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->dept->id,
    ]);

    $session = InspectionSession::factory()->create(['department_id' => $this->dept->id]);
    $this->log = InspectionLog::factory()->create([
        'session_id' => $session->id,
        'verification_status' => 'reclean',
    ]);
});

test('escalating a log whose CAR is already closed does not reopen or rewrite it', function () {
    $car = CorrectiveAction::factory()->create([
        'inspection_log_id' => $this->log->id,
        'escalated_by' => $this->originalEscalator->id,
        'status' => 'closed',
        'root_cause' => 'Original documented root cause',
    ]);

    $this->actingAs($this->attacker);

    $this->post(route('corrective.escalate'), [
        'log_id' => $this->log->id,
        'note' => 'Attacker supplied text',
    ]);

    $car->refresh();

    expect($car->status)->toBe('closed')
        ->and($car->escalated_by)->toBe($this->originalEscalator->id)
        ->and($car->root_cause)->toBe('Original documented root cause');
});

test('escalating a log whose CAR is resolved does not rewrite it', function () {
    $car = CorrectiveAction::factory()->create([
        'inspection_log_id' => $this->log->id,
        'escalated_by' => $this->originalEscalator->id,
        'status' => 'resolved',
        'root_cause' => 'Original documented root cause',
    ]);

    $this->actingAs($this->attacker);

    $this->post(route('corrective.escalate'), [
        'log_id' => $this->log->id,
        'note' => 'Attacker supplied text',
    ]);

    $car->refresh();

    expect($car->status)->toBe('resolved')
        ->and($car->escalated_by)->toBe($this->originalEscalator->id);
});

test('re-escalating a still-open CAR keeps the original escalator', function () {
    $car = CorrectiveAction::factory()->create([
        'inspection_log_id' => $this->log->id,
        'escalated_by' => $this->originalEscalator->id,
        'status' => 'open',
        'root_cause' => 'First pass root cause',
    ]);

    $this->actingAs($this->attacker);

    $this->post(route('corrective.escalate'), [
        'log_id' => $this->log->id,
        'note' => 'Updated root cause detail',
    ]);

    $car->refresh();

    // Updating an open CAR is legitimate, but the escalator identity is the
    // authorisation key for close() and must not transfer.
    expect($car->escalated_by)->toBe($this->originalEscalator->id);
});

test('escalating a log with no existing CAR still creates one normally', function () {
    $this->actingAs($this->attacker);

    $this->post(route('corrective.escalate'), [
        'log_id' => $this->log->id,
        'note' => 'A genuine new finding',
    ]);

    $car = CorrectiveAction::where('inspection_log_id', $this->log->id)->first();

    expect($car)->not->toBeNull()
        ->and($car->escalated_by)->toBe($this->attacker->id)
        ->and($car->status)->toBe('open');
});
