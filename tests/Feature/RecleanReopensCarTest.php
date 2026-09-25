<?php

use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\User;

/**
 * escalate() refuses to touch a CAR that has already been resolved or closed,
 * because updateOrCreate() on it rewrites the recorded root cause and the
 * escalator - a finished audit record being overwritten.
 *
 * verify()'s re-clean path does exactly the same updateOrCreate() and has no
 * such guard.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qaDept = Department::create([
        'dept_name' => 'QA', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->supervisor = User::create([
        'name' => 'QA Supervisor', 'email' => 'sup@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->qaDept->id,
    ]);
    $this->firstEscalator = User::create([
        'name' => 'First Escalator', 'email' => 'first@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->dept->id,
    ]);

    $this->session = InspectionSession::factory()->create([
        'department_id' => $this->dept->id,
        'status' => 'completed',
    ]);

    // A failure that went all the way round the loop once already: escalated,
    // fixed, checked and closed with a record of what was done.
    $this->log = InspectionLog::factory()->create([
        'session_id' => $this->session->id,
        'result' => 'fail',
        'verification_status' => 'approved',
    ]);

    $this->car = CorrectiveAction::factory()->create([
        'inspection_log_id' => $this->log->id,
        'escalated_by' => $this->firstEscalator->id,
        'status' => 'closed',
        'root_cause' => 'ท่อน้ำรั่วจากข้อต่อที่หลวม',
        'action_taken' => 'ขันข้อต่อใหม่และเปลี่ยนซีล',
        'closed_at' => now()->subWeek(),
    ]);
});

function orderReclean($test): \Illuminate\Testing\TestResponse
{
    return $test->actingAs($test->supervisor)->post(route('inspection.verify'), [
        'ids' => [$test->log->id],
        'status' => 'reclean',
        'comment' => 'พบปัญหาเดิมอีกครั้ง',
    ]);
}

it('does not erase the root cause recorded on a closed finding', function () {
    orderReclean($this);

    expect($this->car->refresh()->root_cause)->toBe('ท่อน้ำรั่วจากข้อต่อที่หลวม');
});

it('does not reassign a closed finding to whoever ordered the re-clean', function () {
    orderReclean($this);

    expect($this->car->refresh()->escalated_by)->toBe($this->firstEscalator->id);
});

/**
 * The worst of the three. A finding reopened for a NEW occurrence keeps the
 * OLD action_taken, so the guard that stops a CAR closing with no record of
 * what was done is satisfied by evidence from a different incident - it can
 * go straight back to closed with nobody touching it.
 */
it('does not carry the previous fix over to a finding reopened for a new failure', function () {
    orderReclean($this);

    $car = $this->car->refresh();

    // Either the old CAR is left closed and a fresh record is opened, or it is
    // reopened with its evidence cleared. What must not happen is a CAR that is
    // open again while still holding the last cycle's proof.
    expect($car->status === 'closed' || blank($car->action_taken))->toBeTrue();
});
