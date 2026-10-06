<?php

use App\Models\Checkpoint;
use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The Issues page exists so a finding can be routed: assigned to somebody, or
 * taken on by QA, or handed to the department that owns the problem to assign
 * and close by a due date. All three of those depend on reaching the right
 * people, and three separate things stopped that.
 *
 *  - the assignee list was "anybody in a department whose NAME contains
 *    Production or ผลิต", so a finding raised in QA could only go to
 *    Production - never to the department that owns it;
 *  - a new finding was announced only to level 5 and above of its department,
 *    so the supervisor who actually assigns heard nothing, and a department
 *    with no manager of its own heard nothing at all;
 *  - a parent department's head could not see a sub-department's findings,
 *    although canAcknowledge() had already been taught that they answer for
 *    them.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-06 10:00:00');

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
    $this->packing = Department::create([
        'dept_name' => 'Packing', 'dept_code' => 'PK', 'visibility_type' => 'isolated',
    ]);

    $this->productionHead = User::create([
        'name' => 'Production Head', 'email' => 'pd@example.com', 'password' => bcrypt('x'),
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->production->id,
    ]);
    $this->packingHead = User::create([
        'name' => 'Packing Head', 'email' => 'pk@example.com', 'password' => bcrypt('x'),
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->packing->id,
    ]);
    $this->qaSupervisor = User::create([
        'name' => 'QA Supervisor', 'email' => 'qa@example.com', 'password' => bcrypt('x'),
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->qa->id,
    ]);

    $this->checkpoint = Checkpoint::create(['title' => 'ความสะอาด', 'is_active' => true, 'type' => 'area']);
});

afterEach(fn () => Carbon::setTestNow());

function findingIn($ctx, Department $dept): CorrectiveAction
{
    $session = InspectionSession::factory()->create([
        'department_id' => $dept->id,
        'inspection_date' => '2026-10-06',
        'status' => 'completed',
    ]);

    $log = InspectionLog::create([
        'session_id' => $session->id, 'checkpoint_id' => $ctx->checkpoint->id,
        'result' => 'fail', 'inspected_at' => now(),
    ]);

    return CorrectiveAction::factory()->create([
        'inspection_log_id' => $log->id,
        'escalated_by' => $ctx->qaSupervisor->id,
        'status' => 'open',
    ]);
}

function assignTo($ctx, CorrectiveAction $action, User $assignee, ?User $as = null)
{
    return $ctx->actingAs($as ?? $ctx->qaSupervisor)->post(route('corrective.assign'), [
        'action_id' => $action->id,
        'assigned_to' => $assignee->id,
        'due_date' => '2026-10-07',
    ]);
}

it('hands a finding to the department that owns it', function () {
    $finding = findingIn($this, $this->packing);

    assignTo($this, $finding, $this->packingHead)->assertSessionHas('success');

    expect($finding->refresh()->assigned_to)->toBe($this->packingHead->id);
});

/**
 * The old list could only offer Production, so this was the ONLY thing that
 * worked - and only by accident of the department's name.
 */
it('still hands a Production finding to Production', function () {
    $finding = findingIn($this, $this->production);

    assignTo($this, $finding, $this->productionHead)->assertSessionHas('success');

    expect($finding->refresh()->assigned_to)->toBe($this->productionHead->id);
});

it('refuses to send a finding to an unrelated department', function () {
    $finding = findingIn($this, $this->packing);

    assignTo($this, $finding, $this->productionHead)->assertSessionHas('error');

    expect($finding->refresh()->assigned_to)->toBeNull();
});

/**
 * ห้องแคะ was split out of Production and kept Production's head.
 */
it('lets the parent department head take a sub-department finding', function () {
    $finding = findingIn($this, $this->pdk);

    assignTo($this, $finding, $this->productionHead)->assertSessionHas('success');

    expect($finding->refresh()->assigned_to)->toBe($this->productionHead->id);
});

/**
 * One of the three routes this page is for: QA fixes it themselves.
 */
it('lets QA take a finding on themselves whatever department it came from', function () {
    $finding = findingIn($this, $this->packing);

    assignTo($this, $finding, $this->qaSupervisor)->assertSessionHas('success');

    expect($finding->refresh()->assigned_to)->toBe($this->qaSupervisor->id);
});

it('offers the whole senior roster to the page, not just Production', function () {
    findingIn($this, $this->packing);

    $offered = $this->actingAs($this->qaSupervisor)
        ->get(route('corrective.index'))
        ->assertSuccessful()
        ->viewData('assignableUsers')
        ->pluck('id');

    expect($offered)->toContain($this->packingHead->id)
        ->and($offered)->toContain($this->qaSupervisor->id);
});

it('tells the page which departments each finding may go to', function () {
    $finding = findingIn($this, $this->pdk);

    $routable = $this->actingAs($this->qaSupervisor)
        ->get(route('corrective.index'))
        ->assertSuccessful()
        ->viewData('routableDepartments');

    expect($routable[$finding->id])->toContain($this->pdk->id)
        ->and($routable[$finding->id])->toContain($this->production->id)
        ->and($routable[$finding->id])->not->toContain($this->packing->id);
});

/**
 * Visibility, the other half of being able to act.
 */
it('shows a parent department head the findings of a sub-department', function () {
    $finding = findingIn($this, $this->pdk);

    $visible = $this->actingAs($this->productionHead)
        ->get(route('corrective.index'))
        ->assertSuccessful()
        ->viewData('openActions')
        ->pluck('id');

    expect($visible)->toContain($finding->id);
});

it('does not show one department the findings of another', function () {
    $finding = findingIn($this, $this->packing);

    $visible = $this->actingAs($this->productionHead)
        ->get(route('corrective.index'))
        ->assertSuccessful()
        ->viewData('openActions')
        ->pluck('id');

    expect($visible)->not->toContain($finding->id);
});

/**
 * Being told is the other half again. A finding nobody hears about waits on
 * somebody discovering it by chance.
 */
it('tells the department supervisor, not only the manager', function () {
    Illuminate\Support\Facades\Notification::fake();

    $finding = findingIn($this, $this->production);
    $session = $finding->log->session;

    app(App\Services\InspectionService::class)->notifyManagersOfSessionCars($session);

    Illuminate\Support\Facades\Notification::assertSentTo(
        $this->productionHead,
        App\Notifications\SessionCarsSummaryNotification::class
    );
});

/**
 * ห้องแคะ had no manager of its own the week it was split out, so this told
 * nobody at all - about findings raised against a brand-new work area.
 */
it('falls back to the parent department when a department has nobody', function () {
    Illuminate\Support\Facades\Notification::fake();

    // ห้องแคะ has nobody of its own; Production's head covers it.
    $finding = findingIn($this, $this->pdk);

    app(App\Services\InspectionService::class)->notifyManagersOfSessionCars($finding->log->session);

    Illuminate\Support\Facades\Notification::assertSentTo(
        $this->productionHead,
        App\Notifications\SessionCarsSummaryNotification::class
    );
});
