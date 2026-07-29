# Auto-close Forgotten Inspection Sessions — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automatically close inspection sessions that were left open past their shift end, so Admin no longer has to manually force-close forgotten rounds.

**Architecture:** Core logic lives in one service method (`InspectionService::autoCloseStaleSessions()`), triggered two ways: a throttled "heartbeat" on dashboard load (primary — works without cron) and a scheduled artisan command (bonus — fires if Windows Task Scheduler runs `schedule:run`). A session is closed only when it is past `shift.end_time + grace` AND has had no inspection activity for `idle` minutes (protects inspectors still working past shift). Closing reuses the existing `finishSession()` and records a system entry in `activity_logs`. No schema changes.

**Tech Stack:** Laravel 11, PHP 8.2, Pest 3 (sqlite `:memory:` for tests), MySQL (prod).

## Global Constraints

- No database migration / no schema change. Reuse `activity_logs` and existing session fields.
- Fail-safe: the dashboard heartbeat MUST be wrapped in `try/catch (\Throwable)` and must never break the page. Unknown shift end ⇒ never auto-close.
- Idempotent: safe to call repeatedly. `finishSession()` returns early if already `completed`.
- Config-driven defaults (override via `.env`): `grace_hours=2`, `idle_minutes=30`, `heartbeat_minutes=10`, `enabled=true` (kill-switch).
- User-facing strings are Thai (matches the app).
- Follow existing test style: Pest `test('...', function () {...})`, `Model::create([...])`, `Carbon::setTestNow()` for time control.

---

### Task 1: Auto-close logic in `InspectionService` (+ config)

**Files:**
- Create: `config/inspection.php`
- Modify: `.env.example` (append config keys)
- Modify: `app/Services/InspectionService.php` (append methods to the class)
- Test: `tests/Feature/AutoCloseStaleSessionsTest.php` (create)

**Interfaces:**
- Produces:
  - `InspectionService::autoCloseStaleSessions(): int` — closes all stale sessions, returns count closed.
  - `InspectionService::isStale(App\Models\InspectionSession $session): bool`
  - `InspectionService::shiftEndAt(App\Models\InspectionSession $session): ?Illuminate\Support\Carbon`
  - `InspectionService::lastActivityAt(App\Models\InspectionSession $session): Illuminate\Support\Carbon`
- Consumes: existing `InspectionService::finishSession(InspectionSession $session): void`.

- [ ] **Step 1: Create the config file**

Create `config/inspection.php`:

```php
<?php

return [
    'auto_close' => [
        'enabled'           => env('AUTO_CLOSE_ENABLED', true),      // kill-switch
        'grace_hours'       => env('AUTO_CLOSE_GRACE_HOURS', 2),     // close this long after shift end
        'idle_minutes'      => env('AUTO_CLOSE_IDLE_MINUTES', 30),   // defer if activity within this window
        'heartbeat_minutes' => env('AUTO_CLOSE_HEARTBEAT_MINUTES', 10), // dashboard throttle
    ],
];
```

- [ ] **Step 2: Document the env keys**

Append to `.env.example`:

```
AUTO_CLOSE_ENABLED=true
AUTO_CLOSE_GRACE_HOURS=2
AUTO_CLOSE_IDLE_MINUTES=30
AUTO_CLOSE_HEARTBEAT_MINUTES=10
```

- [ ] **Step 3: Write the failing tests**

Create `tests/Feature/AutoCloseStaleSessionsTest.php`:

