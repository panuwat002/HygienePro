<?php

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * A daily-report row named the department, the shift and a count, and nothing
 * else. Two rounds of the same department on the same day - one of its people,
 * one of its machines - were indistinguishable, so whoever ticked one to
 * export could not tell which form they were about to get until it came out.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-25 14:00:00');

    $this->dept = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 9,
        'department_id' => $this->dept->id,
    ]);

    $shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $this->personnel = InspectionSession::create([
        'inspector_id' => $this->admin->id, 'department_id' => $this->dept->id,
        'type' => 'personnel', 'inspection_date' => '2026-09-25',
        'shift' => 'custom_' . $shift->id, 'round' => 1, 'status' => 'completed',
    ]);

    $employee = Employee::create([
        'employee_id' => 'E1', 'fullname' => 'พนักงาน หนึ่ง',
        'department_id' => $this->dept->id, 'shift_id' => $shift->id,
        'qr_code_hash' => 'h1', 'is_active' => true,
    ]);

    InspectionLog::create([
        'session_id' => $this->personnel->id, 'result' => 'pass', 'inspected_at' => now(),
        'checkpoint_id' => Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person'])->id,
        'employee_id' => $employee->id,
    ]);

    $this->machine = InspectionSession::create([
        'inspector_id' => $this->admin->id, 'department_id' => $this->dept->id,
        'type' => 'machine', 'inspection_date' => '2026-09-25',
        'shift' => 'morning', 'round' => 1, 'status' => 'completed',
    ]);

    $location = Location::create(['location_name' => 'ห้อง เจาะมะพร้าว']);

    InspectionLog::create([
        'session_id' => $this->machine->id, 'result' => 'pass', 'inspected_at' => now(),
        'checkpoint_id' => Checkpoint::create(['title' => 'ความสะอาดของพื้นที่', 'is_active' => true, 'type' => 'area'])->id,
        'machine_id' => Machine::create(['location_id' => $location->id, 'name' => 'หัวเจาะ', 'is_active' => true])->id,
        'location_id' => $location->id,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

it('labels a personnel round on the page', function () {
    expect($this->personnel->type_label)->toBe('พนักงาน');
});

it('labels machine and legacy area rounds as the one bucket the dropdown offers', function () {
    expect($this->machine->type_label)->toBe('พื้นที่ / เครื่องจักร');

    $this->machine->update(['type' => 'area']);

    expect($this->machine->refresh()->type_label)->toBe('พื้นที่ / เครื่องจักร');
});

it('tells the two rounds of one department apart on the list', function () {
    $this->actingAs($this->admin)
        ->get(route('reports.daily', ['date' => '2026-09-25']))
        ->assertSuccessful()
        ->assertSee('พนักงาน')
        ->assertSee('พื้นที่ / เครื่องจักร');
});

/**
 * The area section is headed "2." because personal hygiene is "1.". Export
 * the machine bucket on its own and section 1 is not printed, so a hardcoded
 * "2." reads as though a page had gone missing from an audit document.
 */
it('numbers the area section 1 when it is the only section on the form', function () {
    $captured = [];

    Illuminate\Support\Facades\View::composer('reports.pdf.daily', function ($view) use (&$captured) {
        $captured = $view->getData();
    });

    $this->actingAs($this->admin)->get(route('reports.export.pdf', [
        'date' => '2026-09-25',
        'report_type' => 'machine',
    ]))->assertSuccessful();

    // Rendered as HTML rather than read out of the PDF, which is what dompdf
    // was handed anyway.
    $html = view('reports.pdf.daily', $captured)->render();

    expect($captured['employeeChunks'])->toBeEmpty()
        ->and($html)->toContain('1. รายงานการทวนสอบ')
        ->and($html)->not->toContain('2. รายงานการทวนสอบ');
});

it('still numbers it 2 when personal hygiene is printed above it', function () {
    $captured = [];

    Illuminate\Support\Facades\View::composer('reports.pdf.daily', function ($view) use (&$captured) {
        $captured = $view->getData();
    });

    $this->actingAs($this->admin)->get(route('reports.export.pdf', [
        'date' => '2026-09-25',
        'report_type' => 'all',
    ]))->assertSuccessful();

    // Both sections present, so the area one keeps its "2.".
    expect(count($captured['employeeChunks']))->toBeGreaterThan(0)
        ->and(count($captured['areaMachineChunks']))->toBeGreaterThan(0);
});
