<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 5,
    ]);
    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => 'กะเช้า',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);
    Carbon::setTestNow(Carbon::today()->setTime(9, 0));
});

afterEach(fn () => Carbon::setTestNow());

function worker($ctx, string $code, ?int $shiftId, bool $dayOff = false): Employee
{
    $emp = Employee::create([
        'employee_id' => $code, 'fullname' => 'Worker ' . $code,
        'department_id' => $ctx->dept->id, 'shift_id' => $shiftId,
        'qr_code_hash' => hash('sha256', $code), 'is_active' => true,
    ]);
    EmployeeSchedule::create([
        'employee_id' => $emp->id, 'date' => now()->startOfDay(),
        'shift_id' => $shiftId, 'is_day_off' => $dayOff,
    ]);

    return $emp;
}

function roundOn($ctx, string $shiftKey): InspectionSession
{
    return InspectionSession::create([
        'department_id' => $ctx->dept->id, 'inspection_date' => now()->toDateString(),
        'shift' => $shiftKey, 'inspector_id' => $ctx->inspector->id,
        'status' => 'in_progress', 'type' => 'personnel', 'round' => 1,
    ]);
}

/* ---------------- I2: an unresolvable shift must not mean "everyone" ---------------- */

it('targets nobody when the round names a shift that no longer exists', function () {
    worker($this, 'E1', $this->shift->id);
    worker($this, 'E2', $this->shift->id, dayOff: true);

    $session = roundOn($this, 'custom_' . $this->shift->id);
    expect($session->getTargetEmployees()->count())->toBe(1);

    // Someone deletes the shift row. The session still points at custom_<id>.
    DB::table('shifts')->where('id', $this->shift->id)->delete();

    // Falling back to "everyone in the department" would hand bulk pass the whole
    // department, day-offs included.
    expect($session->fresh()->getTargetEmployees()->count())->toBe(0);
});

it('still targets everyone when the round names no shift at all', function () {
    worker($this, 'E1', $this->shift->id);
    worker($this, 'E2', $this->shift->id);

    // Legacy rounds saved before a shift was required.
    $session = roundOn($this, '');

    expect($session->getTargetEmployees()->count())->toBe(2);
});

it('refuses to delete a shift that employees or rosters still point at', function () {
    worker($this, 'E1', $this->shift->id);

    $this->actingAs($this->admin)
        ->delete(route('shifts.destroy', $this->shift->id))
        ->assertRedirect();

    expect(Shift::find($this->shift->id))->not->toBeNull();
});

it('deletes a shift nothing points at', function () {
    $unused = Shift::create([
        'shift_name' => 'กะดึก 22.00-07.00', 'shift_type' => 'กะดึก',
        'start_time' => '22:00:00', 'end_time' => '07:00:00',
    ]);

    $this->actingAs($this->admin)->delete(route('shifts.destroy', $unused->id));

    expect(Shift::find($unused->id))->toBeNull();
});

/* ---------------- I4: the dashboard counts the picked shift, not a name match ---------------- */

it('counts the round target from the picked shift card on the dashboard', function () {
    $other = Shift::create([
        'shift_name' => 'กะดึก 19.00-04.00', 'shift_type' => 'กะดึก',
        'start_time' => '19:00:00', 'end_time' => '04:00:00',
    ]);
    worker($this, 'E1', $this->shift->id);
    worker($this, 'E2', $this->shift->id);
    worker($this, 'N1', $other->id);

    roundOn($this, 'custom_' . $this->shift->id);

    $response = $this->actingAs($this->inspector)->get(route('inspection.dashboard', 'personnel'));

    $response->assertOk();
    // Matching on shift_name could never hit "custom_<id>", so this used to be 0 - and a
    // zero total made the dashboard list every uninspected employee in the department.
    expect($response->viewData('totalTargets'))->toBe(2)
        ->and($response->viewData('remainingList')->pluck('employee_id')->all())
        ->toEqualCanonicalizing(['E1', 'E2']);
});
