<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Checkpoint;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionWorkflowAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private $qaDept;
    private $prodDept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->qaDept = Department::create([
            'dept_name' => 'Quality Assurance',
            'dept_code' => 'QA',
            'visibility_type' => 'global',
        ]);

        $this->prodDept = Department::create([
            'dept_name' => 'Production',
            'dept_code' => 'PD',
            'visibility_type' => 'isolated',
        ]);
    }

    public function test_scan_page_receives_all_required_progress_and_completion_variables()
    {
        $shift = Shift::create([
            'shift_name' => 'กะเช้า 08.00-17.00',
            'shift_type' => 'กะเช้า',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'department_id' => $this->prodDept->id,
        ]);

        $qaStaff = User::factory()->create([
            'role' => 'staff',
            'level' => 2,
            'department_id' => $this->qaDept->id,
        ]);

        Employee::factory()->create([
            'department_id' => $this->prodDept->id,
            'shift_id' => $shift->id,
            'is_active' => true,
        ]);
        Employee::factory()->create([
            'department_id' => $this->prodDept->id,
            'shift_id' => $shift->id,
            'is_active' => true,
        ]);

        $session = InspectionSession::create([
            'inspector_id' => $qaStaff->id,
            'department_id' => $this->prodDept->id,
            'type' => 'personnel',
            'shift' => 'morning',
            'inspection_date' => now()->toDateString(),
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($qaStaff)->get(route('inspection.scan', $session->id));

        $response->assertStatus(200);
        $response->assertViewHas('totalEmployees', 2);
        $response->assertViewHas('inspectedCount', 0);
        $response->assertViewHas('progressPercent', 0);
        $response->assertViewHas('shiftRemainingCount', 2);
    }

    public function test_random_audit_sample_size_is_respected_in_scan_and_session_targets()
    {
        $shift = Shift::create([
            'shift_name' => 'กะเช้า 08.00-17.00',
            'shift_type' => 'กะเช้า',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'department_id' => $this->prodDept->id,
        ]);

        $qaStaff = User::factory()->create([
            'role' => 'staff',
            'level' => 2,
            'department_id' => $this->qaDept->id,
        ]);

        // Create 10 employees
        for ($i = 0; $i < 10; $i++) {
            Employee::factory()->create([
                'department_id' => $this->prodDept->id,
                'shift_id' => $shift->id,
                'is_active' => true,
            ]);
        }

        $session = InspectionSession::create([
            'inspector_id' => $qaStaff->id,
            'department_id' => $this->prodDept->id,
            'type' => 'personnel',
            'shift' => 'morning',
            'inspection_date' => now()->toDateString(),
            'status' => 'in_progress',
            'is_sampling' => true,
            'sample_size' => 3,
        ]);

        $this->assertEquals(3, $session->getSessionTargetEmployees()->count());

        $response = $this->actingAs($qaStaff)->get(route('inspection.scan', $session->id));
        $response->assertStatus(200);
        $response->assertViewHas('totalEmployees', 3);
        $response->assertViewHas('shiftRemainingCount', 3);
    }

    public function test_qa_supervisor_can_bulk_pass_and_no_production_in_area_session_owned_by_staff()
    {
        $loc = Location::create([
            'location_name' => 'Packing Room',
            'department_id' => $this->prodDept->id,
            'is_active' => true,
        ]);

        $qaStaff = User::factory()->create([
            'role' => 'staff',
            'level' => 2,
            'department_id' => $this->qaDept->id,
        ]);
        $qaSupervisor = User::factory()->create([
            'role' => 'supervisor',
            'level' => 4,
            'department_id' => $this->qaDept->id,
        ]);

        $session = InspectionSession::create([
            'inspector_id' => $qaStaff->id,
            'department_id' => $this->prodDept->id,
            'type' => 'area',
            'shift' => 'morning',
            'inspection_date' => now()->toDateString(),
            'status' => 'in_progress',
        ]);

        // QA Supervisor calls storeBulkNoProduction
        $respNoProd = $this->actingAs($qaSupervisor)->postJson(
            route('inspection.area.bulk-no-production', [$session->id, $loc->id])
        );
        $this->assertNotEquals(403, $respNoProd->status());

        // QA Supervisor calls storeBulkPass
        $respPass = $this->actingAs($qaSupervisor)->postJson(
            route('inspection.area.bulk-pass', [$session->id, $loc->id])
        );
        $this->assertNotEquals(403, $respPass->status());
    }

    public function test_reports_page_renders_cleanly()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_id' => $this->qaDept->id,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertViewIs('reports.index');
        $response->assertSee('รายงานและวิเคราะห์ผล');
    }

    public function test_verification_updates_session_verified_at_and_verified_by()
    {
        $qaStaff = User::factory()->create([
            'role' => 'staff',
            'level' => 2,
            'department_id' => $this->qaDept->id,
        ]);

        $qaSupervisor = User::factory()->create([
            'role' => 'supervisor',
            'level' => 4,
            'department_id' => $this->qaDept->id,
        ]);

        $session = InspectionSession::create([
            'inspector_id' => $qaStaff->id,
            'department_id' => $this->prodDept->id,
            'type' => 'personnel',
            'shift' => 'morning',
            'inspection_date' => now()->toDateString(),
            'status' => 'in_progress',
        ]);

        $checkpoint = \App\Models\Checkpoint::factory()->create([
            'type' => 'person',
        ]);

        $log = \App\Models\InspectionLog::create([
            'session_id' => $session->id,
            'checkpoint_id' => $checkpoint->id,
            'result' => 'pass',
            'inspected_at' => now(),
        ]);

        $this->assertNull($session->verified_at);
        $this->assertNull($session->verified_by);

        $response = $this->actingAs($qaSupervisor)->post(route('inspection.verify'), [
            'ids' => [$log->id],
            'status' => 'verified',
        ]);

        $response->assertRedirect();
        $session->refresh();

        $this->assertNotNull($session->verified_at);
        $this->assertEquals($qaSupervisor->id, $session->verified_by);
    }

    public function test_signatures_blade_renders_thai_names_and_digital_timestamps()
    {
        $inspector = User::factory()->create([
            'name' => 'น.ส. การะเกด จันทอน',
            'role' => 'staff',
            'level' => 2,
            'department_id' => $this->qaDept->id,
        ]);

        $verifier = User::factory()->create([
            'name' => 'Ms. Ketmanee Tansayan',
            'role' => 'supervisor',
            'level' => 4,
            'department_id' => $this->qaDept->id,
        ]);

        $approver = User::factory()->create([
            'name' => 'Dr. QA Manager',
            'role' => 'manager',
            'level' => 5,
            'department_id' => $this->qaDept->id,
        ]);

        $session = InspectionSession::create([
            'inspector_id' => $inspector->id,
            'department_id' => $this->prodDept->id,
            'type' => 'personnel',
            'shift' => 'morning',
            'inspection_date' => '2026-09-16',
            'created_at' => '2026-09-16 08:54:00',
            'verified_at' => '2026-09-16 09:30:00',
            'verified_by' => $verifier->id,
            'approved_at' => '2026-09-16 10:15:00',
            'approved_by' => $approver->id,
            'status' => 'completed',
        ]);

        $rendered = view('reports.pdf._signatures', [
            'sessions' => collect([$session]),
            'verifiers' => collect([$verifier]),
            'approvers' => collect([$approver]),
            'recordedAt' => '2026-09-16 08:54:00',
            'verifiedAt' => '2026-09-16 09:30:00',
            'approvedAt' => '2026-09-16 10:15:00',
        ])->render();

        // 1. Must NOT use Courier New which broke Thai glyphs
        $this->assertStringNotContainsString('Courier New', $rendered);

        // 2. Must NOT fetch external Wikimedia images
        $this->assertStringNotContainsString('wikimedia.org', $rendered);

        // 3. Recorder: Shows Thai name and timestamp
        $this->assertStringContainsString('น.ส. การะเกด จันทอน', $rendered);
        $this->assertStringContainsString('(Digital Record)', $rendered);
        $this->assertStringContainsString('16/09/2026 08:54', $rendered);

        // 4. Verifier: Shows Verified stamp, verifier name, and verified timestamp
        $this->assertStringContainsString('Verified', $rendered);
        $this->assertStringContainsString('(Digital Verified)', $rendered);
        $this->assertStringContainsString('Ms. Ketmanee Tansayan', $rendered);
        $this->assertStringContainsString('16/09/2026 09:30', $rendered);

        // 5. Approver: Shows Approved stamp, approver name, and approved timestamp
        $this->assertStringContainsString('Approved', $rendered);
        $this->assertStringContainsString('(Digital Approved)', $rendered);
        $this->assertStringContainsString('Dr. QA Manager', $rendered);
        $this->assertStringContainsString('16/09/2026 10:15', $rendered);

        // 6. Must NOT use position: absolute or position: relative which caused text overlapping in DomPDF
        $this->assertStringNotContainsString('position: absolute', $rendered);
        $this->assertStringNotContainsString('position: relative', $rendered);
    }

    public function test_export_daily_pdf_returns_successful_stream()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_id' => $this->qaDept->id,
        ]);

        $session = InspectionSession::create([
            'inspector_id' => $admin->id,
            'department_id' => $this->prodDept->id,
            'type' => 'personnel',
            'shift' => 'morning',
            'inspection_date' => '2026-09-16',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->get(route('reports.export.pdf', [
            'date' => '2026-09-16',
            'report_type' => 'person',
            'orientation' => 'landscape',
            'session_ids' => (string) $session->id,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_verification_page_retains_status_counts_and_category_badges()
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'level' => 4,
            'department_id' => $this->qaDept->id,
        ]);

        $employee = Employee::factory()->create([
            'department_id' => $this->prodDept->id,
            'is_active' => true,
        ]);

        $location = Location::create([
            'location_name' => 'Packing Room',
            'department_id' => $this->prodDept->id,
        ]);

        $personCheckpoint = Checkpoint::create([
            'title' => 'Uniform Check',
            'type' => 'person',
            'is_active' => true,
        ]);

        $areaCheckpoint = Checkpoint::create([
            'title' => 'Floor Cleanliness',
            'type' => 'area',
            'is_active' => true,
        ]);
        $location->checkpoints()->attach($areaCheckpoint->id);

        // 1. Personnel session with 1 verified item (awaiting manager approval from yesterday)
        $personSession = InspectionSession::create([
            'inspector_id' => $manager->id,
            'department_id' => $this->prodDept->id,
            'type' => 'personnel',
            'shift' => 'morning',
            'inspection_date' => now()->subDay()->toDateString(),
            'created_at' => now()->subDay()->setTime(8, 0),
            'status' => 'in_progress',
        ]);

        InspectionLog::create([
            'session_id' => $personSession->id,
            'employee_id' => $employee->id,
            'checkpoint_id' => $personCheckpoint->id,
            'result' => 'pass',
            'verification_status' => 'verified',
            'verified_at' => now()->subDay()->setTime(9, 0),
            'verifier_id' => $manager->id,
            'inspected_at' => now()->subDay()->setTime(8, 30),
        ]);

        // 2. Machine/Area session with 1 verified item (awaiting manager approval from today)
        $areaSession = InspectionSession::create([
            'inspector_id' => $manager->id,
            'department_id' => $this->prodDept->id,
            'type' => 'area',
            'shift' => 'morning',
            'inspection_date' => now()->toDateString(),
            'created_at' => now()->setTime(8, 0),
            'status' => 'in_progress',
        ]);

        InspectionLog::create([
            'session_id' => $areaSession->id,
            'location_id' => $location->id,
            'checkpoint_id' => $areaCheckpoint->id,
            'result' => 'pass',
            'verification_status' => 'verified',
            'verified_at' => now()->setTime(9, 0),
            'verifier_id' => $manager->id,
            'inspected_at' => now()->setTime(8, 30),
        ]);

        // Scenario A: When viewing person category in the awaiting-approval tab
        $response = $this->actingAs($manager)->get(route('inspection.verification', [
            'filter_type' => 'person',
            'tab' => 'awaiting_approval',
        ]));

        $response->assertStatus(200);
        $counts = $response->viewData('counts');
        $typeCounts = $response->viewData('typeCounts');

        // Awaiting-approval count must be 1 for person
        $this->assertEquals(1, $counts['awaiting_approval']);
        // Category switcher must report true counts for both categories
        $this->assertEquals(1, $typeCounts['person']);
        $this->assertEquals(1, $typeCounts['machine']);

        // Switching category link to machine MUST preserve the open tab
        $response->assertSee('filter_type=machine&amp;tab=awaiting_approval', false);

        // Scenario B: When viewing person category in pending tab
        // BUG FIX VERIFICATION: the other tabs' counts must NOT drop to 0!
        $responsePending = $this->actingAs($manager)->get(route('inspection.verification', [
            'filter_type' => 'person',
            'tab' => 'pending',
        ]));

        $responsePending->assertStatus(200);
        $countsOnPending = $responsePending->viewData('counts');
        $this->assertEquals(1, $countsOnPending['awaiting_approval'], 'Awaiting-approval count must not drop to 0 when viewing pending tab');

        // Scenario C: When viewing machine category in the awaiting-approval tab
        $responseMachine = $this->actingAs($manager)->get(route('inspection.verification', [
            'filter_type' => 'machine',
            'tab' => 'awaiting_approval',
        ]));

        $responseMachine->assertStatus(200);
        $countsMachine = $responseMachine->viewData('counts');
        $this->assertEquals(1, $countsMachine['awaiting_approval']);

        // Switching category link to person MUST preserve the open tab
        $responseMachine->assertSee('filter_type=person&amp;tab=awaiting_approval', false);
    }
}

