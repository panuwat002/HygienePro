<?php

/**
 * A finding that had been fixed could not be checked off anywhere.
 *
 * It reached 'resolved' on the issues page and the only control offered was a
 * link to the verification page, where the matching card - already verified as
 * an inspection record - had nothing left to press. Nothing in the system
 * could move the finding on, so it sat in "สิ่งที่ต้องดำเนินการ" indefinitely:
 * fixed, written up, photographed, and still listed as work.
 *
 * Checking a fix now happens where the fix is recorded.
 */

use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Http::fake();
    Notification::fake();

    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);

    $this->qa = User::factory()->create([
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->dept->id,
    ]);
    $this->fixer = User::factory()->create([
        'role' => 'staff', 'level' => 2, 'department_id' => $this->dept->id,
    ]);
    $this->outsider = User::factory()->create([
        'role' => 'staff', 'level' => 1, 'department_id' => $this->dept->id,
    ]);
});

function resolvedFinding($ctx, array $overrides = []): CorrectiveAction
{
    $action = CorrectiveAction::factory()->create(array_merge([
        'status' => 'resolved',
        'escalated_by' => $ctx->qa->id,
        'action_taken' => 'ลบแป้งออกจากหน้า',
        'resolved_by' => $ctx->fixer->id,
        'resolved_at' => now()->subDay(),
    ], $overrides));

    $action->log?->update(['verification_status' => 'reclean', 'verified_at' => now()->subDays(2)]);

    return $action->fresh();
}

test('verifying a fix records who checked it and when', function () {
    $action = resolvedFinding($this);

    $this->actingAs($this->qa)
        ->post(route('corrective.verify'), ['action_id' => $action->id])
        ->assertRedirect();

    $action->refresh();

    expect($action->status)->toBe('verified')
        ->and($action->verified_by)->toBe($this->qa->id)
        ->and($action->verified_at)->not->toBeNull()
        // Who did the work is untouched - a third fact, a third column.
        ->and($action->resolved_by)->toBe($this->fixer->id);
});

test('it clears the reclean flag on the inspection log', function () {
    // Left at 'reclean', the card stays on the supervisor's สั่งแก้ไข tab
    // after the work is finished. One act of checking, one write to both.
    $action = resolvedFinding($this);

    $this->actingAs($this->qa)->post(route('corrective.verify'), ['action_id' => $action->id]);

    expect($action->log->fresh()->verification_status)->toBe('verified')
        ->and($action->log->fresh()->verifier_id)->toBe($this->qa->id);
});

test('the person who fixed it cannot verify their own work', function () {
    // The fixer is also the one who raised it, so authorisation would let them
    // through. Having done the work is the thing that stops them.
    $action = resolvedFinding($this, ['escalated_by' => $this->fixer->id]);

    $this->actingAs($this->fixer)
        ->post(route('corrective.verify'), ['action_id' => $action->id])
        ->assertSessionHas('error');

    expect($action->fresh()->status)->toBe('resolved');
});

test('somebody with no part in it cannot verify', function () {
    $action = resolvedFinding($this);

    $this->actingAs($this->outsider)
        ->post(route('corrective.verify'), ['action_id' => $action->id])
        ->assertSessionHas('error');

    expect($action->fresh()->status)->toBe('resolved');
});

test('a finding nobody has fixed cannot be verified', function () {
    $action = CorrectiveAction::factory()->create([
        'status' => 'open',
        'escalated_by' => $this->qa->id,
    ]);

    $this->actingAs($this->qa)
        ->post(route('corrective.verify'), ['action_id' => $action->id])
        ->assertSessionHas('error');

    expect($action->fresh()->status)->toBe('open');
});

test('a fix with nothing written down cannot be verified', function () {
    $action = resolvedFinding($this, ['action_taken' => null]);

    $this->actingAs($this->qa)
        ->post(route('corrective.verify'), ['action_id' => $action->id])
        ->assertSessionHas('error');

    expect($action->fresh()->status)->toBe('resolved');
});

test('verifying twice is refused rather than rewriting the first check', function () {
    $action = resolvedFinding($this);

    $this->actingAs($this->qa)->post(route('corrective.verify'), ['action_id' => $action->id]);
    $firstCheck = $action->fresh()->verified_at;

    $this->actingAs($this->qa)
        ->post(route('corrective.verify'), ['action_id' => $action->id])
        ->assertSessionHas('error');

    expect($action->fresh()->verified_at->toDateTimeString())->toBe($firstCheck->toDateTimeString());
});

test('it leaves the open queue once verified', function () {
    // The complaint that started this: fixed, written up, and still listed
    // under "things to do".
    $action = resolvedFinding($this);

    $this->actingAs($this->qa)->post(route('corrective.verify'), ['action_id' => $action->id]);

    $open = CorrectiveAction::whereIn('status', ['open', 'assigned', 'resolved'])->pluck('id');

    expect($open)->not->toContain($action->id);
});
