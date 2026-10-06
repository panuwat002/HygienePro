<?php

use App\Models\Checkpoint;
use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;

/**
 * Three things the printed area form said that it should not have.
 *
 *  - the แผนก column took the department off the session, and an area round is
 *    stamped with whoever walked it - always QA - so every room in the plant
 *    read Quality Assurance, including the ones Production had just been made
 *    responsible for;
 *  - ผลการแก้ไข printed "(Open)" for a finding nobody had acted on: an English
 *    status, in a column headed "result of the fix", with nothing before it;
 *  - หมายเหตุ printed "ความสะอาด: ไม่ผ่าน", which only repeats the X already
 *    standing in that checkpoint's own column.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-06 10:00:00');

    $this->production = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);
    $this->qa = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->qa->id,
    ]);

    // Production's room, walked by QA.
    $this->room = Location::create([
        'location_name' => 'ห้อง เจาะมะพร้าว', 'department_id' => $this->production->id,
    ]);

    $this->session = InspectionSession::create([
        'inspector_id' => $this->admin->id, 'department_id' => $this->qa->id,
        'type' => 'machine', 'inspection_date' => '2026-10-06',
        'shift' => 'morning', 'round' => 1, 'status' => 'completed',
    ]);

    $this->checkpoint = Checkpoint::create([
        'title' => 'ความสะอาด', 'is_active' => true, 'type' => 'area',
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function areaForm($ctx): string
{
    $captured = [];
    View::composer('reports.pdf.daily', function ($view) use (&$captured) {
        $captured = $view->getData();
    });

    $ctx->actingAs($ctx->admin)->get(route('reports.export.pdf', [
        'date' => '2026-10-06', 'report_type' => 'machine',
    ]))->assertSuccessful();

    return view('reports.pdf.daily', $captured)->render();
}

function failedAreaLog($ctx, ?string $note = null): InspectionLog
{
    return InspectionLog::create([
        'session_id' => $ctx->session->id, 'checkpoint_id' => $ctx->checkpoint->id,
        'location_id' => $ctx->room->id, 'result' => 'fail',
        'note' => $note, 'inspected_at' => now(),
    ]);
}

it('names the department that runs the room, not the one that walked the round', function () {
    failedAreaLog($this);

    $html = areaForm($this);

    expect($html)->toContain('Production');
});

it('falls back to the round department for a room with no owner', function () {
    $this->room->update(['department_id' => null]);
    failedAreaLog($this);

    expect(areaForm($this))->toContain('Quality Assurance');
});

/**
 * The column is headed ผลการแก้ไข. A finding nobody has acted on has no result
 * yet, and must not be made to look as though it has one.
 */
it('says in Thai that a finding has not been fixed', function () {
    $log = failedAreaLog($this);
    CorrectiveAction::factory()->create([
        'inspection_log_id' => $log->id, 'escalated_by' => $this->admin->id, 'status' => 'open',
    ]);

    $html = areaForm($this);

    expect($html)->toContain('ยังไม่ได้แก้ไข')
        ->and($html)->not->toContain('(Open)');
});

it('never prints a raw status for one that was only assigned', function () {
    $log = failedAreaLog($this);
    CorrectiveAction::factory()->create([
        'inspection_log_id' => $log->id, 'escalated_by' => $this->admin->id, 'status' => 'assigned',
    ]);

    expect(areaForm($this))->not->toContain('(Assigned)');
});

it('prints what was done once there is something to print', function () {
    $log = failedAreaLog($this);
    CorrectiveAction::factory()->create([
        'inspection_log_id' => $log->id, 'escalated_by' => $this->admin->id,
        'status' => 'resolved', 'action_taken' => 'ล้างท่อระบายน้ำและเช็ดแห้ง',
    ]);

    $html = areaForm($this);

    expect($html)->toContain('ล้างท่อระบายน้ำและเช็ดแห้ง')
        ->and($html)->toContain('แก้ไขแล้ว');
});

/**
 * The X in the checkpoint's own column already says it failed.
 */
it('leaves the note column empty when there is no note', function () {
    failedAreaLog($this);

    expect(areaForm($this))->not->toContain('ความสะอาด: ไม่ผ่าน');
});

it('prints the note when there is one', function () {
    failedAreaLog($this, 'ท่อระบายน้ำมีน้ำขัง');

    expect(areaForm($this))->toContain('ท่อระบายน้ำมีน้ำขัง');
});
