<?php

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use App\Services\InspectionService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qaDept = Department::create([
        'dept_name' => 'QA', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);
    $this->inspector = User::create([
        'name' => 'Inspector', 'email' => 'inspector@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->qaDept->id,
    ]);
    $this->supervisor = User::create([
        'name' => 'Supervisor', 'email' => 'sup@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->qaDept->id,
    ]);
    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => 'กะเช้า',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);
    Carbon::setTestNow(Carbon::today()->setTime(9, 0));
    $this->service = app(InspectionService::class);
});

afterEach(fn () => Carbon::setTestNow());

/* ---------------- C1: reject -> rework must announce again ---------------- */

it('announces again after a rejected round is reworked and finished', function () {
    $checkpoint = Checkpoint::create(['title' => 'CP', 'type' => 'person', 'is_active' => true]);
    $employee = Employee::create([
        'employee_id' => 'E1', 'fullname' => 'Worker', 'department_id' => $this->dept->id,
        'qr_code_hash' => hash('sha256', 'E1'), 'is_active' => true,
    ]);

    $session = $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false, 'custom_' . $this->shift->id
    );
    $log = InspectionLog::create([
        'session_id' => $session->id, 'employee_id' => $employee->id,
        'checkpoint_id' => $checkpoint->id, 'result' => 'fail', 'inspected_at' => now(),
    ]);

    $this->service->finishSession($session);
    expect($session->refresh()->finished_notified_at)->not->toBeNull();

    $this->actingAs($this->supervisor)->post(route('inspection.reject'), [
        'ids' => [$log->id],
        'comment' => 'ตรวจไม่ครบ กรุณาตรวจใหม่',
    ]);

    // The supervisor who sent it back must hear about the rework, so the marker has to clear.
    $session->refresh();
    expect($session->status)->toBe('in_progress')
        ->and($session->finished_notified_at)->toBeNull();
});

/* ---------------- I1: a personnel round names a real shift card ---------------- */

it('refuses to open a new personnel round on a guessed generic shift key', function () {
    expect(fn () => $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false, 'morning'
    ))->toThrow(ValidationException::class);

    expect(InspectionSession::where('type', 'personnel')->count())->toBe(0);
});

it('still resumes a legacy round that was stored with a generic key', function () {
    // Sessions already in the database predate the rule and must stay usable.
    $legacy = InspectionSession::create([
        'department_id' => $this->dept->id, 'inspection_date' => now()->toDateString(),
        'shift' => 'morning', 'inspector_id' => $this->inspector->id,
        'status' => 'in_progress', 'type' => 'personnel', 'round' => 1,
    ]);

    $resumed = $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false, 'morning'
    );

    expect($resumed->id)->toBe($legacy->id);
});

it('accepts several picked shift cards on one round', function () {
    $other = Shift::create([
        'shift_name' => 'กะเช้า 10.00-19.00', 'shift_type' => 'กะเช้า',
        'start_time' => '10:00:00', 'end_time' => '19:00:00',
    ]);

    $session = $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false,
        'custom_' . $this->shift->id . ',custom_' . $other->id
    );

    expect($session->getResolvedShifts()['shift_ids'])
        ->toEqualCanonicalizing([$this->shift->id, $other->id]);
});

/* ---------------- I3: a round whose logs were signed off is closed ---------------- */

it('will not continue a round whose logs a supervisor has already verified', function () {
    $checkpoint = Checkpoint::create(['title' => 'CP', 'type' => 'person', 'is_active' => true]);
    $session = $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false, 'custom_' . $this->shift->id
    );
    InspectionLog::create([
        'session_id' => $session->id, 'checkpoint_id' => $checkpoint->id,
        'result' => 'pass', 'inspected_at' => now(),
        // What InspectionController::verify() writes - the session row itself stays untouched.
        'verified_at' => now(), 'verifier_id' => $this->supervisor->id,
        'verification_status' => 'verified',
    ]);
    $this->service->finishSession($session);

    expect($this->service->canReopen($session->refresh()))->toBeFalse();

    $next = $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false, 'custom_' . $this->shift->id
    );

    expect($next->id)->not->toBe($session->id)
        ->and($next->round)->toBe(2);
});

/* ---------------- C4: the round panel reaches personnel too ---------------- */

it('continues a finished personnel round on the shift the inspector picked', function () {
    $employee = Employee::create([
        'employee_id' => 'E1', 'fullname' => 'Worker', 'department_id' => $this->dept->id,
        'shift_id' => $this->shift->id, 'qr_code_hash' => hash('sha256', 'E1'), 'is_active' => true,
    ]);
    EmployeeSchedule::create([
        'employee_id' => $employee->id, 'date' => now()->startOfDay(),
        'shift_id' => $this->shift->id, 'is_day_off' => false,
    ]);

    $first = $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false, 'custom_' . $this->shift->id
    );
    $this->service->finishSession($first);

    $again = $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false, 'custom_' . $this->shift->id
    );

    expect($again->id)->toBe($first->id)
        ->and($again->round)->toBe(1)
        ->and($again->getTargetEmployees()->count())->toBe(1);
});

it('tells the dashboard whether a finished personnel round can be continued', function () {
    $first = $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false, 'custom_' . $this->shift->id
    );
    $this->service->finishSession($first);

    $response = $this->actingAs($this->inspector)->getJson(route('inspection.stats', [
        'type' => 'personnel', 'department' => $this->dept->id,
    ]) . '?shifts[]=custom_' . $this->shift->id);

    $response->assertOk()->assertJson([
        'session_exists' => true,
        'session_status' => 'completed',
        'session_can_reopen' => true,
    ]);
});