```php
<?php

use App\Models\Department;
use App\Models\User;
use App\Models\Shift;
use App\Models\InspectionSession;
use App\Models\InspectionLog;
use App\Models\Checkpoint;
use App\Models\Employee;
use App\Models\ActivityLog;
use App\Services\InspectionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();

    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);

    $this->inspector = User::create([
        'name' => 'Inspector', 'email' => 'inspector@example.com',
        'password' => bcrypt('password'), 'role' => 'staff', 'level' => 2,
        'department_id' => $this->dept->id,
    ]);
    $this->inspector->forceFill(['email_verified_at' => now()])->save();

    // Morning shift: 06:00 - 13:00
    Shift::create(['shift_name' => 'morning', 'start_time' => '06:00:00', 'end_time' => '13:00:00']);

    $this->service = app(InspectionService::class);
});

afterEach(function () {
    Carbon::setTestNow(); // reset frozen time
});

function makeOpenSession($ctx, string $shift = 'morning', string $date = '2026-07-29'): InspectionSession {
    return InspectionSession::create([
        'inspector_id'    => $ctx->inspector->id,
        'department_id'   => $ctx->dept->id,
        'type'            => 'personnel',
        'inspection_date' => $date,
        'shift'           => $shift,
        'round'           => 1,
        'status'          => 'in_progress',
        'is_locked'       => false,
    ]);
}

test('auto-closes a session left open past shift end + grace with no activity', function () {
    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this);

    // Now 16:00: shift ended 13:00, +2h grace = 15:00, last activity 12:00 (>30m idle)
    Carbon::setTestNow('2026-07-29 16:00:00');

    expect($this->service->autoCloseStaleSessions())->toBe(1);
    expect($session->refresh()->status)->toBe('completed');
    expect(
        ActivityLog::where('model_id', $session->id)
            ->where('action', 'auto_close')
            ->whereNull('user_id')
            ->exists()
    )->toBeTrue();
});

test('does not close a session still within shift end + grace', function () {
    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this);

    Carbon::setTestNow('2026-07-29 14:30:00'); // 13:00 + 2h = 15:00, not reached
    expect($this->service->autoCloseStaleSessions())->toBe(0);
    expect($session->refresh()->status)->toBe('in_progress');
});

test('does not close a session with recent inspection activity', function () {
    $cp  = Checkpoint::create(['title' => 'Nails', 'is_active' => true, 'type' => 'person']);
    $emp = Employee::create([
        'employee_id' => 'E1', 'fullname' => 'Somchai', 'department_id' => $this->dept->id,
        'qr_code_hash' => 'h1', 'is_active' => true,
    ]);

    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this);

    // Activity at 15:50 — within 30m of evaluation time
    Carbon::setTestNow('2026-07-29 15:50:00');
    InspectionLog::create([
        'session_id' => $session->id, 'checkpoint_id' => $cp->id, 'employee_id' => $emp->id,
        'result' => 'pass', 'inspected_at' => now(),
    ]);

    Carbon::setTestNow('2026-07-29 16:00:00'); // past grace, but last activity 15:50 > 15:30
    expect($this->service->autoCloseStaleSessions())->toBe(0);
    expect($session->refresh()->status)->toBe('in_progress');
});

test('ignores completed and locked sessions', function () {
    Carbon::setTestNow('2026-07-29 16:00:00');

    $completed = makeOpenSession($this);
    $completed->update(['status' => 'completed']);

    $locked = makeOpenSession($this);
    $locked->update(['is_locked' => true]);

    expect($this->service->autoCloseStaleSessions())->toBe(0);
});

test('does not close when shift end time is unknown (fail-safe)', function () {
    // No 'night' shift row exists -> shiftEndAt() returns null
    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this, 'night');

    Carbon::setTestNow('2026-07-29 23:00:00');
    expect($this->service->autoCloseStaleSessions())->toBe(0);
    expect($session->refresh()->status)->toBe('in_progress');
});

test('handles a night shift that wraps past midnight', function () {
    Shift::create(['shift_name' => 'night', 'start_time' => '22:00:00', 'end_time' => '06:00:00']);

    Carbon::setTestNow('2026-07-28 23:00:00');
    $session = makeOpenSession($this, 'night', '2026-07-28');

    // Shift end = 2026-07-29 06:00; +2h grace = 08:00
    Carbon::setTestNow('2026-07-29 09:00:00');
    expect($this->service->autoCloseStaleSessions())->toBe(1);
    expect($session->refresh()->status)->toBe('completed');
});
```

- [ ] **Step 4: Run the tests to verify they fail**

Run: `php artisan test --filter=AutoCloseStaleSessionsTest`
Expected: FAIL — `Call to undefined method App\Services\InspectionService::autoCloseStaleSessions()`

- [ ] **Step 5: Implement the service methods**

Append these methods inside the `InspectionService` class in `app/Services/InspectionService.php` (e.g. right after `finishSession()`). `InspectionSession` and `InspectionLog` are already imported at the top of the file; `Shift` and `ActivityLog` are referenced fully-qualified.

