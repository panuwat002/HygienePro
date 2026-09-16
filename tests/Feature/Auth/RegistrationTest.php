<?php

/**
 * Public self-registration was removed: accounts are provisioned by an admin at /users.
 * The Breeze scaffolding tests that used to live here asserted the opposite ("the
 * registration screen can be rendered", "new users can register"), so they are replaced
 * rather than deleted outright — the behaviour they documented is now a defect.
 *
 * The replacement coverage lives in
 * tests/Feature/Security/RegistrationDisabledTest.php, which also checks that login and
 * password reset are unaffected.
 */

use App\Models\User;

test('public registration is not available', function () {
    $this->get('/register')->assertNotFound();

    $before = User::count();

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::count())->toBe($before);
});
