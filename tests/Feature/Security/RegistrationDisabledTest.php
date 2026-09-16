<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Public self-registration handed a working account to anyone who could reach the host:
 * the users table defaults role to 'staff' and level to 2 (see the add_matrix_columns
 * migration), so a registrant was not an inert null-role user but a real staff account,
 * and RegisteredUserController::store() logs them straight in. User does not implement
 * MustVerifyEmail, so the `verified` middleware on /dashboard gates nothing.
 *
 * Accounts are provisioned by an admin at /users instead.
 */
test('the registration page is not reachable', function () {
    $this->get('/register')->assertNotFound();
});

test('posting a registration does not create an account', function () {
    $before = User::count();

    $this->post('/register', [
        'name' => 'Walk In',
        'email' => 'walkin@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertNotFound();

    expect(User::count())->toBe($before)
        ->and(User::where('email', 'walkin@example.com')->exists())->toBeFalse();
});

test('login is still reachable', function () {
    $this->get('/login')->assertOk();
});

test('password reset is still reachable', function () {
    $this->get('/forgot-password')->assertOk();
});
