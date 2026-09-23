<?php

/**
 * Characterisation tests for the /verification page.
 *
 * These lock the page's visible output - which group lands in which tab, what
 * the badges count, how groups are keyed and ordered, and what a page holds -
 * so the query underneath can be rewritten without anyone noticing.
 *
 * They were written against the existing implementation and must keep passing
 * unchanged afterwards.
 */

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Shift;
use App\Models\User;

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

    $this->personCheckpoint = Checkpoint::create([
        'title' => 'Hand wash', 'is_active' => true, 'type' => 'person',
    ]);
    $this->areaCheckpoint = Checkpoint::create([
        'title' => 'Floor clean', 'is_active' => true, 'type' => 'area',
    ]);

    $this->round = 0;
    $this->clock = 0;
});

function newSession($ctx, string $type): InspectionSession
{
    $ctx->round++;

    return InspectionSession::create([
        'inspector_id' => $ctx->verifier->id,
        'department_id' => $ctx->dept->id,
        'type' => $type,
        'inspection_date' => now()->toDateString(),
        'shift' => 'custom_' . $ctx->shift->id,
        'round' => $ctx->round,
        'status' => 'completed',
    ]);
}

/**
 * One entry per log: 'verified', 'approved', 'auto_verified', 'reclean',
 * 'rejected', or null. A null status also leaves verified_at unset, which is
 * what the page reads as "still waiting for QA".
 */
function personGroup($ctx, array $specs): InspectionSession
{
    $session = newSession($ctx, 'personnel');

    $employee = Employee::create([
        'employee_id' => 'E' . $ctx->round, 'fullname' => 'Worker ' . $ctx->round,
        'department_id' => $ctx->dept->id, 'shift_id' => $ctx->shift->id,
        'qr_code_hash' => 'h' . $ctx->round, 'is_active' => true,
    ]);

    foreach ($specs as $status) {
        InspectionLog::create([
            'session_id' => $session->id,
            'checkpoint_id' => $ctx->personCheckpoint->id,
            'employee_id' => $employee->id,
            'result' => 'pass',
            'inspected_at' => now()->subSeconds(1000 - (++$ctx->clock)),
            'verification_status' => $status,
            'verified_at' => $status === null ? null : now(),
            'verifier_id' => $status === null ? null : $ctx->verifier->id,
        ]);
    }

    return $session;
}

function stockedLocation(string $name): Location
{
    $location = Location::create(['location_name' => $name]);

    // The page drops logs whose location has neither checkpoints nor machines,
    // so a location used in a fixture has to carry at least one of them.
    $location->checkpoints()->attach(Checkpoint::where('type', 'area')->first()->id);

    return $location;
}

function areaGroup($ctx, Location $location, array $specs, ?InspectionSession $session = null): InspectionSession
{
    $session ??= newSession($ctx, 'area');

    foreach ($specs as $status) {
        InspectionLog::create([
            'session_id' => $session->id,
            'checkpoint_id' => $ctx->areaCheckpoint->id,
            'employee_id' => null,
            'location_id' => $location->id,
            'result' => 'pass',
            'inspected_at' => now()->subSeconds(1000 - (++$ctx->clock)),
            'verification_status' => $status,
            'verified_at' => $status === null ? null : now(),
        ]);
    }

    return $session;
}

function machineGroup($ctx, Machine $machine, array $specs): InspectionSession
{
    $session = newSession($ctx, 'machine');

    foreach ($specs as $status) {
        InspectionLog::create([
            'session_id' => $session->id,
            'checkpoint_id' => $ctx->areaCheckpoint->id,
            'employee_id' => null,
            'location_id' => null,
            'machine_id' => $machine->id,
            'result' => 'pass',
            'inspected_at' => now()->subSeconds(1000 - (++$ctx->clock)),
            'verification_status' => $status,
            'verified_at' => $status === null ? null : now(),
        ]);
    }

    return $session;
}

function pageData($ctx, string $query): array
{
    $response = $ctx->actingAs($ctx->verifier)->get('/verification?' . $query);
    $response->assertSuccessful();

    $paginator = $response->viewData('groupedInspections');

    return [
        'groups' => collect($paginator->items()),
        'counts' => $response->viewData('counts'),
        'typeCounts' => $response->viewData('typeCounts'),
        'total' => $paginator->total(),
    ];
}