```php
    public function autoCloseStaleSessions(): int
    {
        if (! config('inspection.auto_close.enabled', true)) {
            return 0;
        }

        $sessions = InspectionSession::whereIn('status', ['in_progress', 'paused'])
            ->where('is_locked', false)
            ->get();

        $closed = 0;
        foreach ($sessions as $session) {
            if (! $this->isStale($session)) {
                continue;
            }

            $this->finishSession($session);

            \App\Models\ActivityLog::create([
                'user_id'     => null, // system actor
                'action'      => 'auto_close',
                'model_type'  => InspectionSession::class,
                'model_id'    => $session->id,
                'description' => 'ปิดรอบอัตโนมัติโดยระบบ (เลยกะ ' . $session->shift . ' + '
                    . config('inspection.auto_close.grace_hours', 2) . ' ชม. และไม่มีการตรวจใน '
                    . config('inspection.auto_close.idle_minutes', 30) . ' นาที)',
            ]);

            $closed++;
        }

        return $closed;
    }

    public function isStale(InspectionSession $session): bool
    {
        if ($session->isLocked() || ! in_array($session->status, ['in_progress', 'paused'], true)) {
            return false;
        }

        $shiftEnd = $this->shiftEndAt($session);
        if ($shiftEnd === null) {
            return false; // fail-safe: unknown shift end -> never auto-close
        }

        $graceHours = (float) config('inspection.auto_close.grace_hours', 2);
        if (now()->lt($shiftEnd->copy()->addHours($graceHours))) {
            return false; // still within shift + grace
        }

        $idleMinutes = (int) config('inspection.auto_close.idle_minutes', 30);
        if ($this->lastActivityAt($session)->gt(now()->copy()->subMinutes($idleMinutes))) {
            return false; // recent activity -> defer close
        }

        return true;
    }

    public function shiftEndAt(InspectionSession $session): ?\Illuminate\Support\Carbon
    {
        $names = match (strtolower((string) $session->shift)) {
            'morning'   => ['morning', 'กะเช้า'],
            'afternoon' => ['afternoon', 'กะบ่าย'],
            'night'     => ['night', 'กะดึก'],
            default     => [(string) $session->shift],
        };

        $shift = \App\Models\Shift::whereIn('shift_name', $names)->first();
        if (! $shift || empty($shift->end_time)) {
            return null;
        }

        $date = \Illuminate\Support\Carbon::parse($session->inspection_date)->toDateString();
        $end  = \Illuminate\Support\Carbon::parse($date . ' ' . $shift->end_time);

        // Shift wraps past midnight (e.g. night 22:00-06:00): end lands on the next day
        if (! empty($shift->start_time) && $shift->end_time <= $shift->start_time) {
            $end->addDay();
        }

        return $end;
    }

    public function lastActivityAt(InspectionSession $session): \Illuminate\Support\Carbon
    {
        $lastLog = InspectionLog::where('session_id', $session->id)->max('created_at');
        if ($lastLog) {
            return \Illuminate\Support\Carbon::parse($lastLog);
        }

        return \Illuminate\Support\Carbon::parse($session->updated_at ?? $session->created_at ?? now());
    }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=AutoCloseStaleSessionsTest`
Expected: PASS (6 tests)

- [ ] **Step 7: Commit**

```bash
git add config/inspection.php .env.example app/Services/InspectionService.php tests/Feature/AutoCloseStaleSessionsTest.php
git commit -m "feat: add auto-close-stale-sessions logic to InspectionService"
```

---

### Task 2: Artisan command + scheduler entry

**Files:**
- Create: `app/Console/Commands/AutoCloseStaleSessions.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/AutoCloseStaleSessionsTest.php` (add one test)

**Interfaces:**
- Produces: artisan command signature `inspections:auto-close-stale`.
- Consumes: `InspectionService::autoCloseStaleSessions(): int` (Task 1).

- [ ] **Step 1: Write the failing test**

Add this test to `tests/Feature/AutoCloseStaleSessionsTest.php`:

```php
test('the inspections:auto-close-stale command closes stale sessions', function () {
    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this);

    Carbon::setTestNow('2026-07-29 16:00:00');

    $this->artisan('inspections:auto-close-stale')
        ->expectsOutputToContain('Auto-closed 1')
        ->assertExitCode(0);

    expect($session->refresh()->status)->toBe('completed');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter="command closes stale sessions"`
Expected: FAIL — command `inspections:auto-close-stale` is not defined.

- [ ] **Step 3: Create the command**

Create `app/Console/Commands/AutoCloseStaleSessions.php` (Laravel 11 auto-discovers commands in this directory — no manual registration needed):

```php
<?php

namespace App\Console\Commands;

use App\Services\InspectionService;
use Illuminate\Console\Command;

class AutoCloseStaleSessions extends Command
{
    protected $signature = 'inspections:auto-close-stale';

    protected $description = 'Auto-close inspection sessions left open past their shift end + grace period';

    public function handle(InspectionService $service): int
    {
        $count = $service->autoCloseStaleSessions();
        $this->info("Auto-closed {$count} stale inspection session(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter="command closes stale sessions"`
Expected: PASS

- [ ] **Step 5: Register the scheduled run (bonus trigger)**

`routes/console.php` already imports `Illuminate\Support\Facades\Schedule`. Append below the existing `schedule:check-missed` line:

```php
Schedule::command('inspections:auto-close-stale')->everyFifteenMinutes();
```

- [ ] **Step 6: Verify the command is registered**

