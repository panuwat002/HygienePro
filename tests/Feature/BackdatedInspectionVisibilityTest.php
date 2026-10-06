<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use App\Services\InspectionService;
use Illuminate\Support\Carbon;

/**
 * The quiet half of backdating: every screen that answers "what is there to
 * inspect" answered it for today, even while the round being started was for
 * an earlier day.
 *
 * The worst of it is that getDepartmentStats() drops a shift card with nobody
 * rostered and no inspections - so a shift that worked on the 5th and not on
 * the 6th was not offered at all, and the backdated round for it could not be
 * started. Nothing on screen said why.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-06 09:00:00');

    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->supervisor = User::create([
        'name' => 'QA Supervisor', 'email' => 'sup@example.com', 'password' => bcrypt('x'),
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->qa->id,
    ]);
    $this->inspector = User::create([
        'name' => 'Inspector', 'email' => 'ins@example.com', 'password' => bcrypt('x'),
        'role' => 'staff', 'level' => 2, 'department_id' => $this->qa->id,
    ]);

    $this->night = Shift::create([
        'shift_name' => 'กะดึก 19.00-04.00', 'shift_type' => Shift::TYPE_NIGHT,
        'start_time' => '19:00:00', 'end_time' => '04:00:00',
    ]);

    // Somebody who worked the night shift on the 5th and is off on the 6th.
    $this->worker = Employee::create([
        'employee_id' => 'N1', 'fullname' => 'พนักงาน กะดึก',
        'department_id' => $this->dept->id, 'shift_id' => $this->night->id,
        'qr_code_hash' => 'n1', 'is_active' => true,
    ]);

    EmployeeSchedule::create([
        'employee_id' => $this->worker->id, 'date' => '2026-10-05',
        'shift_id' => $this->night->id, 'is_day_off' => false,
    ]);
    EmployeeSchedule::create([
        'employee_id' => $this->worker->id, 'date' => '2026-10-06',
        'shift_id' => $this->night->id, 'is_day_off' => true,
    ]);

    $this->service = app(InspectionService::class);
});

afterEach(fn () => Carbon::setTestNow());

function shiftCardsFor($ctx, ?string $forDate): array
{
    $query = ['shifts' => ['custom_' . $ctx->night->id]];

    if ($forDate) {
        $query['for_date'] = $forDate;
    }

    $response = $ctx->actingAs($ctx->supervisor)
        ->getJson(route('inspection.stats', ['type' => 'personnel', 'department' => $ctx->dept->id] + $query))
        ->assertSuccessful();

    return $response->json('locations') ?? [];
}

it('offers a shift that worked on the day being recorded', function () {
    $cards = shiftCardsFor($this, '2026-10-05');

    expect(collect($cards)->pluck('employees_count')->sum())->toBe(1);
});

/**
 * The same request without the date: the person is off today, so the card is
 * dropped and the shift cannot be picked.
 */
it('shows that the same shift is empty today', function () {
    $cards = shiftCardsFor($this, null);

    expect(collect($cards)->pluck('employees_count')->sum())->toBe(0);
});

it('ignores a date from somebody who may not backdate', function () {
    $cards = $this->actingAs($this->inspector)
        ->getJson(route('inspection.stats', [
            'type' => 'personnel', 'department' => $this->dept->id,
            'shifts' => ['custom_' . $this->night->id], 'for_date' => '2026-10-05',
        ]))
        ->assertSuccessful()
        ->json('locations') ?? [];

    expect(collect($cards)->pluck('employees_count')->sum())->toBe(0);
});

it('ignores a date outside the window rather than breaking the page', function () {
    foreach (['2026-09-01', '2026-10-20', 'not-a-date'] as $bad) {
        expect($this->service->resolveViewDate($this->supervisor, $bad))->toBe('2026-10-06');
    }
});

it('accepts a date inside the window', function () {
    expect($this->service->resolveViewDate($this->supervisor, '2026-10-05'))->toBe('2026-10-05');
});

/**
 * Somebody picking rounds to export has to be able to see which of them is an
 * exception, not only discover it once the PDF is printed.
 */
it('marks a backdated round on the report list, not only on the PDF', function () {
    $this->service->startSession(
        $this->supervisor, $this->dept->id, 'area', false, null, false, null,
        '2026-10-05', 'ได้รับตารางกะจากฝ่ายผลิตล่าช้า'
    );

    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->qa->id,
    ]);

    $this->actingAs($admin)
        ->get(route('reports.daily', ['date' => '2026-10-05']))
        ->assertSuccessful()
        ->assertSee('บันทึกย้อนหลัง');
});

it('says nothing of the sort for an ordinary round', function () {
    $this->service->startSession($this->supervisor, $this->dept->id, 'area');

    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin2@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->qa->id,
    ]);

    $this->actingAs($admin)
        ->get(route('reports.daily', ['date' => '2026-10-06']))
        ->assertSuccessful()
        ->assertDontSee('บันทึกย้อนหลัง');
});
