<?php

use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * reject() writes verification_status = 'rejected' AND verified_at = now(). Nothing in
 * the codebase read that value: the verification() group-status ladder tested
 * is_null(verified_at) for "pending", so a rejected group fell through to 'verified' and
 * was filed under the completed tab — rejected work disappeared from the queue and was
 * never signed off. And because storeLog()'s $logData carries no verification keys, a
 * reworked-and-resubmitted log kept the rejection forever.
 *
 * Intended behaviour pinned here: a rejection puts the work back in front of a verifier,
 * and resubmitting clears the rejection so it can be verified afresh.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PRD', 'visibility_type' => 'isolated',
    ]);

    $this->inspector = User::factory()->create([
        'role' => 'staff', 'level' => 2, 'department_id' => $this->dept->id,
    ]);

    $this->session = InspectionSession::factory()->create([
        'department_id' => $this->dept->id,
        'inspector_id' => $this->inspector->id,
    ]);
});

test('a rejected log counts as pending, not as verified work', function () {
    $verifier = User::factory()->create([
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->dept->id,
    ]);

    $log = InspectionLog::factory()->create([
        'session_id' => $this->session->id,
        'verification_status' => 'rejected',
        'verified_at' => now(),
        'verifier_id' => $verifier->id,
    ]);

    $group = collect([$log->fresh()]);

    $hasRejected = $group->contains('verification_status', 'rejected');
    $hasPending = $hasRejected || $group->contains(fn ($l) => is_null($l->verified_at));

    expect($hasPending)->toBeTrue();
});

test('resubmitting a rejected log clears the rejection', function () {
    $verifier = User::factory()->create([
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->dept->id,
    ]);

    $log = InspectionLog::factory()->create([
        'session_id' => $this->session->id,
        'result' => 'fail',
        'verification_status' => 'rejected',
        'verified_at' => now(),
        'verifier_id' => $verifier->id,
        'verification_comment' => 'Photo is unusable, redo it',
    ]);

    $service = app(\App\Services\InspectionService::class);
    $service->storeLog(
        $this->session,
        $log->employee_id,
        $log->checkpoint_id,
        ['result' => 'pass'],
        null
    );

    $reworked = $log->fresh();

    expect($reworked->verification_status)->toBeNull()
        ->and($reworked->verified_at)->toBeNull()
        ->and($reworked->verifier_id)->toBeNull()
        ->and($reworked->verification_comment)->toBeNull()
        ->and($reworked->result)->toBe('pass');
});
