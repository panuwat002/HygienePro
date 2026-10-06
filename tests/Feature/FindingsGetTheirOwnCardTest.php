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
 * A round arrived as one card holding everything in it - ten people who passed
 * and the one who did not - and the tab was decided by a single status for the
 * lot, where an unverified log outranked a re-clean. So a round with a finding
 * sat in รอทวนสอบ while สั่งแก้ไข read 0, even though every one of those
 * failures already had a corrective action opened against it automatically.
 *
 * Two representations of the same fact, set by different triggers, disagreeing
 * on screen. Findings are now a card of their own, in the tab that means
 * "needs fixing", from the moment they are recorded.
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
    $this->supervisor = User::create([
        'name' => 'QA Supervisor', 'email' => 'sup@example.com', 'password' => bcrypt('x'),
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->qa->id,
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

function inspected($ctx, string $result, ?string $status = null): InspectionLog
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
        'inspected_at' => now()->subSeconds(100 - $ctx->seq),
        'verification_status' => $status,
        'verified_at' => $status ? now() : null,
    ]);

    if ($result === 'fail') {
        CorrectiveAction::factory()->create([
            'inspection_log_id' => $log->id,
            'escalated_by' => $ctx->inspector->id,
            'status' => 'open',
        ]);
    }

    return $log;
}

function tab($ctx, string $tab)
{
    return $ctx->actingAs($ctx->supervisor)
        ->get("/verification?date=&filter_type=person&tab={$tab}")
        ->assertSuccessful();
}

it('puts a fresh finding straight into สั่งแก้ไข', function () {
    inspected($this, 'pass');
    inspected($this, 'fail');

    expect(tab($this, 'reclean')->viewData('counts')['reclean'])->toBe(1);
});

it('leaves the clean results of the same round waiting for verification', function () {
    inspected($this, 'pass');
    inspected($this, 'fail');

    expect(tab($this, 'pending')->viewData('counts')['pending'])->toBe(1);
});

/**
 * The symptom that started this: a round full of findings, and the tab that
 * means "needs fixing" reading zero.
 */
it('no longer reads zero while findings are standing', function () {
    inspected($this, 'fail');
    inspected($this, 'fail');

    expect(tab($this, 'reclean')->viewData('counts')['reclean'])->toBe(1)
        ->and(tab($this, 'pending')->viewData('counts')['pending'])->toBe(0);
});

it('makes no second card when nothing failed', function () {
    inspected($this, 'pass');
    inspected($this, 'pass');

    expect(tab($this, 'reclean')->viewData('counts')['reclean'])->toBe(0)
        ->and(tab($this, 'pending')->viewData('counts')['pending'])->toBe(1);
});

/**
 * A finding card carries only findings, so what a supervisor acts on is what
 * they can see - and approving it can no longer take a clean result with it.
 */
it('carries only the failures in the finding card', function () {
    inspected($this, 'pass');
    inspected($this, 'pass');
    inspected($this, 'fail');

    $group = tab($this, 'reclean')->viewData('groupedInspections')->first();

    expect($group->all_logs)->toHaveCount(1)
        ->and(collect($group->all_logs)->every(fn ($log) => $log->result === 'fail'))->toBeTrue();
});

/**
 * Once a failure is approved it is no longer outstanding, so it stops being a
 * finding and the card empties itself rather than lingering.
 */
it('empties the finding card as failures are signed off', function () {
    inspected($this, 'fail', 'approved');
    inspected($this, 'pass', 'approved');

    expect(tab($this, 'reclean')->viewData('counts')['reclean'])->toBe(0);
});

/**
 * Work sent back because the record itself is wrong is a different problem
 * from something needing re-cleaning, so the split must not quietly reclassify
 * it as one.
 *
 * It does not turn up in รอทวนสอบ either, but that is the separate known gap
 * already recorded in VerificationPageBehaviourTest - rejected work is invisible
 * in the inbox - and is not what this change was about.
 */
it('does not reclassify rejected work as something to re-clean', function () {
    inspected($this, 'fail', 'rejected');

    expect(tab($this, 'reclean')->viewData('counts')['reclean'])->toBe(0);
});
