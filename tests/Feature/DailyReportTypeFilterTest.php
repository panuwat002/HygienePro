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
use Illuminate\Support\Facades\View;

/**
 * The daily report's ประเภท dropdown filters the LIST by session type, and the
 * PDF export never did. Asking for เครื่องจักร/พื้นที่ and pressing Export PDF
 * pulled in every personnel round of that date as well:
 *
 *   report_type=machine   list: 136        export: 133, 134, 135, 136
 *
 * The employee rows were dropped log by log, so the table looked right - but
 * $sessions was wrong, and _signatures.blade.php takes the ผู้บันทึก box from
 * $sessions->first()->inspector. An area form came out signed by whoever
 * walked the Production personnel round, dated from that round.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-25 14:00:00');

    $this->production = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 9,
        'department_id' => $this->qa->id,
    ]);
    $this->personnelInspector = User::create([
        'name' => 'Personnel Round Inspector', 'email' => 'person@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->qa->id,
    ]);
    $this->areaInspector = User::create([
        'name' => 'Area Round Inspector', 'email' => 'area@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->qa->id,
    ]);

    $shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $personCheckpoint = Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person']);
    $areaCheckpoint = Checkpoint::create(['title' => 'ความสะอาดของพื้นที่', 'is_active' => true, 'type' => 'area']);

    // A personnel round, walked by one person.
    $this->personnelSession = InspectionSession::create([
        'inspector_id' => $this->personnelInspector->id, 'department_id' => $this->production->id,
        'type' => 'personnel', 'inspection_date' => '2026-09-25',
        'shift' => 'custom_' . $shift->id, 'round' => 1, 'status' => 'completed',
    ]);

    $employee = Employee::create([
        'employee_id' => 'E1', 'fullname' => 'พนักงาน หนึ่ง',
        'department_id' => $this->production->id, 'shift_id' => $shift->id,
        'qr_code_hash' => 'h1', 'is_active' => true,
    ]);

    InspectionLog::create([
        'session_id' => $this->personnelSession->id, 'checkpoint_id' => $personCheckpoint->id,
        'employee_id' => $employee->id, 'result' => 'pass', 'inspected_at' => now(),
    ]);

    // A machine/area round the same day, walked by somebody else.
    $this->machineSession = InspectionSession::create([
        'inspector_id' => $this->areaInspector->id, 'department_id' => $this->qa->id,
        'type' => 'machine', 'inspection_date' => '2026-09-25',
        'shift' => 'morning', 'round' => 1, 'status' => 'completed',
    ]);

    $location = Location::create(['location_name' => 'ห้อง HPP']);
    $machine = Machine::create(['location_id' => $location->id, 'name' => 'เครื่องต้ม', 'is_active' => true]);

    InspectionLog::create([
        'session_id' => $this->machineSession->id, 'checkpoint_id' => $areaCheckpoint->id,
        'machine_id' => $machine->id, 'location_id' => $location->id,
        'result' => 'pass', 'inspected_at' => now(),
    ]);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Captures what the PDF view is actually handed, which is the only place the
 * difference shows - the rendered PDF drops the wrong-type rows on its own.
 */
function exportedPdfData($test, array $params): array
{
    $captured = [];

    View::composer('reports.pdf.daily', function ($view) use (&$captured) {
        $captured = $view->getData();
    });

    $test->actingAs($test->admin)
        ->get(route('reports.export.pdf', $params + ['date' => '2026-09-25']))
        ->assertSuccessful();

    return $captured;
}

it('exports only the machine round when the machine bucket is asked for', function () {
    $data = exportedPdfData($this, ['report_type' => 'machine']);

    expect($data['sessions']->pluck('id')->all())->toBe([$this->machineSession->id]);
});

it('exports only the personnel rounds when the person bucket is asked for', function () {
    $data = exportedPdfData($this, ['report_type' => 'person']);

    expect($data['sessions']->pluck('id')->all())->toBe([$this->personnelSession->id]);
});

it('exports everything when no type is picked', function () {
    $data = exportedPdfData($this, ['report_type' => 'all']);

    expect($data['sessions']->pluck('id')->sort()->values()->all())
        ->toBe([$this->personnelSession->id, $this->machineSession->id]);
});

/**
 * The consequence that reaches paper.
 */
it('signs the area form with the person who walked the area round', function () {
    $data = exportedPdfData($this, ['report_type' => 'machine']);

    expect($data['sessions']->first()->inspector->name)->toBe('Area Round Inspector');
});

/**
 * ?report_type=area is a legacy alias kept for old saved URLs and bookmarks.
 */
it('treats the legacy area alias the same as the merged bucket', function () {
    $data = exportedPdfData($this, ['report_type' => 'area']);

    expect($data['sessions']->pluck('id')->all())->toBe([$this->machineSession->id]);
});

it('keeps the list page and the export agreeing on which rounds are in scope', function () {
    foreach (['all', 'person', 'machine'] as $type) {
        $listed = $this->actingAs($this->admin)
            ->get(route('reports.daily', ['date' => '2026-09-25', 'report_type' => $type]))
            ->assertSuccessful()
            ->viewData('sessions')
            ->pluck('id')->sort()->values()->all();

        $exported = exportedPdfData($this, ['report_type' => $type])['sessions']
            ->pluck('id')->sort()->values()->all();

        expect($exported)->toBe($listed, "report_type={$type}");
    }
});
