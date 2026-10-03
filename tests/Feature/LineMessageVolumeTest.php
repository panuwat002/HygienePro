<?php

use App\Models\Checkpoint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InspectionLog;
use App\Models\InspectionSession;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\SessionSummaryLineNotification;
use Illuminate\Support\Carbon;

/**
 * The LINE channel's allowance is 300 messages a month, and the rest of the
 * system already spends about 130 of it on fixed daily and per-round traffic.
 *
 * A LINE push per failing employee therefore made the cost of a round depend
 * on how bad hygiene was that shift: twenty failures took a sixth of the
 * month. And LineMessagingChannel logs a refusal and swallows it, so once the
 * allowance ran out the alerts would simply stop arriving with nothing on
 * screen to say so - exactly when they mattered most.
 *
 * One message per round, naming everyone who failed, is a cost that does not
 * move with the result.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-03 09:00:00');

    $this->dept = Department::create([
        'dept_name' => 'Production', 'dept_code' => 'PD', 'visibility_type' => 'isolated',
    ]);

    $this->inspector = User::create([
        'name' => 'Inspector', 'email' => 'inspector@example.com', 'password' => bcrypt('x'),
        'role' => 'staff', 'level' => 2, 'department_id' => $this->dept->id,
    ]);

    $this->shift = Shift::create([
        'shift_name' => 'กะเช้า 08.00-17.00', 'shift_type' => Shift::TYPE_MORNING,
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
    ]);

    $this->handWash = Checkpoint::create(['title' => 'ล้างมือ ฆ่าเชื้อ', 'is_active' => true, 'type' => 'person']);
    $this->uniform = Checkpoint::create(['title' => 'แต่งกาย ถูกต้อง', 'is_active' => true, 'type' => 'person']);

    $this->session = InspectionSession::create([
        'inspector_id' => $this->inspector->id, 'department_id' => $this->dept->id,
        'type' => 'personnel', 'inspection_date' => '2026-10-03',
        'shift' => 'custom_' . $this->shift->id, 'round' => 1, 'status' => 'completed',
    ]);

    $this->seq = 0;
});

afterEach(fn () => Carbon::setTestNow());

function failing($ctx, string $name, array $checkpoints): Employee
{
    $ctx->seq++;

    $employee = Employee::create([
        'employee_id' => 'E' . $ctx->seq, 'fullname' => $name,
        'department_id' => $ctx->dept->id, 'shift_id' => $ctx->shift->id,
        'qr_code_hash' => 'h' . $ctx->seq, 'is_active' => true,
    ]);

    foreach ($checkpoints as $checkpoint) {
        InspectionLog::create([
            'session_id' => $ctx->session->id, 'employee_id' => $employee->id,
            'checkpoint_id' => $checkpoint->id, 'result' => 'fail',
            'inspected_at' => now(),
        ]);
    }

    return $employee;
}

function summaryText($ctx): string
{
    $notification = new SessionSummaryLineNotification(
        $ctx->session->fresh(),
        ['total' => 10, 'pass' => 5, 'fail' => 5],
        0
    );

    return $notification->toLine(null);
}

it('names who failed and on what', function () {
    failing($this, 'น.ส. กาญจนา งามญาติ', [$this->handWash]);

    $text = summaryText($this);

    expect($text)->toContain('น.ส. กาญจนา งามญาติ')
        ->and($text)->toContain('ล้างมือ ฆ่าเชื้อ');
});

it('puts every check one person failed on a single line', function () {
    failing($this, 'น.ส. ขอ สุ', [$this->handWash, $this->uniform]);

    $text = summaryText($this);

    expect(substr_count($text, 'น.ส. ขอ สุ'))->toBe(1)
        ->and($text)->toContain('ล้างมือ ฆ่าเชื้อ, แต่งกาย ถูกต้อง');
});

/**
 * LINE refuses a text message over 5000 characters outright, which would lose
 * the whole summary rather than the tail of a long list.
 */
it('summarises the rest rather than running past LINE message limit', function () {
    foreach (range(1, 14) as $n) {
        failing($this, 'พนักงาน ' . str_pad($n, 2, '0', STR_PAD_LEFT), [$this->handWash]);
    }

    $text = summaryText($this);

    expect($text)->toContain('และอีก 4 รายการ')
        ->and(mb_strlen($text))->toBeLessThan(5000);
});

it('says nothing about failures when there were none', function () {
    $employee = Employee::create([
        'employee_id' => 'E99', 'fullname' => 'ผ่านหมด',
        'department_id' => $this->dept->id, 'shift_id' => $this->shift->id,
        'qr_code_hash' => 'hok', 'is_active' => true,
    ]);

    InspectionLog::create([
        'session_id' => $this->session->id, 'employee_id' => $employee->id,
        'checkpoint_id' => $this->handWash->id, 'result' => 'pass', 'inspected_at' => now(),
    ]);

    $text = summaryText($this);

    expect($text)->not->toContain('รายการที่ไม่ผ่าน')
        ->and($text)->toContain('พบปัญหา (CAR): 0');
});

/**
 * The cost of a round must not depend on how bad the shift was.
 */
it('costs the same message whether one person failed or fourteen', function () {
    failing($this, 'คนเดียว', [$this->handWash]);
    $one = summaryText($this);

    foreach (range(1, 13) as $n) {
        failing($this, 'เพิ่ม ' . $n, [$this->handWash]);
    }
    $many = summaryText($this);

    // Both are one message. The second simply says more inside it.
    expect($one)->toBeString()->and($many)->toBeString()
        ->and(mb_strlen($many))->toBeGreaterThan(mb_strlen($one));
});
