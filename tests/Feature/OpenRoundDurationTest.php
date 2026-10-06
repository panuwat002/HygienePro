<?php

use App\Models\Department;
use App\Models\InspectionSession;
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
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-06 15:00:00');

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

it('prints whole hours and minutes, not a float', function () {
    unfinishedRound($this, '2026-10-06 09:12:00');

    $html = $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->getContent();

    expect($html)->toContain('5 ชม. 48 นาที')
        ->and($html)->not->toMatch('/\d+\.\d{4,}/');
});

it('says what is actually wrong, not just that it is long', function () {
    unfinishedRound($this, '2026-10-06 09:12:00');

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('ยังไม่ได้ปิดรอบ')
        ->assertDontSee('นานเกินไป');
});

it('says nothing about a round that has only just started', function () {
    unfinishedRound($this, '2026-10-06 14:30:00');

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSee('ยังไม่ได้ปิดรอบ');
});
