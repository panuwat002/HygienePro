<?php

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

/**
 * Before 238e48b, approving a round wrote the manager's id into verifier_id -
 * the column the printed form's ผู้ทวนสอบ (QA Supervisor) box is filled from.
 * On UAT that is 25,007 rows across 101 rounds.
 *
 * The migration takes the manager back out. It cannot put the supervisor back,
 * but it does not need to: the report also reads
 * inspection_sessions.verified_by, which approval never touched.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-03 09:00:00');

    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->qa->id,
    ]);
    $this->supervisor = User::create([
        'name' => 'Somsak Supervisor', 'email' => 'sup@example.com', 'password' => bcrypt('x'),
        'role' => 'supervisor', 'level' => 4, 'department_id' => $this->qa->id,
    ]);
    $this->manager = User::create([
        'name' => 'Wallika Manager', 'email' => 'mgr@example.com', 'password' => bcrypt('x'),
        'role' => 'manager', 'level' => 5, 'department_id' => $this->qa->id,
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);
    $this->checkpoint = Checkpoint::create(['title' => 'ล้างมือ', 'is_active' => true, 'type' => 'person']);
    $this->seq = 0;
});

afterEach(fn () => Carbon::setTestNow());

/**
 * One approved log. $overwritten builds the shape the old code left behind:
 * the same person and the same instant in both the verifier and the approver
 * columns.
 */
function approvedLog($ctx, bool $overwritten, ?int $sessionVerifiedBy = null): InspectionLog
{
    $ctx->seq++;

    $session = InspectionSession::create([
        'inspector_id' => $ctx->admin->id, 'department_id' => $ctx->dept->id,
        'type' => 'personnel', 'inspection_date' => '2026-09-01',
        'shift' => 'custom_' . $ctx->shift->id, 'round' => $ctx->seq, 'status' => 'completed',
        'verified_by' => $sessionVerifiedBy,
        'verified_at' => $sessionVerifiedBy ? '2026-09-01 10:00:00' : null,
    ]);

    $employee = Employee::create([
        'employee_id' => 'E' . $ctx->seq, 'fullname' => 'พนักงาน ' . $ctx->seq,
        'department_id' => $ctx->dept->id, 'shift_id' => $ctx->shift->id,
        'qr_code_hash' => 'h' . $ctx->seq, 'is_active' => true,
    ]);

    return InspectionLog::create([
        'session_id' => $session->id, 'employee_id' => $employee->id,
        'checkpoint_id' => $ctx->checkpoint->id, 'result' => 'pass',
        'inspected_at' => '2026-09-01 08:00:00',
        'verification_status' => 'approved',
        // Overwritten: manager in both columns, one instant in both.
        // Healthy: supervisor verified at 10:00, manager approved at 11:00.
        'verifier_id'  => $overwritten ? $ctx->manager->id : $ctx->supervisor->id,
        'verified_at'  => $overwritten ? '2026-09-01 11:00:00' : '2026-09-01 10:00:00',
        'approved_by'  => $ctx->manager->id,
        'approved_at'  => '2026-09-01 11:00:00',
    ]);
}

function clearMigration()
{
    return require base_path('database/migrations/2026_10_03_100000_clear_overwritten_verifier_ids.php');
}

it('takes the approver back out of the verifier column', function () {
    $log = approvedLog($this, overwritten: true);

    clearMigration()->up();

    expect($log->fresh()->verifier_id)->toBeNull();
});

it('leaves a round approved since the fix completely alone', function () {
    $log = approvedLog($this, overwritten: false);

    clearMigration()->up();

    expect($log->fresh()->verifier_id)->toBe($this->supervisor->id);
});

it('leaves work that is not approved alone', function () {
    $log = approvedLog($this, overwritten: true);
    $log->update(['verification_status' => 'verified']);

    clearMigration()->up();

    expect($log->fresh()->verifier_id)->toBe($this->manager->id);
});

/**
 * Nothing is lost: who approved is still recorded, in the column that means it.
 */
it('still knows who approved', function () {
    $log = approvedLog($this, overwritten: true);

    clearMigration()->up();

    expect($log->fresh()->approved_by)->toBe($this->manager->id);
});

/**
 * VerificationGroups reads a null verified_at as "nobody has verified this".
 * Clearing it would march every one of these rows back into the inbox.
 */
it('does not touch verified_at', function () {
    $log = approvedLog($this, overwritten: true);

    clearMigration()->up();

    expect($log->fresh()->verified_at)->not->toBeNull();
});

it('can be rolled back', function () {
    $log = approvedLog($this, overwritten: true);

    $migration = clearMigration();
    $migration->up();
    $migration->down();

    expect($log->fresh()->verifier_id)->toBe($this->manager->id);
});

it('runs twice without doing anything the second time', function () {
    approvedLog($this, overwritten: true);
    $healthy = approvedLog($this, overwritten: false);

    clearMigration()->up();
    clearMigration()->up();

    expect($healthy->fresh()->verifier_id)->toBe($this->supervisor->id)
        ->and(DB::table('inspection_logs')->whereNull('verifier_id')->count())->toBe(1);
});

/**
 * The point of the whole exercise. The form stops naming the manager, and
 * where the round still carries a session-level verifier it names the real
 * supervisor again - taken from a column approval never wrote to, not guessed.
 */
it('prints the real supervisor on a form whose round still remembers one', function () {
    approvedLog($this, overwritten: true, sessionVerifiedBy: $this->supervisor->id);

    clearMigration()->up();

    $captured = [];
    View::composer('reports.pdf.daily', function ($view) use (&$captured) {
        $captured = $view->getData();
    });

    $this->actingAs($this->admin)->get(route('reports.export.pdf', [
        'date' => '2026-09-01', 'report_type' => 'person',
    ]))->assertSuccessful();

    $names = $captured['verifiers']->pluck('name')->all();

    expect($names)->toBe(['Somsak Supervisor']);
});

it('names nobody on a form whose round does not remember one', function () {
    approvedLog($this, overwritten: true, sessionVerifiedBy: null);

    clearMigration()->up();

    $captured = [];
    View::composer('reports.pdf.daily', function ($view) use (&$captured) {
        $captured = $view->getData();
    });

    $this->actingAs($this->admin)->get(route('reports.export.pdf', [
        'date' => '2026-09-01', 'report_type' => 'person',
    ]))->assertSuccessful();

    expect($captured['verifiers'])->toBeEmpty();
});
