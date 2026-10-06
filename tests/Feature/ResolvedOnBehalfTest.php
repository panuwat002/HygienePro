<?php

use App\Models\Checkpoint;
use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * QA stepping in for Production is deliberate: it gets the floor cleaned
 * sooner than waiting for the department to pick the ticket up. The risk is
 * not that it happens - it is that it becomes every time and nobody notices.
 *
 * Nothing could notice, because resolve() wrote the resolver into
 * assigned_to - "auto-claim by resolver" - so the moment QA fixed something on
 * Production's behalf the record stopped saying it had ever been Production's.
 * One column holding two facts, the second write destroying the first.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-06 10:00:00');
    Storage::fake('public');

    $this->production = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->pdk = Department::create([
        'dept_name' => 'Production (ห้องแคะ)', 'dept_code' => 'PDK', 'visibility_type' => 'isolated',
        'parent_department_id' => $this->production->id,
    ]);
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->productionHead = User::create([
        'name' => 'Production Head', 'email' => 'pd@example.com', 'password' => bcrypt('x'),
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->production->id,
    ]);
    $this->qaSupervisor = User::create([
        'name' => 'QA Supervisor', 'email' => 'qa@example.com', 'password' => bcrypt('x'),
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->qa->id,
    ]);

    $this->checkpoint = Checkpoint::create(['title' => 'ความสะอาด', 'is_active' => true, 'type' => 'area']);
});

afterEach(fn () => Carbon::setTestNow());

function findingOwnedBy($ctx, Department $owner, ?User $assignee = null): CorrectiveAction
{
    $room = Location::create([
        'location_name' => 'ห้อง ' . $owner->dept_code . uniqid(),
        'department_id' => $owner->id,
    ]);

    $session = InspectionSession::factory()->create([
        'department_id' => $ctx->qa->id, 'status' => 'completed',
    ]);

    $log = InspectionLog::create([
        'session_id' => $session->id, 'location_id' => $room->id,
        'checkpoint_id' => $ctx->checkpoint->id, 'result' => 'fail', 'inspected_at' => now(),
    ]);

    return CorrectiveAction::factory()->create([
        'inspection_log_id' => $log->id,
        'escalated_by' => $ctx->qaSupervisor->id,
        'assigned_to' => $assignee?->id,
        'status' => $assignee ? 'assigned' : 'open',
    ]);
}

function resolveAs($ctx, CorrectiveAction $action, User $user)
{
    return $ctx->actingAs($user)->post(route('corrective.resolve'), [
        'action_id' => $action->id,
        'action_taken' => 'ล้างท่อระบายน้ำและเช็ดแห้ง',
        'preventive_action' => 'เพิ่มรอบทำความสะอาดท้ายกะ',
        'proof_image' => UploadedFile::fake()->image('proof.jpg'),
    ]);
}

it('keeps the person it was given to when somebody else does it', function () {
    $action = findingOwnedBy($this, $this->production, $this->productionHead);

    resolveAs($this, $action, $this->qaSupervisor)->assertSessionHas('success');

    $action->refresh();

    expect($action->assigned_to)->toBe($this->productionHead->id)
        ->and($action->resolved_by)->toBe($this->qaSupervisor->id);
});

it('marks a finding one department dealt with for another', function () {
    $action = findingOwnedBy($this, $this->production);

    resolveAs($this, $action, $this->qaSupervisor);

    expect($action->refresh()->wasResolvedOnBehalf())->toBeTrue();
});

it('does not mark a department fixing its own problem', function () {
    $action = findingOwnedBy($this, $this->production, $this->productionHead);

    resolveAs($this, $action, $this->productionHead);

    expect($action->refresh()->wasResolvedOnBehalf())->toBeFalse();
});

/**
 * ห้องแคะ's head is Production's head. Neither is standing in for the other.
 */
it('does not mark a parent department covering its own sub-department', function () {
    $action = findingOwnedBy($this, $this->pdk);

    resolveAs($this, $action, $this->productionHead);

    expect($action->refresh()->wasResolvedOnBehalf())->toBeFalse();
});

it('takes nobody on behalf of anybody before it is resolved', function () {
    $action = findingOwnedBy($this, $this->production);

    expect($action->wasResolvedOnBehalf())->toBeFalse();
});

/**
 * The loop that was missing: a number to take to the department, rather than
 * a feeling that QA has been doing a lot lately.
 */
it('counts this month on the page, by the department it was done for', function () {
    resolveAs($this, findingOwnedBy($this, $this->production), $this->qaSupervisor);
    resolveAs($this, findingOwnedBy($this, $this->production), $this->qaSupervisor);
    resolveAs($this, findingOwnedBy($this, $this->pdk), $this->qaSupervisor);

    $stats = $this->actingAs($this->qaSupervisor)
        ->get(route('corrective.index'))
        ->assertSuccessful()
        ->viewData('onBehalfStats');

    expect($stats['count'])->toBe(3)
        ->and($stats['byDepartment']['Production'])->toBe(2)
        ->and($stats['byDepartment']['Production (ห้องแคะ)'])->toBe(1);
});

it('says nothing when every department fixed its own', function () {
    resolveAs($this, findingOwnedBy($this, $this->production, $this->productionHead), $this->productionHead);

    $this->actingAs($this->qaSupervisor)
        ->get(route('corrective.index'))
        ->assertSuccessful()
        ->assertDontSee('ดำเนินการแทนแผนกอื่น');
});

it('shows the badge on the row itself, not only in the total', function () {
    resolveAs($this, findingOwnedBy($this, $this->production), $this->qaSupervisor);

    $this->actingAs($this->qaSupervisor)
        ->get(route('corrective.index'))
        ->assertSuccessful()
        ->assertSee('ดำเนินการแทน');
});
