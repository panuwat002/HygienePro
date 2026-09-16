<?php

use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

/**
 * scopedDepartmentId() returns int 0 for a departmentless user. The export endpoints
 * guarded the department filter with a plain truthiness check — `if ($departmentId)` —
 * and 0 is falsy, so the filter was skipped entirely and the export dumped every
 * department. The on-screen moneyReport() handled the same user correctly, which is how
 * you can tell this was a bug rather than intent.
 *
 * DomPDF compresses its output streams, so asserting on the response bytes proves
 * nothing (a naive version of this test passed against the vulnerable code because the
 * plaintext is simply not greppable). Instead we capture the data handed to the PDF
 * view and assert on the actual log collection.
 */
beforeEach(function () {
    $this->deptA = Department::create([
        'dept_name' => 'Alpha', 'dept_code' => 'ALP', 'visibility_type' => 'isolated',
    ]);

    $inspector = User::factory()->create([
        'role' => 'staff', 'level' => 2, 'department_id' => $this->deptA->id,
    ]);

    $session = InspectionSession::factory()->create([
        'department_id' => $this->deptA->id,
        'inspector_id' => $inspector->id,
        'inspection_date' => now(),
    ]);

    $this->alphaLog = InspectionLog::factory()->create([
        'session_id' => $session->id,
        'result' => 'fail',
        'inspected_at' => now(),
    ]);

    // Manager created with the user form's default blank department option.
    $this->departmentlessManager = User::factory()->create([
        'role' => 'manager', 'level' => 5, 'department_id' => null,
    ]);
});

/** Capture the `logs` variable the money PDF view is rendered with. */
function captureMoneyReportLogs(): \Closure
{
    $captured = new stdClass();
    $captured->logs = null;

    View::composer('reports.pdf.money_summary', function ($view) use ($captured) {
        $captured->logs = $view->getData()['logs'] ?? null;
    });

    return fn () => $captured->logs;
}

test('a departmentless manager gets no other-department rows in the money export', function () {
    $logs = captureMoneyReportLogs();

    $this->actingAs($this->departmentlessManager);
    $this->get(route('reports.money.pdf', ['month' => now()->format('Y-m')]));

    expect($logs()) ->not->toBeNull()
        ->and($logs()->pluck('id')->all())->not->toContain($this->alphaLog->id);
});

test('a manager in the owning department still gets their own rows', function () {
    $logs = captureMoneyReportLogs();

    $ownManager = User::factory()->create([
        'role' => 'manager', 'level' => 5, 'department_id' => $this->deptA->id,
    ]);

    $this->actingAs($ownManager);
    $this->get(route('reports.money.pdf', ['month' => now()->format('Y-m')]));

    expect($logs())->not->toBeNull()
        ->and($logs()->pluck('id')->all())->toContain($this->alphaLog->id);
});

test('an admin still gets every department', function () {
    $logs = captureMoneyReportLogs();

    $admin = User::factory()->create([
        'role' => 'admin', 'level' => 9, 'department_id' => null,
    ]);

    $this->actingAs($admin);
    $this->get(route('reports.money.pdf', ['month' => now()->format('Y-m')]));

    expect($logs())->not->toBeNull()
        ->and($logs()->pluck('id')->all())->toContain($this->alphaLog->id);
});
