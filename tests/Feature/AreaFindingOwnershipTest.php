<?php

use App\Models\Checkpoint;
use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * An employee has a department; an area never did. So a finding raised in a
 * room had nothing in the data to say whose room it was, and fell back to the
 * session's department - which, for an area round, startSession() takes from
 * the inspector when none is chosen:
 *
 *     $deptId = Auth::user()->department_id ?? Department::first()?->id;
 *
 * That is always QA. Every finding in a Production room therefore became QA's
 * to repair: the department that inspects became the department that fixes, QA
 * ended up checking its own work, and the people who run the room were never
 * told there was anything wrong in it.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-06 10:00:00');

    $this->production = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->productionHead = User::create([
        'name' => 'Production Head', 'email' => 'pd@example.com', 'password' => bcrypt('x'),
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->production->id,
    ]);
    $this->qaInspector = User::create([
        'name' => 'QA Inspector', 'email' => 'qa@example.com', 'password' => bcrypt('x'),
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->qa->id,
    ]);

    // The bottle filling room: QA walks it, Production runs it.
    $this->room = Location::create([
        'location_name' => 'ห้อง บรรจุน้ำขวด',
        'department_id' => $this->production->id,
    ]);
    $this->capper = Machine::create([
        'location_id' => $this->room->id, 'name' => 'Capper หัวที่ 3', 'is_active' => true,
    ]);

    // Stamped QA, because QA walked it.
    $this->session = InspectionSession::create([
        'inspector_id' => $this->qaInspector->id, 'department_id' => $this->qa->id,
        'type' => 'machine', 'inspection_date' => '2026-10-06',
        'shift' => 'morning', 'round' => 1, 'status' => 'completed',
    ]);

    $this->checkpoint = Checkpoint::create(['title' => 'ความสะอาด', 'is_active' => true, 'type' => 'area']);
});

afterEach(fn () => Carbon::setTestNow());

function areaLog($ctx, array $attributes = []): InspectionLog
{
    return InspectionLog::create(array_merge([
        'session_id' => $ctx->session->id,
        'checkpoint_id' => $ctx->checkpoint->id,
        'location_id' => $ctx->room->id,
        'result' => 'fail',
        'inspected_at' => now(),
    ], $attributes));
}

it('gives a room finding to the department that runs the room', function () {
    expect(areaLog($this)->owningDepartmentId())->toBe($this->production->id);
});

it('gives a machine finding to the department that runs the room it stands in', function () {
    $log = areaLog($this, ['location_id' => null, 'machine_id' => $this->capper->id]);

    expect($log->owningDepartmentId())->toBe($this->production->id);
});

/**
 * Nothing changes for a room nobody has assigned yet, so the rooms can be
 * filled in at whatever pace suits.
 */
it('falls back to the round department for a room with no owner', function () {
    $this->room->update(['department_id' => null]);

    expect(areaLog($this)->owningDepartmentId())->toBe($this->qa->id);
});

it('still gives a personnel finding to the employee department', function () {
    $shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $employee = Employee::create([
        'employee_id' => 'E1', 'fullname' => 'พนักงาน หนึ่ง',
        'department_id' => $this->production->id, 'shift_id' => $shift->id,
        'qr_code_hash' => 'h1', 'is_active' => true,
    ]);

    $log = areaLog($this, ['location_id' => null, 'employee_id' => $employee->id]);

    expect($log->owningDepartmentId())->toBe($this->production->id);
});

/**
 * The consequence that matters: the room's finding is handed to Production,
 * and Production can see it.
 */
it('lets the department that runs the room take the finding', function () {
    $log = areaLog($this);
    $action = CorrectiveAction::factory()->create([
        'inspection_log_id' => $log->id, 'escalated_by' => $this->qaInspector->id, 'status' => 'open',
    ]);

    $this->actingAs($this->qaInspector)->post(route('corrective.assign'), [
        'action_id' => $action->id,
        'assigned_to' => $this->productionHead->id,
        'due_date' => '2026-10-07',
    ])->assertSessionHas('success');

    expect($action->refresh()->assigned_to)->toBe($this->productionHead->id);
});

it('shows the room finding to the department that runs the room', function () {
    $log = areaLog($this);
    $action = CorrectiveAction::factory()->create([
        'inspection_log_id' => $log->id, 'escalated_by' => $this->qaInspector->id, 'status' => 'open',
    ]);

    $visible = $this->actingAs($this->productionHead)
        ->get(route('corrective.index'))
        ->assertSuccessful()
        ->viewData('openActions')
        ->pluck('id');

    expect($visible)->toContain($action->id);
});

/**
 * And they are told, which they never were: the announcement went to the
 * department on the session, and that is the one that walked the round.
 */
it('tells the department that runs the room, not the one that walked the round', function () {
    Notification::fake();

    $log = areaLog($this);
    CorrectiveAction::factory()->create([
        'inspection_log_id' => $log->id, 'escalated_by' => $this->qaInspector->id, 'status' => 'open',
    ]);

    app(App\Services\InspectionService::class)->notifyManagersOfSessionCars($this->session);

    Notification::assertSentTo(
        $this->productionHead,
        App\Notifications\SessionCarsSummaryNotification::class
    );
    Notification::assertNotSentTo(
        $this->qaInspector,
        App\Notifications\SessionCarsSummaryNotification::class
    );
});
