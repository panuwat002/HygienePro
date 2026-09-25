<?php

/**
 * Pick ห้องแคะ and get ห้องแคะ's form, for any date.
 *
 * Reports filter on inspection_sessions.department_id. Rounds walked before
 * the room was split out carry Production, so picking the new department
 * returned an empty page and the only way to see those people's history was to
 * print Production's form and read past everyone else.
 *
 * The filter now matches a session's own department OR the current department
 * of the staff inspected in it, and the personnel rows are narrowed to the
 * people that department actually holds. It is additive on purpose: asking for
 * Production still returns every row it returned before, so a form printed and
 * signed last month still regenerates the same way. Moving an employee must
 * never quietly rewrite a document somebody already put their name on.
 */

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-25 10:00:00');

    $this->production = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);

    $this->pickingRoom = Department::create([
        'dept_name' => 'Production (ห้องแคะ)', 'dept_code' => 'PDK',
        'visibility_type' => 'isolated', 'parent_department_id' => $this->production->id,
    ]);

    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 07.00-16.00', 'start_time' => '07:00:00', 'end_time' => '16:00:00',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'follow-admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 9,
        'department_id' => $this->qa->id,
    ]);

    $this->checkpoint = Checkpoint::create([
        'title' => 'ตรวจเล็บ', 'is_active' => true, 'type' => 'person',
    ]);

    // One round walked under Production back on the 23rd, before the room existed.
    $this->pastRound = InspectionSession::create([
        'inspector_id' => $this->admin->id, 'department_id' => $this->production->id,
        'type' => 'personnel', 'inspection_date' => '2026-09-23',
        'shift' => 'custom_' . $this->shift->id, 'round' => 1, 'status' => 'completed',
    ]);

    $this->followSeq = 0;
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Inspected under Production on the 23rd; $now is where they sit today.
 */
function staffInspectedUnderProduction($ctx, Department $now): Employee
{
    $ctx->followSeq++;

    $employee = Employee::create([
        'employee_id' => 'D' . $ctx->followSeq, 'fullname' => 'Worker ' . $ctx->followSeq,
        'department_id' => $now->id, 'shift_id' => $ctx->shift->id,
        'qr_code_hash' => 'dh' . $ctx->followSeq, 'is_active' => true,
    ]);

    $employee->checkpoints()->attach($ctx->checkpoint->id);

    InspectionLog::create([
        'session_id' => $ctx->pastRound->id, 'checkpoint_id' => $ctx->checkpoint->id,
        'employee_id' => $employee->id, 'result' => 'pass',
        'inspected_at' => Carbon::parse('2026-09-23 08:00:00'),
    ]);

    return $employee;
}

function dailyPreview($ctx, Department $dept)
{
    return $ctx->actingAs($ctx->admin)
        ->get(route('reports.daily', [
            'date' => '2026-09-23',
            'department_id' => $dept->id,
            'report_type' => 'person',
        ]))
        ->assertSuccessful();
}

it('finds the round when asked for the department the staff sit in now', function () {
    staffInspectedUnderProduction($this, $this->pickingRoom);

    $sessions = dailyPreview($this, $this->pickingRoom)->viewData('sessions');

    expect($sessions->pluck('id')->all())->toBe([$this->pastRound->id]);
});

it('still finds it under the department that actually walked it', function () {
    staffInspectedUnderProduction($this, $this->pickingRoom);
    staffInspectedUnderProduction($this, $this->production);

    $sessions = dailyPreview($this, $this->production)->viewData('sessions');

    expect($sessions->pluck('id')->all())->toBe([$this->pastRound->id]);
});

it('returns nothing for a department with neither the round nor the people', function () {
    staffInspectedUnderProduction($this, $this->production);

    $sessions = dailyPreview($this, $this->qa)->viewData('sessions');

    expect($sessions)->toBeEmpty();
});

it('tells the reader the round was walked under another department', function () {
    staffInspectedUnderProduction($this, $this->pickingRoom);

    dailyPreview($this, $this->pickingRoom)
        ->assertSee('ดำเนินการภายใต้แผนกอื่น', false);
});

it('says nothing of the sort when the round is the department own', function () {
    staffInspectedUnderProduction($this, $this->production);

    dailyPreview($this, $this->production)
        ->assertDontSee('ดำเนินการภายใต้แผนกอื่น', false);
});

/**
 * The row rule. A personnel row belongs on a department's form when that
 * department walked the round, or when the person is theirs today.
 */
function rowBelongs($departmentId, $sessionDeptId, $employeeDeptId): bool
{
    $method = new ReflectionMethod(App\Http\Controllers\ReportController::class, 'personRowBelongsToDepartment');
    $method->setAccessible(true);

    return $method->invoke(
        app(App\Http\Controllers\ReportController::class),
        $departmentId,
        $sessionDeptId,
        $employeeDeptId
    );
}

it('keeps a row whose person now belongs to the department asked for', function () {
    expect(rowBelongs($this->pickingRoom->id, $this->production->id, $this->pickingRoom->id))->toBeTrue();
});

it('keeps every row of a round the department itself walked', function () {
    expect(rowBelongs($this->production->id, $this->production->id, $this->pickingRoom->id))->toBeTrue();
});

it('drops a colleague who stayed behind in the parent department', function () {
    expect(rowBelongs($this->pickingRoom->id, $this->production->id, $this->production->id))->toBeFalse();
});

it('keeps every row when no department was asked for', function () {
    expect(rowBelongs(null, $this->production->id, $this->pickingRoom->id))->toBeTrue()
        ->and(rowBelongs('', $this->production->id, $this->production->id))->toBeTrue();
});

it('builds both forms without error', function () {
    staffInspectedUnderProduction($this, $this->pickingRoom);
    staffInspectedUnderProduction($this, $this->production);

    foreach ([$this->pickingRoom, $this->production] as $dept) {
        $this->actingAs($this->admin)
            ->get(route('reports.export.pdf', [
                'date' => '2026-09-23',
                'department_id' => $dept->id,
                'report_type' => 'person',
            ]))
            ->assertSuccessful();
    }
});
