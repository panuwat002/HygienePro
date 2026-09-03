<?php

use App\Models\Department;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use App\Services\InspectionService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The inspector picks the shift; the system never guesses one.
 *
 * A time-guessed generic key ('morning') makes the session target every shift of that type
 * at once — one "ผ่านทุกคนที่เหลือ" then sweeps groups nobody has started inspecting.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    // Inspectors sit in QA (that is what the `inspect` gate checks) and inspect other departments.
    $this->qaDept = Department::create([
        'dept_name' => 'QA', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);
    $this->inspector = User::create([
        'name' => 'Inspector', 'email' => 'inspector@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->qaDept->id,
    ]);
    $this->morning = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => 'กะเช้า',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);
    $this->service = app(InspectionService::class);
});

afterEach(fn () => Carbon::setTestNow());

it('refuses to start a personnel round with no shift picked', function () {
    Carbon::setTestNow(Carbon::today()->setTime(9, 0));

    $this->actingAs($this->inspector)
        ->post(route('inspection.start', 'personnel'), ['department_id' => $this->dept->id])
        ->assertRedirect(route('inspection.dashboard', 'personnel'))
        ->assertSessionHas('error');

    expect(InspectionSession::where('type', 'personnel')->count())->toBe(0);
});

it('never falls back to a time-guessed shift at the service seam', function () {
    Carbon::setTestNow(Carbon::today()->setTime(9, 0));

    expect(fn () => $this->service->startSession($this->inspector, $this->dept->id, 'personnel'))
        ->toThrow(ValidationException::class);

    expect(InspectionSession::where('type', 'personnel')->count())->toBe(0);
});

it('starts the round on exactly the shift card the inspector picked', function () {
    $other = Shift::create([
        'shift_name' => 'กะเช้า 10.00-19.00', 'shift_type' => 'กะเช้า',
        'start_time' => '10:00:00', 'end_time' => '19:00:00',
    ]);

    $this->actingAs($this->inspector)->post(route('inspection.start', 'personnel'), [
        'department_id' => $this->dept->id,
        'targets' => ['shift:custom_' . $this->morning->id],
    ]);

    $session = InspectionSession::where('type', 'personnel')->firstOrFail();

    expect($session->shift)->toBe('custom_' . $this->morning->id)
        // The sibling กะเช้า group is NOT swept in.
        ->and($session->getResolvedShifts()['shift_ids'])->toBe([$this->morning->id])
        ->and($session->getResolvedShifts()['shift_ids'])->not->toContain($other->id);
});

it('still auto-detects the shift for area rounds', function () {
    Carbon::setTestNow(Carbon::today()->setTime(9, 0));

    $session = $this->service->startSession($this->inspector, $this->dept->id, 'area');

    expect($session->shift)->toBe('morning');
});

it('resumes an existing round when the shift comes back from the session', function () {
    $existing = $this->service->startSession(
        $this->inspector, $this->dept->id, 'personnel', false, 'custom_' . $this->morning->id
    );

    // What the dashboard replays into targets[] when resuming.
    $this->actingAs($this->inspector)->post(route('inspection.start', 'personnel'), [
        'department_id' => $this->dept->id,
        'targets' => ['shift:' . $existing->shift],
    ]);

    expect(InspectionSession::where('type', 'personnel')->count())->toBe(1)
        ->and(InspectionSession::first()->id)->toBe($existing->id);
});

it('knows when a round on a picked shift card is over, so auto-close can fire', function () {
    // Matching shift_name literally never hit "กะบ่าย 17.00-02.00", so shiftEndAt() came back
    // null and personnel sessions stayed open forever.
    $afternoon = Shift::create([
        'shift_name' => 'กะบ่าย 17.00-02.00', 'shift_type' => 'กะบ่าย',
        'start_time' => '17:00:00', 'end_time' => '02:00:00',
    ]);

    $session = InspectionSession::create([
        'department_id' => $this->dept->id, 'inspection_date' => '2026-07-29',
        'shift' => 'custom_' . $afternoon->id, 'inspector_id' => $this->inspector->id,
        'status' => 'in_progress', 'type' => 'personnel', 'round' => 1,
    ]);

    // 17:00-02:00 wraps past midnight, so the round ends the next day.
    expect($this->service->shiftEndAt($session)?->toDateTimeString())->toBe('2026-07-30 02:00:00');
});

it('waits for the last shift to end when a round covers several cards', function () {
    $early = Shift::create([
        'shift_name' => 'กะเช้า 07.00-16.00', 'shift_type' => 'กะเช้า',
        'start_time' => '07:00:00', 'end_time' => '16:00:00',
    ]);
    $late = Shift::create([
        'shift_name' => 'กะเช้า 10.00-19.00', 'shift_type' => 'กะเช้า',
        'start_time' => '10:00:00', 'end_time' => '19:00:00',
    ]);

    $session = InspectionSession::create([
        'department_id' => $this->dept->id, 'inspection_date' => '2026-07-29',
        'shift' => 'custom_' . $early->id . ',custom_' . $late->id,
        'inspector_id' => $this->inspector->id,
        'status' => 'in_progress', 'type' => 'personnel', 'round' => 1,
    ]);

    expect($this->service->shiftEndAt($session)?->toDateTimeString())->toBe('2026-07-29 19:00:00');
});
