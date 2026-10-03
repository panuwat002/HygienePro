<?php

/**
 * Read-only. Measures the two gaps that code cannot close on its own, because
 * both are about records already written.
 *
 *  1. Corrective actions marked Closed with no record of what was done.
 *     The workflow bug that produced them is fixed (8f9db8b), but the rows it
 *     already wrote are still there, each one an audit record claiming a
 *     problem was fixed with nothing behind it. Someone has to go and ask.
 *
 *  2. Inspection logs whose verifier was overwritten by the approver.
 *     Before 238e48b, managerApprove() wrote the manager's id into
 *     verifier_id. The migration moved it to approved_by, so we know who
 *     approved; who verified was destroyed in place. A form reprinted for one
 *     of these rounds shows the manager in the ผู้ทวนสอบ box.
 *
 * Nothing here writes. Run it as often as you like.
 *
 *   C:\PHP\php.exe scripts\server\diagnose-audit-gaps.php 2>nul
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CorrectiveAction;
use Illuminate\Support\Facades\DB;

function heading(string $text): void
{
    echo "\n" . str_repeat('=', 78) . "\n  {$text}\n" . str_repeat('=', 78) . "\n";
}

// ---------------------------------------------------------------------------
heading('1. ใบแจ้งปัญหาที่ปิดไปโดยไม่มีบันทึกว่าแก้อย่างไร');

$orphans = CorrectiveAction::with([
        'log.employee', 'log.session.department', 'log.checkpoint',
        'escalator', 'assignee',
    ])
    ->where('status', 'closed')
    ->where(function ($q) {
        $q->whereNull('action_taken')->orWhere('action_taken', '');
    })
    ->orderBy('closed_at')
    ->get();

echo "\nปิดไปแล้วทั้งหมด : " . CorrectiveAction::where('status', 'closed')->count() . " ใบ\n";
echo "ไม่มีบันทึกวิธีแก้ : " . $orphans->count() . " ใบ\n";

foreach ($orphans as $car) {
    $log = $car->log;
    $session = $log?->session;

    echo "\n  --- ใบที่ #{$car->id} ---\n";
    echo "  แผนก        : " . ($session?->department?->dept_name ?? '-') . "\n";
    echo "  จุดที่ไม่ผ่าน : " . ($log?->checkpoint?->title ?? '-') . "\n";
    echo "  พนักงาน      : " . ($log?->employee?->fullname ?? '(ไม่ใช่การตรวจพนักงาน)') . "\n";
    echo "  วันที่ตรวจ    : " . ($log?->inspected_at?->format('d/m/Y') ?? '-') . "\n";
    echo "  ผู้แจ้ง       : " . ($car->escalator?->name ?? '-') . "\n";
    echo "  ผู้รับผิดชอบ  : " . ($car->assignee?->name ?? '(ไม่เคยมอบหมาย)') . "\n";
    echo "  ปิดเมื่อ      : " . ($car->closed_at?->format('d/m/Y H:i') ?? '-') . "\n";
    echo "  สาเหตุที่บันทึกไว้ : " . ($car->root_cause ?: '(ว่าง)') . "\n";
    echo "  เคยผ่านสถานะ 'แก้ไขแล้ว' : " . ($car->resolved_at ? 'ใช่ ' . $car->resolved_at->format('d/m/Y') : 'ไม่เคย') . "\n";
}

if ($orphans->isEmpty()) {
    echo "\n  ไม่มี — ไม่ต้องตามอะไร\n";
}

// ---------------------------------------------------------------------------
heading('2. ฟอร์มที่ช่องผู้ทวนสอบขึ้นชื่อผู้อนุมัติแทน');

/*
 * The signature of an overwritten row: the migration backfilled approved_by
 * and approved_at straight from verifier_id and verified_at, so for a row
 * written before the fix those pairs are identical. A round approved after the
 * fix has two different people and two different times in them.
 */
$affected = DB::table('inspection_logs')
    ->where('verification_status', 'approved')
    ->whereNotNull('approved_by')
    ->whereColumn('approved_by', 'verifier_id')
    ->whereColumn('approved_at', 'verified_at');

$logCount = (clone $affected)->count();
$sessionIds = (clone $affected)->distinct()->pluck('session_id');

echo "\nรายการตรวจที่กระทบ : " . number_format($logCount) . " แถว\n";
echo "รอบตรวจที่กระทบ    : " . $sessionIds->count() . " รอบ\n";

if ($sessionIds->isNotEmpty()) {
    $range = DB::table('inspection_sessions')
        ->whereIn('id', $sessionIds)
        ->selectRaw('MIN(inspection_date) AS first_date, MAX(inspection_date) AS last_date')
        ->first();

    echo "ช่วงวันที่         : {$range->first_date} ถึง {$range->last_date}\n";

    // Can any of them be recovered? The session keeps verified_by separately
    // and approval never touched it - but it holds only the LAST supervisor to
    // verify that round, so it is a guess wherever two people shared one.
    $recoverable = DB::table('inspection_sessions')
        ->whereIn('id', $sessionIds)
        ->whereNotNull('verified_by')
        ->pluck('verified_by', 'id');

    echo "\nรอบที่ยังมี session.verified_by เหลืออยู่ : " . $recoverable->count()
        . " จาก " . $sessionIds->count() . " รอบ\n";

    $differs = DB::table('inspection_sessions as s')
        ->join('inspection_logs as l', 'l.session_id', '=', 's.id')
        ->whereIn('s.id', $sessionIds)
        ->whereNotNull('s.verified_by')
        ->whereColumn('s.verified_by', '!=', 'l.verifier_id')
        ->distinct()
        ->count('s.id');

    echo "ในนั้น เป็นคนละคนกับที่ค้างอยู่ใน log : {$differs} รอบ"
        . "  <-- เติมกลับได้ แต่เป็นการเดาถ้ารอบนั้นมีผู้ทวนสอบหลายคน\n";
}

echo "\n";
