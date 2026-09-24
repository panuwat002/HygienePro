<?php

/**
 * The bell carried a badge, but at 0.65rem inside p-1 the digit was a red
 * speck - it read as "something happened" rather than "57 things are waiting".
 */

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Str;

function bellUser(string $email = 'qa@example.com'): User
{
    $dept = Department::firstOrCreate(
        ['dept_code' => 'QA'],
        ['dept_name' => 'Quality Assurance', 'visibility_type' => 'isolated']
    );

    $user = User::create([
        'name' => 'QA Sup', 'email' => $email, 'password' => bcrypt('password'),
        'role' => 'supervisor', 'level' => 4, 'department_id' => $dept->id,
    ]);
    $user->forceFill(['email_verified_at' => now()])->save();

    return $user;
}

function notifyTimes(User $user, int $times): void
{
    foreach (range(1, $times) as $i) {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\PendingVerificationDigestNotification',
            'data' => ['title' => "แจ้งเตือน {$i}", 'message' => 'รอทวนสอบ', 'icon' => 'bi-bell'],
        ]);
    }
}

it('prints the number of unread notifications on the bell', function () {
    $user = bellUser();
    notifyTimes($user, 7);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('data-unread-count="7"', false);
});

it('caps a large count rather than stretching the bell', function () {
    $user = bellUser();
    notifyTimes($user, 120);

    $html = $this->actingAs($user)->get('/dashboard')->assertSuccessful()->getContent();

    expect($html)->toContain('data-unread-count="120"')
        ->and($html)->toContain('99+');
});

it('shows no badge when nothing is unread', function () {
    $user = bellUser();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertDontSee('data-unread-count', false);
});

it('drops the badge once everything is read', function () {
    $user = bellUser();
    notifyTimes($user, 3);
    $user->unreadNotifications->markAsRead();

    $this->actingAs($user->fresh())
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertDontSee('data-unread-count', false);
});
