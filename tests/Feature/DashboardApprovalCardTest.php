<?php

/**
 * A QA manager's only job on this system is approving what QA has verified,
 * and the dashboard had no card for it - only "รอทวนสอบ", which is somebody
 * else's queue. With nothing on screen saying work was waiting, 17,993 logs
 * accumulated at 'verified'.
 */

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();

    $this->dept = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'morning', 'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $this->checkpoint = Checkpoint::create([
        'title' => 'Hand wash', 'is_active' => true, 'type' => 'person',
    ]);

    $this->round = 0;
});

function qaUser($ctx, string $role, int $level): User
{
    $user = User::create([
        'name' => ucfirst($role), 'email' => "{$role}{$level}@example.com",
        'password' => bcrypt('password'), 'role' => $role, 'level' => $level,
        'department_id' => $ctx->dept->id,
    ]);
    $user->forceFill(['email_verified_at' => now()])->save();

    return $user;
}

function logAwaiting($ctx, User $inspector, ?string $status): void
{
    $ctx->round++;

    $employee = Employee::create([
        'employee_id' => 'E' . $ctx->round, 'fullname' => 'Worker ' . $ctx->round,
        'department_id' => $ctx->dept->id, 'shift_id' => $ctx->shift->id,
        'qr_code_hash' => 'h' . $ctx->round, 'is_active' => true,
    ]);

    $session = InspectionSession::create([
        'inspector_id' => $inspector->id, 'department_id' => $ctx->dept->id,
        'type' => 'personnel', 'inspection_date' => now()->toDateString(),
        'shift' => 'custom_' . $ctx->shift->id, 'round' => $ctx->round, 'status' => 'completed',
    ]);

    InspectionLog::create([
        'session_id' => $session->id, 'checkpoint_id' => $ctx->checkpoint->id,
        'employee_id' => $employee->id, 'result' => 'pass', 'inspected_at' => now(),
        'verification_status' => $status,
        'verified_at' => $status === null ? null : now(),
    ]);
}

it('tells a QA manager how much is waiting on them', function () {
    $manager = qaUser($this, 'manager', 5);

    logAwaiting($this, $manager, 'verified');
    logAwaiting($this, $manager, 'verified');
    logAwaiting($this, $manager, 'approved');     // already done
    logAwaiting($this, $manager, 'auto_verified'); // nothing to sign
    logAwaiting($this, $manager, null);            // not verified yet

    $response = $this->actingAs($manager)->get('/dashboard')->assertSuccessful();

    expect($response->viewData('awaitingApprovalCount'))->toBe(2);

    $response->assertSee('รออนุมัติ', false);
});

it('links that card at the tab holding the work', function () {
    $manager = qaUser($this, 'manager', 5);
    logAwaiting($this, $manager, 'verified');

    $this->actingAs($manager)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('tab=awaiting_approval', false);
});

it('does not show the card to someone who cannot approve', function () {
    $supervisor = qaUser($this, 'supervisor', 4);
    logAwaiting($this, $supervisor, 'verified');

    $this->actingAs($supervisor)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertDontSee('tab=awaiting_approval', false);
});

function logAwaitingArea($ctx, User $inspector, ?string $status): void
{
    $ctx->round++;

    $location = App\Models\Location::create(['location_name' => 'Room ' . $ctx->round]);
    $location->checkpoints()->attach($ctx->checkpoint->id);

    $session = InspectionSession::create([
        'inspector_id' => $inspector->id, 'department_id' => $ctx->dept->id,
        'type' => 'area', 'inspection_date' => now()->toDateString(),
        'shift' => 'custom_' . $ctx->shift->id, 'round' => $ctx->round, 'status' => 'completed',
    ]);

    InspectionLog::create([
        'session_id' => $session->id, 'checkpoint_id' => $ctx->checkpoint->id,
        'employee_id' => null, 'location_id' => $location->id,
        'result' => 'pass', 'inspected_at' => now(),
        'verification_status' => $status,
        'verified_at' => $status === null ? null : now(),
    ]);
}

/**
 * The card said 225 and linked at a tab filtered to พนักงาน, which held 50 of
 * them. The number a manager reads and the page that number opens have to be
 * the same number.
 */
it('breaks the waiting work down by what kind of round it was', function () {
    $manager = qaUser($this, 'manager', 5);

    logAwaiting($this, $manager, 'verified');
    logAwaiting($this, $manager, 'verified');
    logAwaiting($this, $manager, 'verified');
    logAwaitingArea($this, $manager, 'verified');
    logAwaitingArea($this, $manager, 'verified');
    logAwaiting($this, $manager, 'approved');     // not waiting on anyone

    $response = $this->actingAs($manager)->get('/dashboard')->assertSuccessful();

    expect($response->viewData('awaitingApprovalCount'))->toBe(5)
        ->and($response->viewData('awaitingApprovalPersonCount'))->toBe(3)
        ->and($response->viewData('awaitingApprovalAreaCount'))->toBe(2);
});

it('links each half of the breakdown at its own category', function () {
    $manager = qaUser($this, 'manager', 5);

    logAwaiting($this, $manager, 'verified');
    logAwaitingArea($this, $manager, 'verified');

    $this->actingAs($manager)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('filter_type=person&amp;tab=awaiting_approval', false)
        ->assertSee('filter_type=machine&amp;tab=awaiting_approval', false);
});
