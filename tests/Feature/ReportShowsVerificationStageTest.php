<?php

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Two ladders, and the daily report only ever showed one.
 *
 * A round runs draft → in_progress → completed, which is about it being
 * walked. Sign-off runs pending → reclean → awaiting approval → approved,
 * which is about QA looking at the result. "completed" is the first ladder,
 * and the report printed it raw - so a round nobody had verified read
 * "Completed", in English, on a page where that is taken to mean finished and
 * signed off, while the verification page listed the same round under
 * รอทวนสอบ.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-06 10:00:00');

    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->dept->id,
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $this->checkpoint = Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person']);
    $this->seq = 0;
});

afterEach(fn () => Carbon::setTestNow());

function walkedRound($ctx, array $logStatuses, string $sessionStatus = 'completed'): InspectionSession
{
    $ctx->seq++;

    $session = InspectionSession::create([
        'inspector_id' => $ctx->admin->id, 'department_id' => $ctx->dept->id,
        'type' => 'personnel', 'inspection_date' => '2026-10-06',
        'shift' => 'custom_' . $ctx->shift->id, 'round' => $ctx->seq, 'status' => $sessionStatus,
    ]);

    foreach ($logStatuses as $i => $status) {
        $employee = Employee::create([
            'employee_id' => 'E' . $ctx->seq . $i, 'fullname' => 'พนักงาน ' . $ctx->seq . $i,
            'department_id' => $ctx->dept->id, 'shift_id' => $ctx->shift->id,
            'qr_code_hash' => 'h' . $ctx->seq . $i, 'is_active' => true,
        ]);

        InspectionLog::create([
            'session_id' => $session->id, 'employee_id' => $employee->id,
            'checkpoint_id' => $ctx->checkpoint->id, 'result' => 'pass',
            'inspected_at' => now(), 'verification_status' => $status,
        ]);
    }

    return $session->fresh();
}

it('does not call a walked but unverified round finished', function () {
    walkedRound($this, [null, null]);

    $this->actingAs($this->admin)
        ->get(route('reports.daily', ['date' => '2026-10-06']))
        ->assertSuccessful()
        ->assertSee('ตรวจเสร็จแล้ว')
        ->assertSee('รอทวนสอบ')
        ->assertDontSee('Completed');
});

it('never prints a raw English status', function () {
    walkedRound($this, [null], 'in_progress');

    $this->actingAs($this->admin)
        ->get(route('reports.daily', ['date' => '2026-10-06']))
        ->assertSuccessful()
        ->assertSee('กำลังตรวจ')
        ->assertDontSee('In_progress');
});

it('shows a round waiting on a manager as waiting on a manager', function () {
    expect(walkedRound($this, ['verified', 'verified'])->verificationStage()['label'])
        ->toBe('รออนุมัติ');
});

it('shows a round sent back for re-cleaning', function () {
    expect(walkedRound($this, ['verified', 'reclean'])->verificationStage()['label'])
        ->toBe('สั่งแก้ไข');
});

it('shows a round fully signed off', function () {
    expect(walkedRound($this, ['approved', 'auto_verified'])->verificationStage()['label'])
        ->toBe('อนุมัติแล้ว');
});

/**
 * One unverified log is enough to keep the whole round at the front of the
 * queue - that is where the verification page would list it.
 */
it('keeps a part-verified round at รอทวนสอบ', function () {
    expect(walkedRound($this, ['approved', null])->verificationStage()['label'])
        ->toBe('รอทวนสอบ');
});

it('says so when a round has no results at all', function () {
    expect(walkedRound($this, [])->verificationStage()['label'])->toBe('ยังไม่มีผลตรวจ');
});
