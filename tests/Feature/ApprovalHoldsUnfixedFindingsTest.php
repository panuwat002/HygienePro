<?php

use App\Models\Checkpoint;
use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * A verification card holds a whole round - ten people who passed and the one
 * who did not, or a room's twenty-six clean machines and its dirty floor - and
 * approval ran over every log in it.
 *
 * So one press signed off a finding nobody had touched. Worse: the session
 * locks once every log in it is approved, and a locked session refuses new
 * logs, so the re-clean could then never be recorded against the round it
 * belonged to. The corrective action stayed open with nothing able to close
 * the loop.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-06 10:00:00');

    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->inspector = User::create([
        'name' => 'Inspector', 'email' => 'ins@example.com', 'password' => bcrypt('x'),
        'role' => 'staff', 'level' => 2, 'department_id' => $this->qa->id,
    ]);
    $this->manager = User::create([
        'name' => 'QA Manager', 'email' => 'mgr@example.com', 'password' => bcrypt('x'),
        'role' => 'manager', 'level' => 5, 'department_id' => $this->qa->id,
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $this->checkpoint = Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person']);

    $this->session = InspectionSession::create([
        'inspector_id' => $this->inspector->id, 'department_id' => $this->dept->id,
        'type' => 'personnel', 'inspection_date' => '2026-10-06',
        'shift' => 'custom_' . $this->shift->id, 'round' => 1, 'status' => 'completed',
    ]);

    $this->seq = 0;
});

afterEach(fn () => Carbon::setTestNow());

/**
 * One inspected person. A failure gets the corrective action the real flow
 * opens for it automatically.
 */
function personLog($ctx, string $result, ?string $carStatus = 'open'): InspectionLog
{
    $ctx->seq++;

    $employee = Employee::create([
        'employee_id' => 'E' . $ctx->seq, 'fullname' => 'พนักงาน ' . $ctx->seq,
        'department_id' => $ctx->dept->id, 'shift_id' => $ctx->shift->id,
        'qr_code_hash' => 'h' . $ctx->seq, 'is_active' => true,
    ]);

    $log = InspectionLog::create([
        'session_id' => $ctx->session->id, 'employee_id' => $employee->id,
        'checkpoint_id' => $ctx->checkpoint->id, 'result' => $result,
        'inspected_at' => now(), 'verification_status' => 'verified',
        'verified_at' => now(), 'verifier_id' => $ctx->inspector->id,
    ]);

    if ($result === 'fail' && $carStatus) {
        CorrectiveAction::factory()->create([
            'inspection_log_id' => $log->id,
            'escalated_by' => $ctx->inspector->id,
            'status' => $carStatus,
            'action_taken' => in_array($carStatus, ['resolved', 'verified', 'closed'], true)
                ? 'ทำความสะอาดใหม่แล้ว'
                : null,
        ]);
    }

    return $log;
}

function approveAll($ctx, $logs)
{
    return $ctx->actingAs($ctx->manager)->post(route('inspection.approve'), [
        'ids' => collect($logs)->pluck('id')->all(),
    ]);
}

it('approves the results that passed', function () {
    $passed = [personLog($this, 'pass'), personLog($this, 'pass')];
    $failed = personLog($this, 'fail');

    approveAll($this, array_merge($passed, [$failed]));

    foreach ($passed as $log) {
        expect($log->fresh()->verification_status)->toBe('approved');
    }
});

it('holds back a failure nobody has fixed', function () {
    $failed = personLog($this, 'fail');
    personLog($this, 'pass');

    approveAll($this, [$failed, ...[]]);

    expect($failed->fresh()->verification_status)->toBe('verified');
});

it('says how many it held back and why', function () {
    $logs = [personLog($this, 'pass'), personLog($this, 'fail')];

    approveAll($this, $logs)->assertSessionHas('warning');
});

/**
 * The damage the hold-back prevents. A locked session refuses new logs, so
 * approving the whole card made the re-clean unrecordable against the round it
 * belonged to.
 */
it('does not lock the round while a finding is still open', function () {
    $logs = [personLog($this, 'pass'), personLog($this, 'fail')];

    approveAll($this, $logs);

    expect($this->session->fresh()->is_locked)->toBeFalsy();
});

it('approves a failure once its corrective action is resolved', function () {
    $failed = personLog($this, 'fail', carStatus: 'resolved');

    approveAll($this, [$failed]);

    expect($failed->fresh()->verification_status)->toBe('approved');
});

it('approves a failure whose corrective action is already closed', function () {
    $failed = personLog($this, 'fail', carStatus: 'closed');

    approveAll($this, [$failed]);

    expect($failed->fresh()->verification_status)->toBe('approved');
});

it('holds back a failure that was only assigned, not fixed', function () {
    $failed = personLog($this, 'fail', carStatus: 'assigned');

    approveAll($this, [$failed]);

    expect($failed->fresh()->verification_status)->toBe('verified');
});

it('refuses outright when nothing in the selection can be approved', function () {
    $failed = personLog($this, 'fail');

    approveAll($this, [$failed])->assertSessionHas('error');

    expect($failed->fresh()->verification_status)->toBe('verified');
});

/**
 * Once the finding is dealt with the round finishes normally - including the
 * lock, which is what says the record is closed.
 */
it('locks the round once the finding is fixed and approved too', function () {
    $passed = personLog($this, 'pass');
    $failed = personLog($this, 'fail');

    approveAll($this, [$passed, $failed]);
    expect($this->session->fresh()->is_locked)->toBeFalsy();

    $failed->correctiveAction->update(['status' => 'resolved', 'action_taken' => 'ทำความสะอาดใหม่']);
    approveAll($this, [$failed]);

    expect($failed->fresh()->verification_status)->toBe('approved')
        ->and($this->session->fresh()->is_locked)->toBeTruthy();
});
