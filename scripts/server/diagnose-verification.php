<?php

/**
 * Read-only measurement of the /verification page against real data.
 *
 * Reports how much data exists, then runs the real controller for each tab and
 * reports how long it took and how many rows it turned into models. Nothing is
 * written: every request it makes is a GET.
 *
 * Run:  C:\PHP\php.exe scripts\server\diagnose-verification.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// The point of the exercise is to find out how much the page loads, so let it
// load - a memory ceiling here would hide the number we came for.
ini_set('memory_limit', '-1');

use App\Models\InspectionLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

function line(string $label, $value): void
{
    printf("%-46s %s\n", $label, $value);
}

echo "\n=== ขนาดข้อมูลจริง ===\n";
line('inspection_logs ทั้งหมด', number_format(InspectionLog::count()));
line('inspection_sessions ทั้งหมด', number_format(DB::table('inspection_sessions')->count()));

echo "\n=== แยกตามสถานะ ===\n";
$rows = DB::table('inspection_logs')
    ->selectRaw("IFNULL(verification_status, '(null)') AS st, verified_at IS NULL AS no_verified_at, COUNT(*) AS n")
    ->groupBy('st', 'no_verified_at')
    ->orderByDesc('n')
    ->get();
foreach ($rows as $r) {
    line('  ' . $r->st . ' / verified_at ' . ($r->no_verified_at ? 'NULL' : 'set'), number_format($r->n));
}

echo "\n=== แต่ละเงื่อนไขใน inbox mode ครอบคลุมกี่แถว ===\n";
$today = date('Y-m-d');
line('A) verified_at IS NULL', number_format(InspectionLog::whereNull('verified_at')->count()));
line('B) status IN (reclean, verified)', number_format(
    InspectionLog::whereIn('verification_status', ['reclean', 'verified'])->count()
));
line('C) approved/auto_verified ของวันนี้', number_format(
    InspectionLog::whereIn('verification_status', ['approved', 'auto_verified'])
        ->whereDate('inspected_at', $today)->count()
));

// The page is for whoever can verify, so measure as one of them.
$verifier = User::whereNotNull('email')
    ->where(fn ($q) => $q->where('role', 'supervisor')->orWhere('level', '>=', 4))
    ->get()
    ->first(fn (User $u) => $u->canVerify() || $u->canApprove() || $u->isAdmin());

if (! $verifier) {
    echo "\nไม่พบผู้ใช้ที่มีสิทธิ์ทวนสอบ — ข้ามการวัดเวลาหน้าเว็บ\n";
    exit(0);
}

echo "\n=== เวลาต่อหน้า (ผู้ใช้: {$verifier->name}) ===\n";
Auth::login($verifier);

$hydrated = 0;
InspectionLog::retrieved(function () use (&$hydrated) {
    $hydrated++;
});

$urls = [
    '/verification?filter_type=person&tab=pending',
    '/verification?filter_type=person&tab=completed',
    '/verification?filter_type=person&tab=reclean',
    '/verification?filter_type=machine&tab=pending',
    '/verification?filter_type=machine&tab=completed',
    '/verification?filter_type=machine&tab=reclean',
];

$controller = $app->make(App\Http\Controllers\InspectionController::class);

printf("%-44s %9s %9s %9s\n", 'url', 'controller', 'render', 'rows');
foreach ($urls as $url) {
    $request = Request::create($url, 'GET');
    $request->setUserResolver(fn () => $verifier);
    $app->instance('request', $request);

    $hydrated = 0;
    DB::flushQueryLog();

    $start = microtime(true);
    $view = $controller->verification($request);
    $controllerMs = round((microtime(true) - $start) * 1000);

    $start = microtime(true);
    try {
        $view->render();
        $renderMs = round((microtime(true) - $start) * 1000) . ' ms';
    } catch (\Throwable $e) {
        $renderMs = 'error';
    }

    printf(
        "%-44s %9s %9s %9s\n",
        str_replace('/verification?', '', $url),
        $controllerMs . ' ms',
        $renderMs,
        number_format($hydrated)
    );
}

line('หน่วยความจำสูงสุดที่ใช้', round(memory_get_peak_usage(true) / 1048576) . ' MB');

echo "\nเสร็จแล้ว\n";
