<?php

/**
 * Read-only: what the daily report is actually looking at for one date.
 *
 * Reports every session on that date with its type and log make-up, then runs
 * the same two filters the page and the PDF export apply, so any disagreement
 * between them shows up side by side.
 *
 * Run:  C:\PHP\php.exe scripts\server\diagnose-daily-report.php 2026-09-25
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ReportController;
use App\Models\InspectionSession;

$date = $argv[1] ?? date('Y-m-d');

/**
 * Both report paths are asked through the controller's own method rather than
 * a copy of its rules here, so this stays true whatever that method becomes.
 */
$applyTypeScope = function ($query, string $reportType) {
    $method = new ReflectionMethod(ReportController::class, 'scopeSessionsToReportType');
    $method->setAccessible(true);
    $method->invoke(app(ReportController::class), $query, $reportType);

    return $query;
};

echo "\n=== รอบตรวจทั้งหมดของวันที่ {$date} ===\n";
printf("%-6s %-12s %-26s %-16s %7s %7s %7s %7s\n",
    'ID', 'type', 'department', 'shift', 'logs', 'emp', 'machine', 'area');

$sessions = InspectionSession::with('department')
    ->whereDate('inspection_date', $date)
    ->orderBy('id')
    ->get();

foreach ($sessions as $s) {
    $logs = $s->logs()->get(['employee_id', 'machine_id', 'location_id']);

    printf("%-6s %-12s %-26s %-16s %7d %7d %7d %7d\n",
        $s->id,
        $s->type ?? '(null)',
        mb_substr($s->department->dept_name ?? '-', 0, 24),
        mb_substr((string) $s->shift, 0, 14),
        $logs->count(),
        $logs->whereNotNull('employee_id')->count(),
        $logs->whereNotNull('machine_id')->count(),
        $logs->filter(fn ($l) => $l->location_id && ! $l->machine_id && ! $l->employee_id)->count()
    );
}

echo "\n=== รอบตรวจที่แต่ละตัวกรองเลือกได้ ===\n";

foreach (['all', 'person', 'machine', 'area'] as $reportType) {
    $scoped = $applyTypeScope(
        InspectionSession::with('inspector')->whereDate('inspection_date', $date)->orderBy('id'),
        $reportType
    )->get();

    $ids = $scoped->pluck('id')->all();
    $first = $scoped->first();

    echo "report_type={$reportType}\n";
    echo "   รอบตรวจ    : " . (empty($ids) ? '(ไม่มี)' : implode(', ', $ids)) . "\n";

    // The ผู้บันทึก box on the printed form comes from this one round, so a
    // round of the wrong type reaching the front of the list is what put the
    // wrong person's name on an area form.
    echo "   ผู้บันทึกบนฟอร์ม: รอบ #" . ($first->id ?? '-')
        . " (type " . ($first->type ?? '-') . ") "
        . ($first?->inspector->name ?? '-') . "\n";

    if ($first && $reportType !== 'all') {
        $expected = $reportType === 'person' ? ['personnel'] : ['machine', 'area'];
        if (! in_array($first->type, $expected, true)) {
            echo "   >>> ผิด: ฟอร์มจะเซ็นด้วยรอบประเภท '{$first->type}'\n";
        }
    }
    echo "\n";
}
