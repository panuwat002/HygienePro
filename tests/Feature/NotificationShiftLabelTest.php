<?php

/**
 * "custom_11" is a database key, not a shift.
 *
 * Shifts stopped being three hardcoded strings when the shifts table arrived;
 * a session now stores "custom_<id>" and InspectionSession::$shift_label
 * resolves it. Six places never got the message and print the raw column, so
 * a supervisor's bell reads "งานตรวจกะ custom_11" - or worse, "Custom_11",
 * because one of them runs ucfirst() over the fallback.
 */

use App\Models\Department;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\InspectionRejectedNotification;
use App\Notifications\PendingVerificationNotification;
use App\Notifications\SessionCarsSummaryNotification;
use App\Notifications\VerificationEscalatedNotification;
use App\Notifications\VerificationReminderNotification;

beforeEach(function () {
    // Pinned because the fixtures name a fixed inspection_date. Without this the
    // auto-close case below wrote updated_at at the real clock and then rewound
    // now() to 2026-09-24, so the session read as "active a moment ago" and was
    // never closed - green on the day it was written, red the next morning.
    Illuminate\Support\Carbon::setTestNow('2026-09-24 09:00:00');

    $this->dept = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $this->inspector = User::create([
        'name' => 'Ketmanee', 'email' => 'shift-label@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->dept->id,
    ]);

    // What a session actually stores once a shift is a row rather than a string.
    $this->session = InspectionSession::create([
        'inspector_id' => $this->inspector->id, 'department_id' => $this->dept->id,
        'type' => 'personnel', 'inspection_date' => '2026-09-24',
        'shift' => 'custom_' . $this->shift->id, 'round' => 1, 'status' => 'completed',
    ]);
});

afterEach(function () {
    Illuminate\Support\Carbon::setTestNow();
});

dataset('shift-naming notifications', [
    'rejected' => [fn ($ctx) => new InspectionRejectedNotification($ctx->session, 'พื้นไม่สะอาด')],
    'pending verification' => [fn ($ctx) => new PendingVerificationNotification($ctx->session)],
    'verification reminder' => [fn ($ctx) => new VerificationReminderNotification($ctx->session)],
    'verification escalated' => [fn ($ctx) => new VerificationEscalatedNotification($ctx->session)],
    'session CAR summary' => [fn ($ctx) => new SessionCarsSummaryNotification($ctx->session, [
        ['place' => 'ห้องบรรจุ'],
    ])],
]);

it('names the shift instead of leaking its key', function (Closure $build) {
    $payload = $build($this)->toArray($this->inspector);
    $text = implode(' ', array_filter($payload, 'is_string'));

    expect($text)
        ->toContain('กะเช้า 08.00-17.00')
        ->not->toContain('custom_')
        ->not->toContain('Custom_');
})->with('shift-naming notifications');

it('does not end up saying the word กะ twice', function (Closure $build) {
    $payload = $build($this)->toArray($this->inspector);
    $text = implode(' ', array_filter($payload, 'is_string'));

    expect($text)->not->toContain('กะกะ');
})->with('shift-naming notifications');

/**
 * The same key leaks into the audit trail, which is the one place that has to
 * still make sense to a reader months later.
 */
it('names the shift in the auto-close audit entry', function () {
    $this->session->update(['status' => 'in_progress']);

    // Shift ends 17:00; the grace window is two hours, so 20:00 is stale.
    Illuminate\Support\Carbon::setTestNow('2026-09-24 20:00:00');

    app(App\Services\InspectionService::class)->autoCloseStaleSessions();

    Illuminate\Support\Carbon::setTestNow();

    $entry = App\Models\ActivityLog::where('action', 'auto_close')
        ->where('model_id', $this->session->id)
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->description)->toContain('กะเช้า 08.00-17.00')
        ->and($entry->description)->not->toContain('custom_');
});