it('files each group under the status its logs add up to', function () {
    personGroup($this, ['verified', 'verified']);          // -> verified
    personGroup($this, ['approved', 'approved']);          // -> approved
    personGroup($this, ['auto_verified']);                 // -> auto_verified
    personGroup($this, ['approved', 'auto_verified']);     // -> approved (mixed)
    personGroup($this, ['verified', null]);                // -> pending
    personGroup($this, ['reclean', 'reclean']);            // -> reclean
    personGroup($this, ['verified', null]);                // -> pending

    $statuses = pageData($this, 'filter_type=person&tab=all')['groups']
        ->pluck('verification_status')->countBy()->all();

    expect($statuses)->toEqual([
        'verified' => 1,
        'approved' => 2,
        'auto_verified' => 1,
        'pending' => 2,
        'reclean' => 1,
    ]);
});

/**
 * KNOWN GAP, recorded so the rewrite does not change it by accident.
 *
 * reject() stamps verified_at alongside verification_status = 'rejected', and
 * inbox mode asks for verified_at IS NULL or a status of reclean/verified - so
 * a rejected log matches none of them and is never loaded. The group therefore
 * reads as 'verified' off its remaining logs, and rejected work is invisible
 * until someone picks a date range. Worth fixing, but on its own commit: it is
 * a correctness bug, not the performance one this rewrite is about.
 */
it('currently hides a rejected log in inbox mode', function () {
    personGroup($this, ['verified', 'rejected']);

    $groups = pageData($this, 'filter_type=person&tab=all')['groups'];

    expect($groups)->toHaveCount(1)
        ->and($groups->first()->verification_status)->toBe('verified')
        ->and($groups->first()->all_logs)->toHaveCount(1);
});

it('counts the badges for the chosen category, not the chosen tab', function () {
    personGroup($this, ['verified']);
    personGroup($this, ['approved']);
    personGroup($this, ['auto_verified']);
    personGroup($this, ['verified', null]);
    personGroup($this, ['reclean']);

    // Same numbers whichever tab is open: the badges describe the category.
    foreach (['pending', 'completed', 'reclean'] as $tab) {
        expect(pageData($this, "filter_type=person&tab={$tab}")['counts'])
            ->toEqual(['pending' => 1, 'completed' => 3, 'reclean' => 1, 'total' => 5]);
    }
});

it('counts both categories for the open tab', function () {
    $location = stockedLocation('Packing room');

    personGroup($this, ['verified', null]);    // person, pending
    personGroup($this, ['verified', null]);    // person, pending
    personGroup($this, ['verified']);          // person, completed
    areaGroup($this, $location, [null]);       // area, pending
    areaGroup($this, $location, ['verified']); // area, completed

    expect(pageData($this, 'filter_type=person&tab=pending')['typeCounts'])
        ->toEqual(['person' => 2, 'machine' => 1]);

    expect(pageData($this, 'filter_type=person&tab=completed')['typeCounts'])
        ->toEqual(['person' => 1, 'machine' => 1]);
});

it('shows only the open tab in the table', function () {
    personGroup($this, ['verified']);
    personGroup($this, ['verified', null]);
    personGroup($this, ['reclean']);

    expect(pageData($this, 'filter_type=person&tab=pending')['groups']->pluck('verification_status')->all())
        ->toEqual(['pending']);
    expect(pageData($this, 'filter_type=person&tab=reclean')['groups']->pluck('verification_status')->all())
        ->toEqual(['reclean']);
    expect(pageData($this, 'filter_type=person&tab=completed')['groups']->pluck('verification_status')->all())
        ->toEqual(['verified']);
});

it('keeps a person round in one group and never on the area tab', function () {
    personGroup($this, ['verified', 'verified', 'verified']);

    $person = pageData($this, 'filter_type=person&tab=completed');
    expect($person['groups'])->toHaveCount(1)
        ->and($person['groups']->first()->type)->toBe('person')
        ->and($person['groups']->first()->all_logs)->toHaveCount(3);

    expect(pageData($this, 'filter_type=machine&tab=completed')['groups'])->toHaveCount(0);
});

it('splits one area round into a group per location', function () {
    $packing = stockedLocation('Packing room');
    $cold = stockedLocation('Cold store');

    $session = areaGroup($this, $packing, ['verified', 'verified']);
    areaGroup($this, $cold, ['verified'], $session);

    $groups = pageData($this, 'filter_type=machine&tab=completed')['groups'];

    expect($groups)->toHaveCount(2)
        ->and($groups->pluck('name')->sort()->values()->all())
        ->toEqual(['Cold store', 'Packing room']);
});

