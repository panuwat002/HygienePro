<?php

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'isolated',
    ]);

    $this->verifier = User::create([
        'name' => 'QA Sup', 'email' => 'qa@example.com',
        'password' => bcrypt('password'), 'role' => 'supervisor', 'level' => 4,
        'department_id' => $this->dept->id,
    ]);
    $this->verifier->forceFill(['email_verified_at' => now()])->save();

    $this->shift = Shift::create([
        'shift_name' => 'morning', 'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $this->checkpoint = Checkpoint::create([
        'title' => 'Hand wash', 'is_active' => true, 'type' => 'person',
    ]);
});

/**
 * One pending group per employee, each on its own session, which is the shape
 * that used to cost one EmployeeSchedule query per group.
 */
function seedPendingGroups($ctx, int $count): void
{
    // Sessions are unique on (inspector, dept, date, shift, round, type), so
    // each group gets its own round rather than colliding on round 1.
    $round = InspectionSession::max('round') ?? 0;

    foreach (range(1, $count) as $i) {
        $round++;
        $employee = Employee::create([
            'employee_id' => "E{$round}", 'fullname' => "Worker {$round}",
            'department_id' => $ctx->dept->id, 'shift_id' => $ctx->shift->id,
            'qr_code_hash' => "h{$round}", 'is_active' => true,
        ]);

        $session = InspectionSession::create([
            'inspector_id' => $ctx->verifier->id,
            'department_id' => $ctx->dept->id,
            'type' => 'personnel',
            'inspection_date' => now()->toDateString(),
            'shift' => 'custom_' . $ctx->shift->id,
            'round' => $round,
            'status' => 'completed',
        ]);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'shift_id' => $ctx->shift->id,
            'date' => now()->toDateString(),
        ]);

        InspectionLog::create([
            'session_id' => $session->id,
            'checkpoint_id' => $ctx->checkpoint->id,
            'employee_id' => $employee->id,
            'result' => 'pass',
            'inspected_at' => now(),
            'verification_status' => null,
        ]);
    }
}

function scheduleQueryCount(callable $work): int
{
    DB::enableQueryLog();
    DB::flushQueryLog();

    $work();

    $queries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'employee_schedules'))
        ->count();

    DB::disableQueryLog();

    return $queries;
}

// The grouping map() looked the roster up per group:
//     EmployeeSchedule::with('shift')->where('employee_id', ...)->first()
// so a page showing 300 groups issued 300 extra queries, which is what made
// /verification crawl as the pending backlog grew.
it('looks the roster up once however many groups are on the page', function () {
    seedPendingGroups($this, 12);

    $queries = scheduleQueryCount(function () {
        $this->actingAs($this->verifier)
            ->get('/verification?tab=pending')
            ->assertSuccessful();
    });

    expect($queries)->toBeLessThanOrEqual(1);
});

it('does not issue more roster queries as groups grow', function () {
    seedPendingGroups($this, 4);
    $small = scheduleQueryCount(function () {
        $this->actingAs($this->verifier)->get('/verification?tab=pending')->assertSuccessful();
    });

    seedPendingGroups($this, 20);
    $large = scheduleQueryCount(function () {
        $this->actingAs($this->verifier)->get('/verification?tab=pending')->assertSuccessful();
    });

    // Flat, not proportional to the number of groups.
    // Flat or better - never proportional to the number of groups.
    expect($large)->toBeLessThanOrEqual($small);
});

// Employee names live in the detail modal, which is fetched rather than
// rendered with the page, so this checks the resolved value on the card
// instead of looking for it in the page's markup.
it('still resolves the roster shift for a card', function () {
    seedPendingGroups($this, 2);

    $response = $this->actingAs($this->verifier)
        ->get('/verification?tab=pending')
        ->assertSuccessful();

    $shifts = collect($response->viewData('groupedInspections')->items())->pluck('shift');

    expect($shifts)->not->toBeEmpty()
        ->and($shifts->unique()->all())->toEqual(['morning']);
});

function tableQueryCount(callable $work, string $table): int
{
    DB::enableQueryLog();
    DB::flushQueryLog();

    $work();

    $n = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], '"' . $table . '"') || str_contains($q['query'], '`' . $table . '`'))
        ->count();

    DB::disableQueryLog();

    return $n;
}

// Two more lookups ran once per group: $employee->shift lazily, and
// getResolvedShifts() re-querying the shifts table for every session it built a
// label for. On a 40-group page that was 80 of the 92 queries the page issued.
it('reads the shifts table a handful of times, not once per group', function () {
    seedPendingGroups($this, 20);

    $n = tableQueryCount(function () {
        $this->actingAs($this->verifier)->get('/verification?tab=pending')->assertSuccessful();
    }, 'shifts');

    expect($n)->toBeLessThanOrEqual(3);
});

it('keeps shift queries flat as groups grow', function () {
    seedPendingGroups($this, 5);
    $small = tableQueryCount(function () {
        $this->actingAs($this->verifier)->get('/verification?tab=pending')->assertSuccessful();
    }, 'shifts');

    seedPendingGroups($this, 25);
    $large = tableQueryCount(function () {
        $this->actingAs($this->verifier)->get('/verification?tab=pending')->assertSuccessful();
    }, 'shifts');

    // Flat or better - never proportional to the number of groups.
    expect($large)->toBeLessThanOrEqual($small);
});
