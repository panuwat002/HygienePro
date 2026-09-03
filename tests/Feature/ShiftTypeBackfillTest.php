<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * shift_type is what a generic session key ('afternoon') resolves against, so every shift
 * has to carry one — including shifts created before the admin form offered the field.
 */
it('derives the type from the shift name prefix', function (string $name, string $expected) {
    expect(Shift::deriveType($name, '17:00:00'))->toBe($expected);
})->with([
    ['กะเช้า 08.00-17.00', 'กะเช้า'],
    ['กะบ่าย 17.00-02.00', 'กะบ่าย'],
    ['กะดึก 19.00-04.00', 'กะดึก'],
    ['Morning A', 'กะเช้า'],
    ['night crew', 'กะดึก'],
]);

it('falls back to the start_time bucket when the name gives nothing away', function (string $start, string $expected) {
    expect(Shift::deriveType('กะโอที', $start))->toBe($expected);
})->with([
    ['06:00:00', 'กะเช้า'],
    ['12:00:00', 'กะเช้า'],
    ['12:30:00', 'กะบ่าย'],
    ['17:00:00', 'กะบ่าย'],
    ['18:00:00', 'กะบ่าย'],
    ['19:00:00', 'กะดึก'],
    ['03:00:00', 'กะดึก'],
]);

it('treats a day-off shift as วันหยุด regardless of name or time', function () {
    expect(Shift::deriveType('กะบ่าย 17.00-02.00', '17:00:00', true))->toBe('วันหยุด');
});

it('backfills shifts left without a type by the old admin form', function () {
    // Bypass the model so the row lands in the pre-fix state.
    $id = DB::table('shifts')->insertGetId([
        'shift_name' => 'กะโอที 17.00-02.00', 'shift_type' => null,
        'start_time' => '17:00:00', 'end_time' => '02:00:00',
        'is_dayoff' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $curated = DB::table('shifts')->insertGetId([
        'shift_name' => 'กะพิเศษ', 'shift_type' => 'กะดึก',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
        'is_dayoff' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    require_once base_path('database/migrations/2026_09_03_000000_backfill_shift_type_on_shifts.php');
    (require base_path('database/migrations/2026_09_03_000000_backfill_shift_type_on_shifts.php'))->up();

    expect(Shift::find($id)->shift_type)->toBe('กะบ่าย')
        // A value already set by hand is never overwritten.
        ->and(Shift::find($curated)->shift_type)->toBe('กะดึก');
});

it('lists employees of a backfilled shift on a 17:00 round', function () {
    $dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $inspector = User::create([
        'name' => 'Inspector', 'email' => 'inspector@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $dept->id,
    ]);

    $shiftId = DB::table('shifts')->insertGetId([
        'shift_name' => 'กะโอที 17.00-02.00', 'shift_type' => null,
        'start_time' => '17:00:00', 'end_time' => '02:00:00',
        'is_dayoff' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $emp = Employee::create([
        'employee_id' => 'EMP1', 'fullname' => 'Worker One',
        'department_id' => $dept->id, 'shift_id' => $shiftId,
        'qr_code_hash' => hash('sha256', 'EMP1'), 'is_active' => true,
    ]);
    EmployeeSchedule::create([
        'employee_id' => $emp->id, 'date' => now()->startOfDay(),
        'shift_id' => $shiftId, 'is_day_off' => false,
    ]);

    $session = InspectionSession::create([
        'department_id' => $dept->id, 'inspection_date' => now()->toDateString(),
        'shift' => 'afternoon', 'inspector_id' => $inspector->id,
        'status' => 'in_progress', 'type' => 'personnel', 'round' => 1,
    ]);

    expect($session->getTargetEmployees()->count())->toBe(0);

    (require base_path('database/migrations/2026_09_03_000000_backfill_shift_type_on_shifts.php'))->up();

    expect($session->getTargetEmployees()->count())->toBe(1);
});

it('stamps a type on shifts saved through the admin form without one', function () {
    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 5,
    ]);

    $this->actingAs($admin)->post(route('shifts.store'), [
        'shift_name' => 'กะโอที 17.00-02.00',
        'start_time' => '17:00',
        'end_time' => '02:00',
    ])->assertRedirect(route('shifts.index'));

    expect(Shift::where('shift_name', 'กะโอที 17.00-02.00')->first()->shift_type)->toBe('กะบ่าย');
});
