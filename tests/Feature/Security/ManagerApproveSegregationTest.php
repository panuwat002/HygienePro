<?php

use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Each shift runs with a single QA inspector, so that person unavoidably inspects and
 * then verifies their own round — blocking verify() would stop inspections entirely.
 * The manager approval step is where two-person control can actually be enforced: a
 * manager does not walk the shift, so refusing to let them approve a round they
 * personally inspected costs nothing operationally.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->qaManager = User::factory()->create([
        'role' => 'manager', 'level' => 5, 'department_id' => $this->dept->id,
    ]);

    $this->otherInspector = User::factory()->create([
        'role' => 'staff', 'level' => 2, 'department_id' => $this->dept->id,
    ]);
});

function logForInspector(Department $dept, User $inspector): InspectionLog
{
    $session = InspectionSession::factory()->create([
        'department_id' => $dept->id,
        'inspector_id' => $inspector->id,
    ]);

    return InspectionLog::factory()->create([
        'session_id' => $session->id,
        'verification_status' => 'verified',
        'verified_at' => now(),
    ]);
}

test('a manager cannot approve a round they inspected themselves', function () {
    $log = logForInspector($this->dept, $this->qaManager);

    $this->actingAs($this->qaManager)
        ->post(route('inspection.approve'), ['ids' => [$log->id]])
        ->assertSessionHas('error');

    expect($log->fresh()->verification_status)->not->toBe('approved');
});

test('a manager can approve a round someone else inspected', function () {
    $log = logForInspector($this->dept, $this->otherInspector);

    $this->actingAs($this->qaManager)
        ->post(route('inspection.approve'), ['ids' => [$log->id]]);

    expect($log->fresh()->verification_status)->toBe('approved');
});

test('a batch mixing own and others work is refused rather than partly applied', function () {
    $ownLog = logForInspector($this->dept, $this->qaManager);
    $otherLog = logForInspector($this->dept, $this->otherInspector);

    $this->actingAs($this->qaManager)
        ->post(route('inspection.approve'), ['ids' => [$ownLog->id, $otherLog->id]])
        ->assertSessionHas('error');

    expect($ownLog->fresh()->verification_status)->not->toBe('approved')
        ->and($otherLog->fresh()->verification_status)->not->toBe('approved');
});
