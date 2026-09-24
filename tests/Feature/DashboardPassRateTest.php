<?php

/**
 * A day nobody inspected is not a perfect day.
 *
 * Both dashboard rates fell back to a literal 100 when the denominator was
 * zero, so an empty morning rendered a green "อัตราผ่าน 100%" next to
 * "งานตรวจวันนี้ 0". Green reads as "all clear" — the exact opposite of what
 * an untouched shift means. scoreFromLogs() already answers null and lets the
 * page print "—"; these two figures now do the same.
 */

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    Carbon::setTestNow('2026-09-24 10:00:00');

    $this->dept = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'morning', 'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $this->checkpoint = Checkpoint::create([
        'title' => 'Hand wash', 'is_active' => true, 'type' => 'person',
    ]);

    $this->manager = User::create([
        'name' => 'Manager', 'email' => 'rate-manager@example.com',
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => $this->dept->id,
    ]);
    $this->manager->forceFill(['email_verified_at' => now()])->save();

    $this->rateRound = 0;
});

afterEach(function () {
    Carbon::setTestNow();
});

function logRateResult($ctx, string $result, ?Carbon $at = null): void
{
    $ctx->rateRound++;
    $at ??= Carbon::now();

    $employee = Employee::create([
        'employee_id' => 'R' . $ctx->rateRound, 'fullname' => 'Worker ' . $ctx->rateRound,
        'department_id' => $ctx->dept->id, 'shift_id' => $ctx->shift->id,
        'qr_code_hash' => 'rh' . $ctx->rateRound, 'is_active' => true,
    ]);

    $session = InspectionSession::create([
        'inspector_id' => $ctx->manager->id, 'department_id' => $ctx->dept->id,
        'type' => 'personnel', 'inspection_date' => $at->toDateString(),
        'shift' => 'custom_' . $ctx->shift->id, 'round' => $ctx->rateRound, 'status' => 'completed',
    ]);

    InspectionLog::create([
        'session_id' => $session->id, 'checkpoint_id' => $ctx->checkpoint->id,
        'employee_id' => $employee->id, 'result' => $result, 'inspected_at' => $at,
    ]);
}

it('reports no pass rate for a day with no inspections', function () {
    $response = $this->actingAs($this->manager)->get('/dashboard')->assertSuccessful();

    expect($response->viewData('inspectionsToday'))->toBe(0)
        ->and($response->viewData('passRate'))->toBeNull();
});

it('prints a dash instead of a fabricated percentage', function () {
    $this->actingAs($this->manager)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertDontSee('>100%<', false)
        ->assertSee('—', false);
});

/**
 * Nobody came to work and the line was not running. Nothing was assessed, so
 * there is no ratio - counting these as passes is what let a shift with three
 * people on leave report perfect hygiene.
 */
it('does not read an unassessed day as a perfect one', function () {
    logRateResult($this, 'absent');
    logRateResult($this, 'no_production');

    $response = $this->actingAs($this->manager)->get('/dashboard')->assertSuccessful();

    expect($response->viewData('passRate'))->toBeNull();
});

it('still reports the real rate once something was assessed', function () {
    logRateResult($this, 'pass');
    logRateResult($this, 'pass');
    logRateResult($this, 'pass');
    logRateResult($this, 'fail');
    logRateResult($this, 'absent'); // excluded from both sides

    $response = $this->actingAs($this->manager)->get('/dashboard')->assertSuccessful();

    expect($response->viewData('passRate'))->toBe(75);
});

it('leaves the hygiene index empty when the month assessed nothing', function () {
    $response = $this->actingAs($this->manager)->get('/dashboard')->assertSuccessful();

    expect($response->viewData('monthlyPassRate'))->toBeNull();
});

it('scores the hygiene index on the whole month, not just today', function () {
    logRateResult($this, 'pass', Carbon::parse('2026-09-10 09:00:00'));
    logRateResult($this, 'fail', Carbon::parse('2026-09-11 09:00:00'));
    logRateResult($this, 'pass', Carbon::parse('2026-08-31 09:00:00')); // last month

    $response = $this->actingAs($this->manager)->get('/dashboard')->assertSuccessful();

    expect($response->viewData('monthlyPassRate'))->toBe(50)
        ->and($response->viewData('passRate'))->toBeNull(); // none of it is today
});

it('does not draw the index ring when there is no score to draw', function () {
    $this->actingAs($this->manager)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertDontSee('stroke-dashoffset', false);
});
