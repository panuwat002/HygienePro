<?php

use App\Models\Department;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The dashboard's open-rounds panel printed "นานเกินไป (5.8002289908333 ชม.)".
 *
 * diffInHours() returns a float in Carbon 3, and the float went straight to
 * the screen - the same leak as the raw "(Open)" and "In_progress" elsewhere.
 *
 * The wording was the other half: "too long" says a round has been open a
 * while without saying what that means. It means the inspector never pressed
 * finish, and finishing is what sends the summary, tells the supervisor there
 * is something to verify, and closes off a random audit.
 *
 * What "a while" measures against changed afterwards - see
 * ActiveRoundShiftBadgeTest - because an age alone flagged every normal
 * morning. These two guards survive that change: no raw float reaches the
 * screen, and the panel says what is wrong rather than that it is long.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-06 15:00:00');

    Shift::create(['shift_name' => 'morning', 'start_time' => '07:00:00', 'end_time' => '13:00:00']);

    $this->dept = Department::create([
        'dept_name' => 'Production (ห้องแคะ)', 'dept_code' => 'PDK', 'visibility_type' => 'isolated',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->dept->id,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function unfinishedRound($ctx, string $startedAt): InspectionSession
{
    $session = InspectionSession::create([
        'inspector_id' => $ctx->admin->id, 'department_id' => $ctx->dept->id,
        'type' => 'personnel', 'inspection_date' => '2026-10-06',
        'shift' => 'morning', 'round' => 1, 'status' => 'in_progress',
    ]);

    $session->forceFill(['created_at' => $startedAt])->save();

    return $session;
}

it('never prints a raw float on screen', function () {
    // Shift ended 13:00, grace to 15:00, now 15:00 - the flagged case, which is
    // the one that used to carry the number.
    unfinishedRound($this, '2026-10-06 09:12:00');

    $html = $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->getContent();

    expect($html)->not->toMatch('/\d+\.\d{4,}/');
});

it('says what is actually wrong, not just that it is long', function () {
    unfinishedRound($this, '2026-10-06 09:12:00');

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('เลยเวลาที่ระบบควรปิดให้แล้ว')
        ->assertDontSee('นานเกินไป');
});

it('says nothing alarming about a round still inside its own shift', function () {
    Carbon::setTestNow('2026-10-06 12:00:00');
    unfinishedRound($this, '2026-10-06 09:12:00');

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSee('เลยเวลาที่ระบบควรปิดให้แล้ว')
        ->assertSee('เลิกกะ 13:00 น.');
});
