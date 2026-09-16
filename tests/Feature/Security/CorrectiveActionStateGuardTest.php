<?php

use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * resolve() and assign() never checked $action->status, so a CAR that had already been
 * closed could be pushed back to 'resolved' or 'assigned' indefinitely. resolve() also
 * sets assigned_to = auth()->id(), letting the escalator of a closed ticket reopen it
 * and claim ownership of finished audit work.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PRD', 'visibility_type' => 'isolated',
    ]);

    $this->manager = User::factory()->create([
        'role' => 'manager', 'level' => 5, 'department_id' => $this->dept->id,
    ]);

    $session = InspectionSession::factory()->create(['department_id' => $this->dept->id]);
    $this->log = InspectionLog::factory()->create(['session_id' => $session->id]);
});

test('a closed CAR cannot be resolved again', function () {
    $car = CorrectiveAction::factory()->create([
        'inspection_log_id' => $this->log->id,
        'escalated_by' => $this->manager->id,
        'status' => 'closed',
    ]);

    $this->actingAs($this->manager);

    $this->post(route('corrective.resolve'), [
        'action_id' => $car->id,
        'action_taken' => 'Reopened by attacker',
        'preventive_action' => 'None',
        'proof_image' => UploadedFile::fake()->image('proof.jpg'),
    ]);

    expect($car->refresh()->status)->toBe('closed');
});

test('a closed CAR cannot be reassigned', function () {
    $car = CorrectiveAction::factory()->create([
        'inspection_log_id' => $this->log->id,
        'escalated_by' => $this->manager->id,
        'status' => 'closed',
    ]);

    $assignee = User::factory()->create([
        'role' => 'staff', 'level' => 2, 'department_id' => $this->dept->id,
    ]);

    $this->actingAs($this->manager);

    $this->post(route('corrective.assign'), [
        'action_id' => $car->id,
        'assigned_to' => $assignee->id,
    ]);

    $car->refresh();

    expect($car->status)->toBe('closed')
        ->and($car->assigned_to)->not->toBe($assignee->id);
});

test('an open CAR can still be assigned normally', function () {
    $car = CorrectiveAction::factory()->create([
        'inspection_log_id' => $this->log->id,
        'escalated_by' => $this->manager->id,
        'status' => 'open',
    ]);

    $assignee = User::factory()->create([
        'role' => 'staff', 'level' => 2, 'department_id' => $this->dept->id,
    ]);

    $this->actingAs($this->manager);

    $this->post(route('corrective.assign'), [
        'action_id' => $car->id,
        'assigned_to' => $assignee->id,
    ]);

    $car->refresh();

    expect($car->assigned_to)->toBe($assignee->id)
        ->and($car->status)->toBe('assigned');
});

test('an assigned CAR can still be resolved normally', function () {
    $car = CorrectiveAction::factory()->create([
        'inspection_log_id' => $this->log->id,
        'escalated_by' => $this->manager->id,
        'assigned_to' => $this->manager->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($this->manager);

    $this->post(route('corrective.resolve'), [
        'action_id' => $car->id,
        'action_taken' => 'Cleaned and re-checked',
        'preventive_action' => 'Added to daily checklist',
        'proof_image' => UploadedFile::fake()->image('proof.jpg'),
    ]);

    expect($car->refresh()->status)->toBe('resolved');
});
