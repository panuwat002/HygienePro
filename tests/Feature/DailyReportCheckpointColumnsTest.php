<?php

/**
 * The form has to show what that work area is actually required to pass.
 *
 * Production staff walk through an air shower on the way in; ห้องแคะ staff do
 * not, so that checkpoint is never assigned to them. The daily form built its
 * columns from the master checkpoint list, so both groups printed the same ten
 * columns and the room's staff got a "-" in #eee - invisible on paper - where
 * the air shower belongs.
 *
 * On an audit that blank is a question with no answer on the page: was this
 * not required, or was it missed? The two have to look different, and a
 * checkpoint that applies to nobody in the report should not be a column at all.
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

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 07.00-16.00', 'start_time' => '07:00:00', 'end_time' => '16:00:00',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'form-admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 9,
        'department_id' => $this->production->id,
    ]);

    // Nine shared checks plus the air shower, which only the line walks through.
    $this->shared = collect(range(1, 9))->map(fn ($i) => Checkpoint::create([
        'title' => 'ตรวจข้อ ' . $i, 'is_active' => true, 'type' => 'person',
    ]));

    $this->airShower = Checkpoint::create([
        'title' => 'ผ่านตู้เป่าลม', 'is_active' => true, 'type' => 'person',
    ]);

    $this->formSeq = 0;
});

afterEach(function () {
    Carbon::setTestNow();
});

function inspectedStaff($ctx, Department $dept, $checkpoints): Employee
{
    $ctx->formSeq++;

    $employee = Employee::create([
        'employee_id' => 'F' . $ctx->formSeq, 'fullname' => 'Worker ' . $ctx->formSeq,
        'department_id' => $dept->id, 'shift_id' => $ctx->shift->id,
        'qr_code_hash' => 'fh' . $ctx->formSeq, 'is_active' => true,
    ]);

    $employee->checkpoints()->attach($checkpoints->pluck('id'));

    $session = InspectionSession::create([
        'inspector_id' => $ctx->admin->id, 'department_id' => $dept->id,
        'type' => 'personnel', 'inspection_date' => '2026-09-25',
        'shift' => 'custom_' . $ctx->shift->id, 'round' => $ctx->formSeq,
        'status' => 'completed',
    ]);

    foreach ($checkpoints as $checkpoint) {
        InspectionLog::create([
            'session_id' => $session->id, 'checkpoint_id' => $checkpoint->id,
            'employee_id' => $employee->id, 'result' => 'pass',
            'inspected_at' => Carbon::parse('2026-09-25 08:00:00'),
        ]);
    }

    return $employee;
}

function reportColumns($ctx, array $matrix, array $assigned)
{
    $method = new ReflectionMethod(App\Http\Controllers\ReportController::class, 'personColumnsForReport');
    $method->setAccessible(true);

    return $method->invoke(
        app(App\Http\Controllers\ReportController::class),
        Checkpoint::where('type', 'person')->orderBy('id')->get(),
        $matrix,
        collect($assigned)
    );
}

it('drops a column nobody in the report is required to pass', function () {
    $roomStaff = inspectedStaff($this, $this->pickingRoom, $this->shared);

    $columns = reportColumns(
        $this,
        [$roomStaff->id => ['results' => []]],
        [$roomStaff->id => $this->shared->pluck('id')->all()],
    );

    expect($columns)->toHaveCount(9)
        ->and($columns->pluck('title'))->not->toContain('ผ่านตู้เป่าลม');
});

it('keeps every column the line is required to pass', function () {
    $lineStaff = inspectedStaff($this, $this->production, $this->shared->concat([$this->airShower]));

    $columns = reportColumns(
        $this,
        [$lineStaff->id => ['results' => []]],
        [$lineStaff->id => $this->shared->pluck('id')->push($this->airShower->id)->all()],
    );

    expect($columns)->toHaveCount(10)
        ->and($columns->pluck('title'))->toContain('ผ่านตู้เป่าลม');
});

/**
 * Before the room is split out the two groups share a report, so the column has
 * to stay - and the cell is what has to explain itself instead.
 */
it('keeps the column while one report still mixes both groups', function () {
    $roomStaff = inspectedStaff($this, $this->production, $this->shared);
    $lineStaff = inspectedStaff($this, $this->production, $this->shared->concat([$this->airShower]));

    $columns = reportColumns(
        $this,
        [$roomStaff->id => ['results' => []], $lineStaff->id => ['results' => []]],
        [
            $roomStaff->id => $this->shared->pluck('id')->all(),
            $lineStaff->id => $this->shared->pluck('id')->push($this->airShower->id)->all(),
        ],
    );

    expect($columns)->toHaveCount(10);
});

/**
 * A result recorded against a checkpoint nobody is assigned any more still has
 * to appear, or the form would hide an inspection that actually happened.
 */
it('keeps a column that carries a result even when no one is assigned it', function () {
    $staff = inspectedStaff($this, $this->pickingRoom, $this->shared);

    $columns = reportColumns(
        $this,
        [$staff->id => ['results' => [$this->airShower->id => 'a log']]],
        [$staff->id => $this->shared->pluck('id')->all()],
    );

    expect($columns->pluck('title'))->toContain('ผ่านตู้เป่าลม');
});

/**
 * Nobody has checkpoints assigned on older data. Narrowing to an empty set
 * would print a form with no columns at all.
 */
it('falls back to the full list when nobody has assignments', function () {
    $staff = inspectedStaff($this, $this->production, collect());

    $columns = reportColumns($this, [$staff->id => ['results' => []]], []);

    expect($columns)->toHaveCount(10);
});

it('builds the form for each department without error', function () {
    inspectedStaff($this, $this->pickingRoom, $this->shared);
    inspectedStaff($this, $this->production, $this->shared->concat([$this->airShower]));

    foreach ([$this->pickingRoom, $this->production] as $dept) {
        $this->actingAs($this->admin)
            ->get(route('reports.export.pdf', [
                'date' => '2026-09-25',
                'department_id' => $dept->id,
                'report_type' => 'person',
            ]))
            ->assertSuccessful();
    }
});