Run: `php artisan schedule:list`
Expected: shows `inspections:auto-close-stale` running every 15 minutes.
(Also confirm: `php artisan list | grep auto-close` shows the command.)

- [ ] **Step 7: Commit**

```bash
git add app/Console/Commands/AutoCloseStaleSessions.php routes/console.php tests/Feature/AutoCloseStaleSessionsTest.php
git commit -m "feat: add inspections:auto-close-stale command and schedule"
```

---

### Task 3: Dashboard heartbeat trigger (primary)

**Files:**
- Modify: `app/Http/Controllers/InspectionController.php` (method `home()`, starts at line 28)
- Test: `tests/Feature/AutoCloseStaleSessionsTest.php` (add one test)

**Interfaces:**
- Consumes: `$this->inspectionService->autoCloseStaleSessions()` (Task 1); `config('inspection.auto_close.*')`.
- Produces: no new public API — a side effect on `GET /dashboard`.

- [ ] **Step 1: Write the failing test**

Add this test to `tests/Feature/AutoCloseStaleSessionsTest.php`:

```php
test('loading the dashboard triggers the auto-close heartbeat', function () {
    Carbon::setTestNow('2026-07-29 12:00:00');
    $session = makeOpenSession($this);

    Carbon::setTestNow('2026-07-29 16:00:00');

    // The heartbeat runs at the very top of home(), before any view rendering,
    // so we assert the side effect (session closed) regardless of the page response.
    $this->actingAs($this->inspector)->get(route('dashboard'));

    expect($session->refresh()->status)->toBe('completed');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter="dashboard triggers the auto-close heartbeat"`
Expected: FAIL — session stays `in_progress` (heartbeat not wired yet).

- [ ] **Step 3: Add the heartbeat to `home()`**

In `app/Http/Controllers/InspectionController.php`, insert this block as the **first statements** inside `home()` (immediately after `public function home()\n    {`, before `$today = now()->toDateString();`):

```php
        // Heartbeat: opportunistically auto-close stale sessions.
        // Throttled via cache; wrapped so it can NEVER break the dashboard.
        try {
            $heartbeatMinutes = (int) config('inspection.auto_close.heartbeat_minutes', 10);
            if (config('inspection.auto_close.enabled', true)
                && \Illuminate\Support\Facades\Cache::add('auto_close_heartbeat', 1, now()->addMinutes($heartbeatMinutes))) {
                $this->inspectionService->autoCloseStaleSessions();
            }
        } catch (\Throwable $e) {
            \Log::error('auto-close heartbeat failed: ' . $e->getMessage());
        }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter="dashboard triggers the auto-close heartbeat"`
Expected: PASS

- [ ] **Step 5: Run the whole feature test file**

Run: `php artisan test --filter=AutoCloseStaleSessionsTest`
Expected: PASS (8 tests total)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/InspectionController.php tests/Feature/AutoCloseStaleSessionsTest.php
git commit -m "feat: trigger auto-close via throttled dashboard heartbeat"
```

---

## Deployment / manual verification (after all tasks)

1. Load the new config on the server (XAMPP php + IIS):
   ```bash
   php artisan config:clear
   ```
   (If the app uses `php artisan config:cache`, run `config:cache` instead to re-cache.)
2. Optional `.env` overrides on the server, e.g. `AUTO_CLOSE_GRACE_HOURS=1`. Kill-switch: `AUTO_CLOSE_ENABLED=false`.
3. Manual smoke test of the heartbeat: with an `in_progress` session whose shift ended more than `grace_hours` ago and no recent logs, open `/dashboard` → the session should flip to `completed`, and Admin → Activity Logs should show a `auto_close` entry ("ปิดรอบอัตโนมัติโดยระบบ ...").
4. (Bonus) If/when a Windows Task Scheduler task runs `php artisan schedule:run` every minute, the `everyFifteenMinutes()` job will also close stale sessions without anyone opening the dashboard.

## Self-review notes (coverage vs spec)

- Rule (§3 of spec): `isStale()` covers status/lock, shift-end+grace, idle-guard, and the fail-safe null shift end. Tests cover each branch + night wrap. ✅
- Behavior on close (§4): reuses `finishSession()` (status + supervisor notify), leaves targets untouched, writes `activity_logs` with `user_id=null`, `action=auto_close`. ✅
- Trigger A+B (§5): command + `everyFifteenMinutes()` schedule (Task 2); throttled `try/catch` heartbeat in `home()` (Task 3). ✅
- Config (§6): `config/inspection.php` + `.env.example`, all four keys with defaults 2h/30m/10m/enabled. ✅
- No migration (§7): confirmed — only `activity_logs` reused. ✅
