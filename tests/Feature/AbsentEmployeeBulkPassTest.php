<?php

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * "ผ่านทั้งหมด" (bulkPassRemaining) wrote result = 'pass' for every target employee who
 * had no log yet, which conflated three different situations into one value: inspected
 * and clean, did not come to work, and not inspected in time. Employees on a full-day
 * leave were therefore recorded as having passed a hygiene inspection they were never
 * present for, inflating the shift's score and the FM-QA-22 record.
 *
 * The inspector is the only one who knows which is which, so the bulk action now takes
 * the list of people who did not come to work and records them as 'absent'.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'global',
    ]);

    // The inspector belongs to QA and inspects other departments' staff, which is what
    // Gate 'inspect' -> User::canInspect() requires (isQA() plus a supervisor/manager
    // grade). The employees being inspected sit in Production.
    $this->qaDept = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->inspector = User::factory()->create([
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->qaDept->id,
    ]);

    $this->checkpoint = Checkpoint::create([
        'title' => 'เครื่องแบบสะอาด', 'is_active' => true, 'type' => 'person',
    ]);

    $this->present = Employee::factory()->create([
        'fullname' => 'Present Person', 'department_id' => $this->dept->id, 'is_active' => true,
    ]);
    $this->onLeaveA = Employee::factory()->create([
        'fullname' => 'On Leave A', 'department_id' => $this->dept->id, 'is_active' => true,
    ]);
    $this->onLeaveB = Employee::factory()->create([
        'fullname' => 'On Leave B', 'department_id' => $this->dept->id, 'is_active' => true,
    ]);

    $this->session = InspectionSession::factory()->create([
        'department_id' => $this->dept->id,
        'inspector_id' => $this->inspector->id,
        'shift' => '',
        'type' => 'personnel',
        'status' => 'in_progress',
        'is_sampling' => false,
        'inspection_date' => now(),
    ]);
});

function resultFor(InspectionSession $session, Employee $employee): ?string
{
    return InspectionLog::where('session_id', $session->id)
        ->where('employee_id', $employee->id)
        ->value('result');
}

test('employees marked as not at work are recorded absent, not passed', function () {
    $service = app(\App\Services\InspectionService::class);

    $service->bulkPassRemaining($this->session, [
        $this->onLeaveA->id,
        $this->onLeaveB->id,
    ]);

    expect(resultFor($this->session, $this->onLeaveA))->toBe('absent')
        ->and(resultFor($this->session, $this->onLeaveB))->toBe('absent');
});

test('employees not marked absent are still passed', function () {
    $service = app(\App\Services\InspectionService::class);

    $service->bulkPassRemaining($this->session, [$this->onLeaveA->id]);

    expect(resultFor($this->session, $this->present))->toBe('pass');
});

test('calling it with no absent list passes everyone, as before', function () {
    $service = app(\App\Services\InspectionService::class);

    $service->bulkPassRemaining($this->session);

    expect(resultFor($this->session, $this->present))->toBe('pass')
        ->and(resultFor($this->session, $this->onLeaveA))->toBe('pass');
});

test('an absent id that was already inspected is left alone', function () {
    // The inspector inspected this person earlier in the shift; a stale checkbox must
    // not overwrite a real inspection result.
    InspectionLog::factory()->create([
        'session_id' => $this->session->id,
        'employee_id' => $this->present->id,
        'checkpoint_id' => $this->checkpoint->id,
        'result' => 'fail',
    ]);

    $service = app(\App\Services\InspectionService::class);
    $service->bulkPassRemaining($this->session, [$this->present->id]);

    expect(resultFor($this->session, $this->present))->toBe('fail');
});

test('the endpoint passes the absent list through', function () {
    $this->actingAs($this->inspector)
        ->post(route('inspection.bulk-pass', $this->session), [
            'absent_employee_ids' => [$this->onLeaveA->id],
        ]);

    expect(resultFor($this->session, $this->onLeaveA))->toBe('absent')
        ->and(resultFor($this->session, $this->present))->toBe('pass');
});

test('absence belongs to the round, so the next round targets them again', function () {
    $service = app(\App\Services\InspectionService::class);
    $service->bulkPassRemaining($this->session, [$this->onLeaveA->id]);

    $tomorrow = InspectionSession::factory()->create([
        'department_id' => $this->dept->id,
        'inspector_id' => $this->inspector->id,
        'shift' => '',
        'type' => 'personnel',
        'status' => 'in_progress',
        'inspection_date' => now()->addDay(),
    ]);

    $loggedTomorrow = InspectionLog::where('session_id', $tomorrow->id)
        ->where('employee_id', $this->onLeaveA->id)
        ->exists();

    expect($loggedTomorrow)->toBeFalse();
});

test('a qa staff who is the session inspector can bulk-pass remaining employees', function () {
    $qaStaff = User::factory()->create([
        'role' => 'staff',
        'level' => 2,
        'department_id' => $this->qaDept->id,
    ]);

    $staffSession = InspectionSession::factory()->create([
        'department_id' => $this->dept->id,
        'inspector_id' => $qaStaff->id,
        'shift' => '',
        'type' => 'personnel',
        'status' => 'in_progress',
        'inspection_date' => now(),
    ]);

    $response = $this->actingAs($qaStaff)
        ->postJson(route('inspection.bulk-pass', $staffSession), [
            'absent_employee_ids' => [$this->onLeaveA->id],
        ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect(resultFor($staffSession, $this->onLeaveA))->toBe('absent')
        ->and(resultFor($staffSession, $this->present))->toBe('pass');
});

test('a qa supervisor can access and bulk-pass a session owned by another qa staff', function () {
    $qaStaff = User::factory()->create([
        'role' => 'staff',
        'level' => 2,
        'department_id' => $this->qaDept->id,
    ]);

    $staffSession = InspectionSession::factory()->create([
        'department_id' => $this->dept->id,
        'inspector_id' => $qaStaff->id,
        'shift' => '',
        'type' => 'personnel',
        'status' => 'in_progress',
        'inspection_date' => now(),
    ]);

    // Supervisor (meow) helps staff (kade) by calling bulk-pass
    $response = $this->actingAs($this->inspector)
        ->postJson(route('inspection.bulk-pass', $staffSession), [
            'absent_employee_ids' => [$this->onLeaveB->id],
        ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect(resultFor($staffSession, $this->onLeaveB))->toBe('absent')
        ->and(resultFor($staffSession, $this->present))->toBe('pass');
});

test('a non-supervisor non-owner qa staff cannot access another inspectors session', function () {
    $qaStaffA = User::factory()->create([
        'role' => 'staff',
        'level' => 2,
        'department_id' => $this->qaDept->id,
    ]);

    $qaStaffB = User::factory()->create([
        'role' => 'staff',
        'level' => 2,
        'department_id' => $this->qaDept->id,
    ]);

    $sessionA = InspectionSession::factory()->create([
        'department_id' => $this->dept->id,
        'inspector_id' => $qaStaffA->id,
        'shift' => '',
        'type' => 'personnel',
        'status' => 'in_progress',
        'inspection_date' => now(),
    ]);

    $response = $this->actingAs($qaStaffB)
        ->postJson(route('inspection.bulk-pass', $sessionA), [
            'absent_employee_ids' => [],
        ]);

    $response->assertStatus(403);
});