it('groups a machine round by the location the machine sits in', function () {
    $location = stockedLocation('Packing room');
    $mixer = Machine::create(['location_id' => $location->id, 'name' => 'Mixer', 'is_active' => true]);

    machineGroup($this, $mixer, ['verified', 'verified']);

    $groups = pageData($this, 'filter_type=machine&tab=completed')['groups'];

    expect($groups)->toHaveCount(1)
        ->and($groups->first()->type)->toBe('machine')
        ->and($groups->first()->name)->toContain('Packing room');
});

it('puts the most recent round first', function () {
    $oldest = personGroup($this, ['verified']);
    $middle = personGroup($this, ['verified']);
    $newest = personGroup($this, ['verified']);

    expect(pageData($this, 'filter_type=person&tab=completed')['groups']->pluck('session_id')->all())
        ->toEqual([$newest->id, $middle->id, $oldest->id]);
});

it('fills a page with twenty groups and reports the true total', function () {
    foreach (range(1, 23) as $i) {
        personGroup($this, ['verified']);
    }

    $first = pageData($this, 'filter_type=person&tab=completed');
    expect($first['groups'])->toHaveCount(20)
        ->and($first['total'])->toBe(23)
        ->and($first['counts']['completed'])->toBe(23);

    expect(pageData($this, 'filter_type=person&tab=completed&page=2')['groups'])->toHaveCount(3);
});

it('does not show a page of one tab on another tab', function () {
    foreach (range(1, 23) as $i) {
        personGroup($this, ['verified']);
    }
    personGroup($this, [null]);

    $pending = pageData($this, 'filter_type=person&tab=pending');
    expect($pending['groups'])->toHaveCount(1)
        ->and($pending['total'])->toBe(1);
});

it('carries the details each row renders', function () {
    personGroup($this, ['verified', null]);

    $group = pageData($this, 'filter_type=person&tab=pending')['groups']->first();

    expect($group->inspector_name)->toBe('QA Sup')
        ->and($group->shift)->toBe('morning')
        ->and($group->round)->toBe(1)
        ->and($group->hygiene_score)->toBe(100)
        ->and($group->traffic_light)->toBe('green')
        ->and($group->log_ids)->toHaveCount(2)
        ->and($group->subtext)->toBe('Quality Assurance');
});

it('leaves out a location that has neither checkpoints nor machines', function () {
    $empty = Location::create(['location_name' => 'Decommissioned bay']);

    $session = newSession($this, 'area');
    InspectionLog::create([
        'session_id' => $session->id,
        'checkpoint_id' => $this->areaCheckpoint->id,
        'location_id' => $empty->id,
        'result' => 'pass',
        'inspected_at' => now(),
        'verification_status' => 'verified',
        'verified_at' => now(),
    ]);

    expect(pageData($this, 'filter_type=machine&tab=completed')['groups'])->toHaveCount(0);
});

/**
 * The reason this page was rewritten.
 *
 * It renders 20 groups, but it used to hydrate every log the inbox predicate
 * matched in order to find those 20 - on the UAT database, 17,993 rows for a
 * page that showed twenty. Since a verified round leaves that set only when a
 * manager approves it, the cost grew with every round the system had ever run
 * and never came back down.
 *
 * These tests measure the rows actually turned into models, and assert the
 * number tracks the page, not the backlog.
 */
it('loads no more logs as the verified backlog grows', function () {
    $counter = new stdClass();
    $counter->n = 0;
    InspectionLog::retrieved(function () use ($counter) {
        $counter->n++;
    });

    personGroup($this, ['verified', null]);      // the one pending group
    foreach (range(1, 5) as $i) {
        personGroup($this, ['verified', 'verified']);
    }

    $counter->n = 0;
    $this->actingAs($this->verifier)->get('/verification?filter_type=person&tab=pending')->assertSuccessful();
    $small = $counter->n;

    // Forty more finished rounds nobody has approved: exactly what accumulates
    // in production, and exactly what used to be loaded on every page view.
    foreach (range(1, 40) as $i) {
        personGroup($this, ['verified', 'verified']);
    }

    $counter->n = 0;
    $this->actingAs($this->verifier)->get('/verification?filter_type=person&tab=pending')->assertSuccessful();
    $large = $counter->n;

    expect($large)->toBe($small)
        ->and($large)->toBeLessThan(20);
});

it('loads a page worth of logs, not a tab worth', function () {
    $counter = new stdClass();
    $counter->n = 0;
    InspectionLog::retrieved(function () use ($counter) {
        $counter->n++;
    });

    // 60 groups of 3 logs on one tab: 180 logs, of which one page needs 60.
    foreach (range(1, 60) as $i) {
        personGroup($this, ['verified', 'verified', 'verified']);
    }

    $counter->n = 0;
    $this->actingAs($this->verifier)->get('/verification?filter_type=person&tab=completed')->assertSuccessful();

    // 20 groups x 3 logs, with room for the odd extra; nowhere near all 180.
    expect($counter->n)->toBeLessThanOrEqual(70);
});

