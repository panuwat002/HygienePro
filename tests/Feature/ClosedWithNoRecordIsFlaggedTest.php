<?php

use App\Models\Checkpoint;
use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use App\Models\User;

/**
 * The history panel gave every closed finding a green "Closed" tick - two
 * columns away from the words "ไม่ได้บันทึกวิธีแก้ไข" in the same row. The page
 * asserted work was finished while printing, beside it, that nothing had been
 * recorded.
 *
 * Those rows exist because approving a round used to close findings nobody had
 * touched. That is fixed (8f9db8b, and approval now holds unfixed findings
 * back), but the rows already written cannot be corrected by code - somebody
 * has to go and find out whether the problem was ever dealt with. Until then
 * the page must not claim it was.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'),
        'role' => 'admin', 'level' => 9, 'department_id' => $this->dept->id,
    ]);

    $this->session = InspectionSession::factory()->create([
        'department_id' => $this->dept->id, 'status' => 'completed',
    ]);

    $this->checkpoint = Checkpoint::create(['title' => 'ความสะอาด', 'is_active' => true, 'type' => 'area']);
    $this->table = Location::create(['location_name' => 'โต๊ะลอกเนื้อ']);
});

function closedAction($ctx, ?string $actionTaken): CorrectiveAction
{
    $log = InspectionLog::create([
        'session_id' => $ctx->session->id, 'checkpoint_id' => $ctx->checkpoint->id,
        'location_id' => $ctx->table->id, 'result' => 'fail', 'inspected_at' => now(),
    ]);

    return CorrectiveAction::factory()->create([
        'inspection_log_id' => $log->id,
        'escalated_by' => $ctx->admin->id,
        'status' => 'closed',
        'action_taken' => $actionTaken,
        'closed_at' => now(),
    ]);
}

it('does not call a finding closed with no record finished work', function () {
    closedAction($this, null);

    $this->actingAs($this->admin)
        ->get(route('corrective.index'))
        ->assertSuccessful()
        ->assertSee('ปิดโดยไม่มีบันทึก')
        ->assertSee('ไม่ได้บันทึกวิธีแก้ไข');
});

it('counts them so one bad row in a long history is not found by chance', function () {
    closedAction($this, null);
    closedAction($this, null);
    closedAction($this, 'เช็ดขอบมุมโต๊ะและล้างด้วยน้ำยา');

    $this->actingAs($this->admin)
        ->get(route('corrective.index'))
        ->assertSuccessful()
        ->assertSee('มี 2 รายการที่ถูกปิดโดยไม่มีบันทึก', false);
});

it('still calls a properly closed finding closed', function () {
    closedAction($this, 'เช็ดขอบมุมโต๊ะและล้างด้วยน้ำยา');

    $this->actingAs($this->admin)
        ->get(route('corrective.index'))
        ->assertSuccessful()
        ->assertSee('เช็ดขอบมุมโต๊ะและล้างด้วยน้ำยา')
        ->assertDontSee('ปิดโดยไม่มีบันทึก');
});

it('says nothing about it when there are none', function () {
    closedAction($this, 'เช็ดขอบมุมโต๊ะและล้างด้วยน้ำยา');

    $this->actingAs($this->admin)
        ->get(route('corrective.index'))
        ->assertSuccessful()
        ->assertDontSee('ถูกปิดโดยไม่มีบันทึก');
});
