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
 * A room's floor and wall checks belong to the room, not to whoever happened
 * to open the round, so the form widens its search for them past the sessions
 * it was given. That is right - but it was also creating rooms outright, so
 * exporting one personnel round printed every area inspected anywhere that
 * day: seven rooms that round never visited, on a sheet headed with its shift
 * and signed by its inspector.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-06 10:00:00');

    $this->dept = Department::create([
        'dept_name' => 'Production (ห้องแคะ)', 'dept_code' => 'PDK', 'visibility_type' => 'isolated',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->dept->id,
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 07.00-16.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '07:00:00', 'end_time' => '16:00:00',
    ]);

    $this->areaCheck = Checkpoint::create(['title' => 'ความสะอาด', 'is_active' => true, 'type' => 'area']);
    $this->personCheck = Checkpoint::create(['title' => 'แต่งกาย', 'is_active' => true, 'type' => 'person']);

    // The personnel round somebody exports.
    $this->personnel = InspectionSession::create([
        'inspector_id' => $this->admin->id, 'department_id' => $this->dept->id,
        'type' => 'personnel', 'inspection_date' => '2026-10-06',
        'shift' => 'custom_' . $this->shift->id, 'round' => 1, 'status' => 'completed',
    ]);

    $employee = Employee::create([
        'employee_id' => 'E1', 'fullname' => 'พนักงาน หนึ่ง',
        'department_id' => $this->dept->id, 'shift_id' => $this->shift->id,
        'qr_code_hash' => 'h1', 'is_active' => true,
    ]);

    InspectionLog::create([
        'session_id' => $this->personnel->id, 'employee_id' => $employee->id,
        'checkpoint_id' => $this->personCheck->id, 'result' => 'pass', 'inspected_at' => now(),
    ]);

    // A separate area round the same day, in rooms the personnel round never
    // went near.
    $this->areaSession = InspectionSession::create([
        'inspector_id' => $this->admin->id, 'department_id' => $this->dept->id,
        'type' => 'machine', 'inspection_date' => '2026-10-06',
        'shift' => 'morning', 'round' => 1, 'status' => 'completed',
    ]);

    $this->elsewhere = Location::create(['location_name' => 'ห้อง เจาะมะพร้าว']);

    InspectionLog::create([
        'session_id' => $this->areaSession->id, 'location_id' => $this->elsewhere->id,
        'checkpoint_id' => $this->areaCheck->id, 'result' => 'fail', 'inspected_at' => now(),
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function exportedRows($ctx, array $params): array
{
    $captured = [];
    View::composer('reports.pdf.daily', function ($view) use (&$captured) {
        $captured = $view->getData();
    });

    $ctx->actingAs($ctx->admin)->get(route('reports.export.pdf', $params + [
        'date' => '2026-10-06', 'report_type' => 'all',
    ]))->assertSuccessful();

    return $captured;
}

it('does not print areas the selected round never visited', function () {
    $data = exportedRows($this, ['session_ids' => (string) $this->personnel->id]);

    expect($data['areaMachineChunks'])->toBeEmpty();
});

it('still prints the personnel rows of the round that was selected', function () {
    $data = exportedRows($this, ['session_ids' => (string) $this->personnel->id]);

    expect($data['employeeChunks'])->not->toBeEmpty();
});

it('prints the area round when that is the one selected', function () {
    $data = exportedRows($this, ['session_ids' => (string) $this->areaSession->id]);

    expect($data['areaMachineChunks'])->not->toBeEmpty();
});

/**
 * The widening still has to do its job: a machine round that never recorded
 * the room's own floor check must still show it, because somebody else did.
 */
it('still fills in a room check recorded by a different round', function () {
    $machine = Machine::create([
        'location_id' => $this->elsewhere->id, 'name' => 'หัวเจาะ', 'is_active' => true,
    ]);

    $machineOnly = InspectionSession::create([
        'inspector_id' => $this->admin->id, 'department_id' => $this->dept->id,
        'type' => 'machine', 'inspection_date' => '2026-10-06',
        'shift' => 'morning', 'round' => 2, 'status' => 'completed',
    ]);

    InspectionLog::create([
        'session_id' => $machineOnly->id, 'machine_id' => $machine->id,
        'location_id' => $this->elsewhere->id,
        'checkpoint_id' => $this->areaCheck->id, 'result' => 'pass', 'inspected_at' => now(),
    ]);

    $data = exportedRows($this, ['session_ids' => (string) $machineOnly->id]);

    $rows = collect($data['areaMachineChunks'])->flatten(1);
    $room = $rows->firstWhere('type', 'area');

    // The room is on the form, carrying the check the other round recorded.
    expect($room)->not->toBeNull()
        ->and($room['results'])->not->toBeEmpty();
});

it('prints everything when no round is singled out', function () {
    $data = exportedRows($this, []);

    expect($data['employeeChunks'])->not->toBeEmpty()
        ->and($data['areaMachineChunks'])->not->toBeEmpty();
});
