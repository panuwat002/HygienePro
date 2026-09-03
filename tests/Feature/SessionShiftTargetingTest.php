<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Reproduces the "17:00 round shows no employees" report.
 *
 * ShiftSeeder never creates a shift literally named "กะบ่าย"/"afternoon" — every row is
 * "<type> <HH.mm>-<HH.mm>" (e.g. "กะบ่าย 17.00-02.00") with the canonical value in shift_type.
 * A session auto-started without picking a shift card stores the generic key from
 * Shift::detectCurrent(), so that key has to resolve through shift_type.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);

    $this->inspector = User::create([
        'name' => 'Inspector', 'email' => 'inspector@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->dept->id,
    ]);

    // Named exactly as ShiftSeeder generates them.
    $this->afternoonShift = Shift::create([
        'shift_name' => 'กะบ่าย 17.00-02.00', 'shift_type' => 'กะบ่าย',
        'start_time' => '17:00:00', 'end_time' => '02:00:00',
    ]);
    $this->morningShift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => 'กะเช้า',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeEmployee(Department $dept, Shift $shift, string $code): Employee
{
    $emp = Employee::create([
        'employee_id' => $code,
        'fullname' => 'Worker ' . $code,
        'department_id' => $dept->id,
        'shift_id' => $shift->id,
        'qr_code_hash' => hash('sha256', $code),
        'is_active' => true,
    ]);

    EmployeeSchedule::create([
        'employee_id' => $emp->id,
        'date' => now()->startOfDay(),
        'shift_id' => $shift->id,
        'is_day_off' => false,
    ]);

    return $emp;
}

it('resolves the generic afternoon key to shifts whose shift_type is กะบ่าย', function () {
    $session = new InspectionSession(['shift' => 'afternoon']);

    $resolved = $session->getResolvedShifts();

    expect($resolved['shift_ids'])->toContain($this->afternoonShift->id)
        ->and($resolved['shift_ids'])->not->toContain($this->morningShift->id);
});

it('lists the rostered employees for a 17:00 round started without picking a shift card', function () {
    Carbon::setTestNow(Carbon::today()->setTime(17, 0));

    foreach (range(1, 15) as $i) {
        makeEmployee($this->dept, $this->afternoonShift, 'EMP' . $i);
    }
    makeEmployee($this->dept, $this->morningShift, 'EMPM1');

    // What InspectionService::startSession() stores when the shift input arrives empty.
    expect(Shift::detectCurrent())->toBe('afternoon');

    $session = InspectionSession::create([
        'department_id' => $this->dept->id,
        'inspection_date' => now()->toDateString(),
        'shift' => Shift::detectCurrent(),
        'inspector_id' => $this->inspector->id,
        'status' => 'in_progress',
        'type' => 'personnel',
        'round' => 1,
    ]);

    expect($session->getTargetEmployees()->count())->toBe(15);
});

it('still excludes employees marked day off on the roster', function () {
    Carbon::setTestNow(Carbon::today()->setTime(17, 0));

    $working = makeEmployee($this->dept, $this->afternoonShift, 'EMP1');
    $off = makeEmployee($this->dept, $this->afternoonShift, 'EMP2');
    EmployeeSchedule::where('employee_id', $off->id)->update(['is_day_off' => true]);

    $session = InspectionSession::create([
        'department_id' => $this->dept->id,
        'inspection_date' => now()->toDateString(),
        'shift' => 'afternoon',
        'inspector_id' => $this->inspector->id,
        'status' => 'in_progress',
        'type' => 'personnel',
        'round' => 1,
    ]);

    $ids = $session->getTargetEmployees()->pluck('id')->all();
    expect($ids)->toBe([$working->id]);
});

it('keeps the generic shift label instead of listing every matched variant', function () {
    Shift::create([
        'shift_name' => 'กะบ่าย 13.00-22.00', 'shift_type' => 'กะบ่าย',
        'start_time' => '13:00:00', 'end_time' => '22:00:00',
    ]);

    expect((new InspectionSession(['shift' => 'afternoon']))->shift_label)->toBe('กะบ่าย');
});

it('still shows the full shift name for a hand-picked custom shift card', function () {
    $session = new InspectionSession(['shift' => 'custom_' . $this->afternoonShift->id]);

    expect($session->shift_label)->toBe('กะบ่าย 17.00-02.00')
        ->and($session->getResolvedShifts()['shift_ids'])->toBe([$this->afternoonShift->id]);
});

it('combines a generic key with a hand-picked custom shift', function () {
    $session = new InspectionSession(['shift' => 'afternoon,custom_' . $this->morningShift->id]);

    $ids = $session->getResolvedShifts()['shift_ids'];

    expect($ids)->toContain($this->afternoonShift->id)
        ->and($ids)->toContain($this->morningShift->id)
        ->and($session->shift_label)->toBe('กะบ่าย, กะเช้า 08.00-17.00');
});
