<?php

/**
 * Approving a round used to make it disappear.
 *
 * Inbox mode (no date picked) matches three things: work nobody has verified,
 * work sitting at reclean/verified, and finished work *inspected today*. That
 * third clause is the bug. A manager approves a round inspected six weeks ago
 * and it instantly matches none of the three - not awaiting_approval, because
 * it is approved now; not completed, because it was not inspected today. The
 * page they are standing on goes to zero and nothing tells them their 225
 * approvals landed.
 *
 * The loop has to close where the person can see it close.
 */

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-24 14:00:00');

    $this->dept = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $this->checkpoint = Checkpoint::create([
        'title' => 'Hand wash', 'is_active' => true, 'type' => 'person',
    ]);

    $this->manager = User::create([
        'name' => 'Wallika', 'email' => 'visible-manager@example.com',
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => $this->dept->id,
    ]);

    $this->visibleRound = 0;
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * One personnel round, inspected and signed off at whatever moments the test
 * needs. $verifiedAt is when the supervisor verified it; approval is stamped
 * separately in approved_at, and the window reads whichever came last.
 */
function roundClosedAt($ctx, string $status, Carbon $inspectedAt, ?Carbon $verifiedAt = null): InspectionSession
{
    $ctx->visibleRound++;

    $employee = Employee::create([
        'employee_id' => 'V' . $ctx->visibleRound, 'fullname' => 'Worker ' . $ctx->visibleRound,
        'department_id' => $ctx->dept->id, 'shift_id' => $ctx->shift->id,
        'qr_code_hash' => 'vh' . $ctx->visibleRound, 'is_active' => true,
    ]);

    $session = InspectionSession::create([
        'inspector_id' => $ctx->manager->id, 'department_id' => $ctx->dept->id,
        'type' => 'personnel', 'inspection_date' => $inspectedAt->toDateString(),
        'shift' => 'custom_' . $ctx->shift->id, 'round' => $ctx->visibleRound,
        'status' => 'completed',
    ]);

    InspectionLog::create([
        'session_id' => $session->id, 'checkpoint_id' => $ctx->checkpoint->id,
        'employee_id' => $employee->id, 'result' => 'pass', 'inspected_at' => $inspectedAt,
        'verification_status' => $status,
        'verified_at' => $verifiedAt ?? $inspectedAt,
    ]);

    return $session;
}

function completedTab($ctx)
{
    return $ctx->actingAs($ctx->manager)
        ->get('/verification?date=&filter_type=person&tab=completed');
}

it('shows a round the manager just approved, whenever it was inspected', function () {
    // Inspected six weeks ago, approved a minute ago - the 225 they just signed.
    roundClosedAt($this, 'approved',
        Carbon::parse('2026-08-13 09:00:00'),
        Carbon::parse('2026-09-24 13:59:00'));

    $response = completedTab($this)->assertSuccessful();

    expect($response->viewData('counts')['completed'])->toBe(1);
});

/**
 * The same case, driven through the real approve endpoint rather than a
 * hand-written timestamp. Approval is stamped in approved_at now, so if the
 * window only read verified_at the six-week-old round would vanish again the
 * moment it was signed - the exact failure this file exists for.
 */
it('shows a round approved through the endpoint, not only one stamped by hand', function () {
    $session = roundClosedAt($this, 'verified',
        Carbon::parse('2026-08-13 09:00:00'),
        Carbon::parse('2026-08-13 10:00:00'));

    // A manager may not approve a round they inspected themselves, and the
    // helper makes them the inspector - so hand the round to someone else.
    $session->update(['inspector_id' => User::create([
        'name' => 'Inspector', 'email' => 'round-inspector@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->dept->id,
    ])->id]);

    $this->actingAs($this->manager)->post(route('inspection.approve'), [
        'ids' => $session->logs()->pluck('id')->all(),
    ]);

    expect(completedTab($this)->assertSuccessful()->viewData('counts')['completed'])->toBe(1);
});

it('still shows work finished today', function () {
    roundClosedAt($this, 'approved', Carbon::parse('2026-09-24 08:00:00'));

    expect(completedTab($this)->assertSuccessful()->viewData('counts')['completed'])->toBe(1);
});

it('keeps this week of closed work, so yesterday is still there tomorrow', function () {
    roundClosedAt($this, 'approved',
        Carbon::parse('2026-09-01 09:00:00'),
        Carbon::parse('2026-09-22 16:00:00')); // approved two days ago

    expect(completedTab($this)->assertSuccessful()->viewData('counts')['completed'])->toBe(1);
});

/**
 * The bound is what keeps this page fast: the predicate that had no time limit
 * is how it came to load 17,993 rows to render twenty. Closed work ages out of
 * the inbox and is found by picking its date.
 */
it('does not drag every round ever approved back into the inbox', function () {
    roundClosedAt($this, 'approved',
        Carbon::parse('2026-06-02 09:00:00'),
        Carbon::parse('2026-06-02 17:00:00')); // closed months ago

    expect(completedTab($this)->assertSuccessful()->viewData('counts')['completed'])->toBe(0);
});

it('still finds that old round when its date is picked', function () {
    roundClosedAt($this, 'approved',
        Carbon::parse('2026-06-02 09:00:00'),
        Carbon::parse('2026-06-02 17:00:00'));

    $response = $this->actingAs($this->manager)
        ->get('/verification?date=2026-06-02&filter_type=person&tab=completed')
        ->assertSuccessful();

    expect($response->viewData('counts')['completed'])->toBe(1);
});

/**
 * The empty state blamed a date filter that was not set: "ไม่พบข้อมูลการตรวจใน
 * วันที่เลือก" on a page whose own picker reads "กำลังแสดงงานค้างทั้งหมด".
 * Someone reading that goes looking for a filter to clear.
 */
it('does not blame a date filter nobody set', function () {
    completedTab($this)
        ->assertSuccessful()
        ->assertDontSee('ในวันที่เลือก', false)
        ->assertDontSee('ในวันที่ระบุ', false)
        ->assertSee('เลือกวันที่เพื่อดูย้อนหลัง', false);
});

it('still blames the date filter when there actually is one', function () {
    $this->actingAs($this->manager)
        ->get('/verification?date=2026-06-02&filter_type=person&tab=completed')
        ->assertSuccessful()
        ->assertSee('ในวันที่เลือก', false);
});

it('does not let recently closed work leak into the tabs that mean "still waiting"', function () {
    roundClosedAt($this, 'approved',
        Carbon::parse('2026-08-13 09:00:00'),
        Carbon::parse('2026-09-24 13:59:00'));

    $response = completedTab($this)->assertSuccessful();

    expect($response->viewData('counts')['pending'])->toBe(0)
        ->and($response->viewData('counts')['awaiting_approval'])->toBe(0)
        ->and($response->viewData('counts')['reclean'])->toBe(0);
});
