<?php

use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * /dashboard is gated by `auth` + `verified` only, and dashboard.blade.php carries no
 * role guard of its own. The CAR statistics block (InspectionController::home) had no
 * department filter and a cache key with no department component, so every user saw a
 * per-department CAR breakdown for the whole company, defeating visibility_type =
 * 'isolated'. The fix scopes those aggregates with
 * `whereHas('log.session', fn ($q) => $q->where('department_id', $scopeDeptId))`.
 *
 * NOTE: this cannot be driven through GET /dashboard in the test suite. That action
 * issues a raw query using MySQL's CONCAT(), which SQLite does not implement, so the
 * route 500s under the test connection regardless of these changes. This test therefore
 * pins the relation path the fix depends on — CorrectiveAction -> log -> session ->
 * department_id — which is the part that could silently be wrong.
 */
beforeEach(function () {
    $this->deptA = Department::create([
        'dept_name' => 'AlphaDept', 'dept_code' => 'ALP', 'visibility_type' => 'isolated',
    ]);
    $this->deptB = Department::create([
        'dept_name' => 'BravoDept', 'dept_code' => 'BRV', 'visibility_type' => 'isolated',
    ]);

    $this->cars = [];
    foreach (['A' => $this->deptA, 'B' => $this->deptB] as $key => $dept) {
        $session = InspectionSession::factory()->create(['department_id' => $dept->id]);
        $log = InspectionLog::factory()->create(['session_id' => $session->id]);
        $this->cars[$key] = CorrectiveAction::factory()->create([
            'inspection_log_id' => $log->id,
            'status' => 'open',
            'created_at' => now(),
        ]);
    }
});

test('scoping CARs by log.session.department_id returns only that department', function () {
    $scoped = CorrectiveAction::whereHas(
        'log.session',
        fn ($q) => $q->where('department_id', $this->deptA->id)
    )->pluck('id')->all();

    expect($scoped)->toContain($this->cars['A']->id)
        ->and($scoped)->not->toContain($this->cars['B']->id);
});

test('scoping by the departmentless sentinel 0 returns nothing', function () {
    // User::scopedDepartmentId() returns int 0 when department_id is null; the scope
    // must match no rows rather than being skipped.
    $scoped = CorrectiveAction::whereHas(
        'log.session',
        fn ($q) => $q->where('department_id', 0)
    )->count();

    expect($scoped)->toBe(0);
});

test('an unscoped query still returns every department', function () {
    expect(CorrectiveAction::count())->toBe(2);
});
