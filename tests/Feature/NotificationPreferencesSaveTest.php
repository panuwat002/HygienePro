<?php

/**
 * The settings page has never saved anything.
 *
 * SettingsController::update() calls $user->update(['notification_preferences'
 * => ...]), but that key is cast and never was fillable, so Eloquent's mass
 * assignment protection dropped it silently, redirected with a green "บันทึก
 * เรียบร้อย" and left every account on defaults. Nobody could turn an email
 * off, which matters more now that there is one more email to turn off.
 */

use App\Models\Department;
use App\Models\User;

beforeEach(function () {
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);

    $this->user = User::create([
        'name' => 'Wallika', 'email' => 'prefs@example.com',
        'password' => bcrypt('password'), 'role' => 'manager', 'level' => 5,
        'department_id' => $this->qa->id,
    ]);
});

it('actually stores what the settings form submits', function () {
    $this->actingAs($this->user)
        ->post(route('settings.update'), [
            // Only these arrive: an unchecked switch sends nothing at all.
            'email_car_overdue' => 'on',
            'email_awaiting_approval' => 'on',
        ])
        ->assertRedirect(route('settings.index'));

    $saved = $this->user->fresh()->notification_preferences;

    expect($saved)->toBeArray()
        ->and($saved['email_car_overdue'])->toBeTrue()
        ->and($saved['email_session_started'])->toBeFalse();
});

it('lets a manager switch the approval digest off', function () {
    $this->actingAs($this->user)
        ->post(route('settings.update'), ['email_car_overdue' => 'on'])
        ->assertRedirect();

    expect($this->user->fresh()->wantsEmailFor('email_awaiting_approval'))->toBeFalse();
});

it('defaults the approval digest on for someone who has never touched settings', function () {
    expect($this->user->wantsEmailFor('email_awaiting_approval'))->toBeTrue();
});

it('offers the approval digest as its own switch', function () {
    $this->actingAs($this->user)
        ->get(route('settings.index'))
        ->assertSuccessful()
        ->assertSee('name="email_awaiting_approval"', false);
});
