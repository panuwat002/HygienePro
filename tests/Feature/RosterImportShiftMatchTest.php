<?php

use App\Imports\RosterImport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use Illuminate\Support\Collection;

/**
 * RosterImport matches a roster cell against shift names with str_contains and takes the
 * first hit. Shift names all share the "<type> HH.mm-HH.mm" shape, so one cell can be a
 * substring of several names and the winner is decided by row order, not by intent.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);

    // Created in the order ShiftSeeder writes them: ascending start_time.
    $this->morning = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => 'กะเช้า',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);
    $this->afternoon = Shift::create([
        'shift_name' => 'กะบ่าย 17.00-02.00', 'shift_type' => 'กะบ่าย',
        'start_time' => '17:00:00', 'end_time' => '02:00:00',
    ]);

    $this->employee = Employee::create([
        'employee_id' => 'E001', 'fullname' => 'Worker One',
        'department_id' => $this->dept->id, 'qr_code_hash' => hash('sha256', 'E001'),
        'is_active' => true,
    ]);
});

function importRoster($ctx, string $cell): ?EmployeeSchedule
{
    // Columns 0..3 are id/name/etc; day cells start at index 4.
    $row = ['E001', 'Worker One', '', '', $cell, null, null, null, null, null, null];

    (new RosterImport($ctx->dept->id, '2026-09-07'))->collection(new Collection([$row]));

    return EmployeeSchedule::where('employee_id', $ctx->employee->id)
        ->whereDate('date', '2026-09-07')->first();
}

it('matches the full time range the roster actually uses', function () {
    $schedule = importRoster($this, '17.00-02.00');

    expect($schedule)->not->toBeNull()
        ->and($schedule->shift_id)->toBe($this->afternoon->id);
});

it('accepts the same range written with colons and spaces', function () {
    $schedule = importRoster($this, '17:00 - 02:00');

    expect($schedule->shift_id)->toBe($this->afternoon->id);
});

it('matches a cell that spells the shift name out in full', function () {
    $schedule = importRoster($this, 'กะบ่าย 17.00-02.00');

    expect($schedule->shift_id)->toBe($this->afternoon->id);
});

it('refuses a start time that sits inside another shift name', function () {
    // "17.00" is inside both "กะเช้า 08.00-17.00" (its end) and "กะบ่าย 17.00-02.00" (its
    // start). The import used to take whichever came first and roster the employee onto the
    // morning shift, silently.
    expect(fn () => importRoster($this, '17.00'))
        ->toThrow(Exception::class, 'ตรงกับหลายกะในระบบ');

    expect(EmployeeSchedule::count())->toBe(0);
});

it('refuses a cell that names only the shift type', function () {
    Shift::create([
        'shift_name' => 'กะเช้า 10.00-19.00', 'shift_type' => 'กะเช้า',
        'start_time' => '10:00:00', 'end_time' => '19:00:00',
    ]);

    expect(fn () => importRoster($this, 'กะเช้า'))
        ->toThrow(Exception::class, 'ตรงกับหลายกะในระบบ');
});

it('still names the shift when only one candidate matches loosely', function () {
    // Nothing ambiguous here, so the lenient path stays usable.
    $schedule = importRoster($this, 'กะบ่าย');

    expect($schedule->shift_id)->toBe($this->afternoon->id);
});

it('still reports a range that no shift covers', function () {
    expect(fn () => importRoster($this, '23.00-08.00'))
        ->toThrow(Exception::class, 'ไม่พบกะเวลา');
});
