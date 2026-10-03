<?php

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;

/**
 * The form sorted every person by name and nothing else. Thai names run ก→ฮ
 * across the whole report, so one page held Production, QA and ห้องแคะ in
 * turn - and because ห้องแคะ does not pass through an air shower, its N/A
 * scattered down the ผ่านตู้เป่าลม column between other departments' ticks.
 *
 * A page of this form is signed as one work area's record, so it holds one.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-02 14:00:00');

    $this->production = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->pdk = Department::create([
        'dept_name' => 'Production (ห้องแคะ)', 'dept_code' => 'PDK', 'visibility_type' => 'isolated',
        'parent_department_id' => $this->production->id,
    ]);
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 9,
        'department_id' => $this->qa->id,
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 07.00-16.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '07:00:00', 'end_time' => '16:00:00',
    ]);

    $this->checkpoint = Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person']);
    $this->seq = 0;
    $this->sessions = [];
});

afterEach(fn () => Carbon::setTestNow());

/**
 * One inspected person in a department's own round. The names are chosen so
 * that sorting by name alone interleaves the departments.
 */
function personIn($ctx, Department $dept, string $name): Employee
{
    $ctx->seq++;

    // One round per department, held here rather than looked up: the
    // inspection_date cast stores 00:00:00, so firstOrCreate() on the date
    // string never matches what it wrote and inserts a duplicate.
    if (! isset($ctx->sessions[$dept->id])) {
        $ctx->sessions[$dept->id] = InspectionSession::create([
            'inspector_id' => $ctx->admin->id,
            'department_id' => $dept->id,
            'inspection_date' => '2026-10-02',
            'shift' => 'custom_' . $ctx->shift->id,
            'round' => 1,
            'type' => 'personnel',
            'status' => 'completed',
        ]);
    }

    $session = $ctx->sessions[$dept->id];

    $employee = Employee::create([
        'employee_id' => 'E' . $ctx->seq, 'fullname' => $name, 'prefix' => 'น.ส.',
        'department_id' => $dept->id, 'shift_id' => $ctx->shift->id,
        'qr_code_hash' => 'h' . $ctx->seq, 'is_active' => true,
    ]);

    InspectionLog::create([
        'session_id' => $session->id, 'employee_id' => $employee->id,
        'checkpoint_id' => $ctx->checkpoint->id, 'result' => 'pass',
        'inspected_at' => Carbon::parse('2026-10-02 08:00:00'),
    ]);

    return $employee;
}

function pdfChunks($test): array
{
    $captured = [];

    View::composer('reports.pdf.daily', function ($view) use (&$captured) {
        $captured = $view->getData();
    });

    $test->actingAs($test->admin)->get(route('reports.export.pdf', [
        'date' => '2026-10-02',
        'report_type' => 'person',
    ]))->assertSuccessful();

    return $captured['employeeChunks'];
}

it('keeps a department together instead of interleaving it by name', function () {
    // Alphabetically these alternate: ก, ข, ค, ง.
    personIn($this, $this->production, 'กาญจนา งามญาติ');
    personIn($this, $this->pdk, 'ขอ สุ');
    personIn($this, $this->production, 'คิน ชานตา อาว');
    personIn($this, $this->pdk, 'งามตา ทองดี');

    $departmentsPerPage = collect(pdfChunks($this))
        ->map(fn ($chunk) => collect($chunk)->pluck('department_label')->unique()->values()->all());

    // No page mixes two departments.
    expect($departmentsPerPage->every(fn ($d) => count($d) === 1))->toBeTrue();
});

it('gives every department a page of its own, however few people it has', function () {
    personIn($this, $this->production, 'กาญจนา งามญาติ');
    personIn($this, $this->pdk, 'ขอ สุ');
    personIn($this, $this->qa, 'การะเกด จันทอน');

    expect(pdfChunks($this))->toHaveCount(3);
});

it('orders people by name inside their own department', function () {
    personIn($this, $this->pdk, 'ซูลี เมียว');
    personIn($this, $this->pdk, 'ขอ สุ');
    personIn($this, $this->pdk, 'ตาชิน มอน');

    $names = collect(pdfChunks($this)[0])->map(fn ($r) => $r['info']->fullname)->values()->all();

    expect($names)->toBe(['ขอ สุ', 'ซูลี เมียว', 'ตาชิน มอน']);
});

it('still splits one large department across pages', function () {
    foreach (range(1, 20) as $n) {
        personIn($this, $this->production, 'พนักงาน ' . str_pad($n, 2, '0', STR_PAD_LEFT));
    }

    $chunks = pdfChunks($this);

    expect($chunks)->toHaveCount(2)
        ->and(count($chunks[0]))->toBe(18)
        ->and(count($chunks[1]))->toBe(2);
});

it('names the department on each page of the form', function () {
    personIn($this, $this->pdk, 'ขอ สุ');
    personIn($this, $this->production, 'กาญจนา งามญาติ');

    $captured = [];
    View::composer('reports.pdf.daily', function ($view) use (&$captured) {
        $captured = $view->getData();
    });

    $this->actingAs($this->admin)->get(route('reports.export.pdf', [
        'date' => '2026-10-02', 'report_type' => 'person',
    ]))->assertSuccessful();

    $html = view('reports.pdf.daily', $captured)->render();

    expect($html)->toContain('แผนก Production (ห้องแคะ)')
        ->and($html)->toContain('แผนก Production');
});
