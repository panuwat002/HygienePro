<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The email address is a login identifier (LoginRequest authenticates on email or
 * employee_code), so PATCH /profile changing it without re-authentication is an account
 * takeover primitive: an unattended session or a stolen cookie repoints the account,
 * then the password-reset flow completes the takeover without the password ever being
 * known. ProfileController::destroy already required current_password; update did not.
 */
beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'original@example.com',
        'password' => bcrypt('correct-horse'),
        'role' => 'staff',
        'level' => 2,
    ]);
});

test('changing the email without the current password is rejected', function () {
    $this->actingAs($this->user)
        ->patch('/profile', [
            'name' => $this->user->name,
            'email' => 'attacker@example.com',
        ])
        ->assertSessionHasErrors('current_password');

    expect($this->user->refresh()->email)->toBe('original@example.com');
});

test('changing the email with a wrong current password is rejected', function () {
    $this->actingAs($this->user)
        ->patch('/profile', [
            'name' => $this->user->name,
            'email' => 'attacker@example.com',
            'current_password' => 'not-the-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect($this->user->refresh()->email)->toBe('original@example.com');
});

test('changing the email with the correct current password succeeds', function () {
    $this->actingAs($this->user)
        ->patch('/profile', [
            'name' => $this->user->name,
            'email' => 'newaddress@example.com',
            'current_password' => 'correct-horse',
        ])
        ->assertSessionHasNoErrors();

    expect($this->user->refresh()->email)->toBe('newaddress@example.com');
});

test('editing only the name still needs no password', function () {
    $this->actingAs($this->user)
        ->patch('/profile', [
            'name' => 'New Display Name',
            'email' => 'original@example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($this->user->refresh()->name)->toBe('New Display Name');
});
