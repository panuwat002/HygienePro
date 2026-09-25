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

use App\Models\InspectionSession;

$date = $argv[1] ?? date('Y-m-d');

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

$buckets = [
    'person' => ['personnel'],
    'machine' => ['machine', 'area'],
];

foreach ($buckets as $label => $types) {
    // What the LIST page selects — it filters sessions by type.
    $page = InspectionSession::whereDate('inspection_date', $date)
        ->whereIn('type', $types)
        ->pluck('id')
        ->all();

    // What the PDF EXPORT selects — it applies no type filter at all.
    $pdf = InspectionSession::whereDate('inspection_date', $date)
        ->pluck('id')
        ->all();

    echo "report_type={$label}\n";
    echo "   หน้ารายการ : " . (empty($page) ? '(ไม่มี)' : implode(', ', $page)) . "\n";
    echo "   Export PDF : " . (empty($pdf) ? '(ไม่มี)' : implode(', ', $pdf)) . "\n";

    $extra = array_diff($pdf, $page);
    if (! empty($extra)) {
        echo "   >>> PDF ลากรอบตรวจที่หน้ารายการไม่ได้เลือกเข้ามาด้วย: " . implode(', ', $extra) . "\n";
    }
    echo "\n";
}

echo "=== ผู้บันทึกที่จะถูกพิมพ์ลงกรอบลายเซ็นของ PDF ===\n";
$first = $sessions->first();
echo "   sessions->first() = รอบ #" . ($first->id ?? '-')
    . " (type " . ($first->type ?? '-') . ")"
    . " ผู้ตรวจ: " . ($first?->inspector->name ?? '-') . "\n\n";