it('does not load the other category', function () {
    $counter = new stdClass();
    $counter->n = 0;
    InspectionLog::retrieved(function () use ($counter) {
        $counter->n++;
    });

    $location = stockedLocation('Packing room');
    foreach (range(1, 30) as $i) {
        areaGroup($this, $location, ['verified', 'verified']);
    }
    personGroup($this, ['verified', null]);

    $counter->n = 0;
    $this->actingAs($this->verifier)->get('/verification?filter_type=person&tab=pending')->assertSuccessful();

    // filter_type used to be applied in PHP, after every area log had been
    // loaded and grouped alongside the person ones.
    expect($counter->n)->toBeLessThan(10);
});

/**
 * The card's counts now come from the summary query rather than from walking
 * the group's logs. These pin the values that move, so a wrong aggregate shows
 * up as a wrong card rather than as a number nobody checks.
 */
it('stamps the card with the newest log in the group, whatever order they load in', function () {
    $session = newSession($this, 'personnel');
    $employee = Employee::create([
        'employee_id' => 'E1', 'fullname' => 'Worker 1', 'department_id' => $this->dept->id,
        'shift_id' => $this->shift->id, 'qr_code_hash' => 'h1', 'is_active' => true,
    ]);

    $newest = now()->subMinutes(5);
    foreach ([now()->subHours(3), $newest, now()->subHours(2)] as $at) {
        InspectionLog::create([
            'session_id' => $session->id, 'checkpoint_id' => $this->personCheckpoint->id,
            'employee_id' => $employee->id, 'result' => 'pass', 'inspected_at' => $at,
            'verification_status' => 'verified', 'verified_at' => now(),
        ]);
    }

    $group = pageData($this, 'filter_type=person&tab=completed')['groups']->first();

    expect($group->date)->toBe($newest->format('d/m/Y'))
        ->and($group->time)->toBe($newest->format('H:i'));
});

it('counts the people and the failures in a mixed round', function () {
    $session = newSession($this, 'personnel');

    foreach (range(1, 3) as $i) {
        $employee = Employee::create([
            'employee_id' => "E{$i}", 'fullname' => "Worker {$i}", 'department_id' => $this->dept->id,
            'shift_id' => $this->shift->id, 'qr_code_hash' => "h{$i}", 'is_active' => true,
        ]);

        foreach (['pass', $i === 3 ? 'fail' : 'pass'] as $result) {
            InspectionLog::create([
                'session_id' => $session->id, 'checkpoint_id' => $this->personCheckpoint->id,
                'employee_id' => $employee->id, 'result' => $result,
                'inspected_at' => now()->subSeconds(1000 - (++$this->clock)),
                'verification_status' => 'verified', 'verified_at' => now(),
                'acknowledged_at' => $i === 1 ? now() : null,
            ]);
        }
    }

    $group = pageData($this, 'filter_type=person&tab=completed')['groups']->first();

    expect($group->name)->toContain('3 คน')
        ->and($group->name)->toContain('พบข้อบกพร่อง')
        ->and($group->status)->toBe('fail')
        ->and($group->findings)->toHaveCount(1)
        ->and($group->all_logs)->toHaveCount(6)
        // Only one of the three was acknowledged.
        ->and($group->is_acknowledged)->toBeFalse();
});

it('counts machines and bare area checks separately in one location', function () {
    $location = stockedLocation('Packing room');
    $mixer = Machine::create(['location_id' => $location->id, 'name' => 'Mixer', 'is_active' => true]);
    $oven = Machine::create(['location_id' => $location->id, 'name' => 'Oven', 'is_active' => true]);

    $session = newSession($this, 'area');
    foreach ([$mixer->id, $oven->id, null] as $machineId) {
        InspectionLog::create([
            'session_id' => $session->id, 'checkpoint_id' => $this->areaCheckpoint->id,
            'employee_id' => null,
            'location_id' => $location->id, 'machine_id' => $machineId,
            'result' => 'pass', 'inspected_at' => now()->subSeconds(1000 - (++$this->clock)),
            'verification_status' => 'verified', 'verified_at' => now(),
        ]);
    }

    $group = pageData($this, 'filter_type=machine&tab=completed')['groups']->first();

    expect($group->name)->toBe('Packing room (พื้นที่ + อุปกรณ์ 2 ชิ้น)')
        ->and($group->type)->toBe('machine');
});
