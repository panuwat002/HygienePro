<?php

use App\Models\Department;
use App\Models\User;
use App\Models\Shift;
use App\Models\InspectionSession;
use App\Models\InspectionLog;
use App\Models\Checkpoint;
use App\Models\Employee;
use App\Models\ActivityLog;
use App\Services\InspectionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\InspectionController;

beforeEach(function () {
    Cache::flush();
    Http::fake(); // never hit the external AI service (localhost:8001) during tests

    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);

    $this->inspector = User::create([
        'name' => 'Inspector', 'email' => 'inspector@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->dept->id,
    ]);
    $this->inspector->forceFill(['email_verified_at' => now()])->save();

    // Morning shift: 06:00 - 13:00
    Shift::create(['shift_name' => 'morning', 'start_time' => '06:00:00', 'end_time' => '13:00:00']);

    $this->service = app(InspectionService::class);
});

afterEach(function () {
    Carbon::setTestNow(); // reset frozen time
});

function makeOpenSession($ctx, string $shift = 'morning', string $date = '2026-07-29', int $round = 1): InspectionSession {
    return InspectionSession::create([
        'inspector_id'    => $ctx->inspector->id,
        'department_id'   => $ctx->dept->id,
        'type'            => 'personnel',
        'inspection_date' => $date,
        'shift'           => $shift,
        'round'           => $round,
        'status'          => 'in_progress',
        'is_locked'       => false,
    ]);
}

test('auto-closes a session left open past shift end + grace with no activity', function () {
    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this);

    // Now 16:00: shift ended 13:00, +2h grace = 15:00, last activity 12:00 (>30m idle)
    Carbon::setTestNow('2026-07-29 16:00:00');

    expect($this->service->autoCloseStaleSessions())->toBe(1);
    expect($session->refresh()->status)->toBe('completed');
    expect(
        ActivityLog::where('model_id', $session->id)
            ->where('action', 'auto_close')
            ->whereNull('user_id')
            ->exists()
    )->toBeTrue();
});

test('does not close a session still within shift end + grace', function () {
    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this);

    Carbon::setTestNow('2026-07-29 14:30:00'); // 13:00 + 2h = 15:00, not reached
    expect($this->service->autoCloseStaleSessions())->toBe(0);
    expect($session->refresh()->status)->toBe('in_progress');
});

test('does not close a session with recent inspection activity', function () {
    $cp  = Checkpoint::create(['title' => 'Nails', 'is_active' => true, 'type' => 'person']);
    $emp = Employee::create([
        'employee_id' => 'E1', 'fullname' => 'Somchai', 'department_id' => $this->dept->id,
        'qr_code_hash' => 'h1', 'is_active' => true,
    ]);

    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this);

    // Activity at 15:50 — within 30m of evaluation time
    Carbon::setTestNow('2026-07-29 15:50:00');
    InspectionLog::create([
        'session_id' => $session->id, 'checkpoint_id' => $cp->id, 'employee_id' => $emp->id,
        'result' => 'pass', 'inspected_at' => now(),
    ]);

    Carbon::setTestNow('2026-07-29 16:00:00'); // past grace, but last activity 15:50 > 15:30
    expect($this->service->autoCloseStaleSessions())->toBe(0);
    expect($session->refresh()->status)->toBe('in_progress');
});

test('ignores completed and locked sessions', function () {
    Carbon::setTestNow('2026-07-29 16:00:00');

    $completed = makeOpenSession($this, 'morning', '2026-07-29', 1);
    $completed->update(['status' => 'completed']);

    $locked = makeOpenSession($this, 'morning', '2026-07-29', 2);
    $locked->update(['is_locked' => true]);

    expect($this->service->autoCloseStaleSessions())->toBe(0);
});

test('does not close when shift end time is unknown (fail-safe)', function () {
    // No 'night' shift row exists -> shiftEndAt() returns null
    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this, 'night');

    Carbon::setTestNow('2026-07-29 23:00:00');
    expect($this->service->autoCloseStaleSessions())->toBe(0);
    expect($session->refresh()->status)->toBe('in_progress');
});

test('handles a night shift that wraps past midnight', function () {
    Shift::create(['shift_name' => 'night', 'start_time' => '22:00:00', 'end_time' => '06:00:00']);

    Carbon::setTestNow('2026-07-28 23:00:00');
    $session = makeOpenSession($this, 'night', '2026-07-28');

    // Shift end = 2026-07-29 06:00; +2h grace = 08:00
    Carbon::setTestNow('2026-07-29 09:00:00');
    expect($this->service->autoCloseStaleSessions())->toBe(1);
    expect($session->refresh()->status)->toBe('completed');
});

test('the inspections:auto-close-stale command closes stale sessions', function () {
    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this);

    Carbon::setTestNow('2026-07-29 16:00:00');

    $this->artisan('inspections:auto-close-stale')
        ->expectsOutputToContain('Auto-closed 1')
        ->assertExitCode(0);

    expect($session->refresh()->status)->toBe('completed');
});

test('the dashboard heartbeat auto-closes stale sessions', function () {
    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this);

    Carbon::setTestNow('2026-07-29 16:00:00');
    app(InspectionController::class)->autoCloseHeartbeat();

    expect($session->refresh()->status)->toBe('completed');
});

test('the heartbeat respects the enabled kill-switch', function () {
    config(['inspection.auto_close.enabled' => false]);

    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this);

    Carbon::setTestNow('2026-07-29 16:00:00');
    app(InspectionController::class)->autoCloseHeartbeat();

    expect($session->refresh()->status)->toBe('in_progress');
});

test('the heartbeat is throttled to once per window', function () {
    $controller = app(InspectionController::class);
    Carbon::setTestNow('2026-07-29 16:00:00');

    // First run closes the first stale session and sets the throttle key
    $first = makeOpenSession($this, 'morning', '2026-07-29', 1);
    DB::table('inspection_sessions')->where('id', $first->id)
        ->update(['created_at' => '2026-07-29 12:00:00', 'updated_at' => '2026-07-29 12:00:00']);
    $controller->autoCloseHeartbeat();
    expect($first->refresh()->status)->toBe('completed');

    // A second stale session appears, but the heartbeat is throttled -> untouched
    $second = makeOpenSession($this, 'morning', '2026-07-29', 2);
    DB::table('inspection_sessions')->where('id', $second->id)
        ->update(['created_at' => '2026-07-29 12:00:00', 'updated_at' => '2026-07-29 12:00:00']);
    $controller->autoCloseHeartbeat();
    expect($second->refresh()->status)->toBe('in_progress');
});
