<?php

use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\User;

/**
 * The printed daily form has three signature boxes: ผู้บันทึก, ผู้ทวนสอบ
 * (QA Supervisor) and ผู้อนุมัติ. ReportController fills the middle one from
 * inspection_logs.verifier_id.
 *
 * managerApprove() writes the manager's own id into that same column, so the
 * name and signature in the QA Supervisor box become the manager's - the form
 * that is the audit record misstates who carried out the verification, and the
 * supervisor who actually did it is no longer recorded against the work at all.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qaDept = Department::create([
        'dept_name' => 'QA', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->supervisor = User::create([
        'name' => 'Somsak QA Supervisor', 'email' => 'sup@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->qaDept->id,
    ]);
    $this->manager = User::create([
        'name' => 'Ketmanee QA Manager', 'email' => 'mgr@example.com',
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => $this->qaDept->id,
    ]);

    $this->session = InspectionSession::factory()->create([
        'department_id' => $this->dept->id,
        'status' => 'completed',
    ]);

    $this->log = InspectionLog::factory()->create([
        'session_id' => $this->session->id,
        'result' => 'pass',
        'verification_status' => null,
    ]);

    // The supervisor verifies, then the manager approves — the normal route.
    $this->actingAs($this->supervisor)->post(route('inspection.verify'), [
        'ids' => [$this->log->id],
        'status' => 'verified',
    ]);

    $this->actingAs($this->manager)->post(route('inspection.approve'), [
        'ids' => [$this->log->id],
    ]);

    $this->log->refresh();
});

it('still records the supervisor as the person who verified the work', function () {
    expect($this->log->verifier_id)->toBe($this->supervisor->id);
});

it('records the manager separately as the person who approved it', function () {
    expect($this->log->approved_by)->toBe($this->manager->id)
        ->and($this->log->approved_at)->not->toBeNull();
});

/**
 * What the two columns are for: the form's QA Supervisor box must name the
 * supervisor and nobody else.
 */
it('names only the supervisor in the verifier signature box', function () {
    $sessions = InspectionSession::with('logs')->where('id', $this->session->id)->get();

    $verifierIds = $sessions->flatMap(fn ($s) => $s->logs->pluck('verifier_id'))
        ->concat($sessions->pluck('verified_by'))
        ->unique()
        ->filter()
        ->values()
        ->all();

    expect($verifierIds)->toBe([$this->supervisor->id]);
});
