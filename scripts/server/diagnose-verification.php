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
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
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

// Splits each page's time into what the database spent and what PHP spent.
$sqlMs = 0;
$sqlCount = 0;
DB::listen(function ($q) use (&$sqlMs, &$sqlCount) {
    $sqlMs += $q->time;
    $sqlCount++;
});

// date= with nothing in it is what the page's own filter form submits, and
// ConvertEmptyStringsToNull makes that a different input from no date at all.
// Measuring only the tidy URLs once hid a 500 on every link the page builds.
$urls = [];
foreach (['person', 'machine'] as $type) {
    foreach (['pending', 'completed', 'reclean'] as $tab) {
        $urls[] = "/verification?filter_type={$type}&tab={$tab}";
        $urls[] = "/verification?date=&filter_type={$type}&tab={$tab}";
    }
}

$controller = $app->make(App\Http\Controllers\InspectionController::class);
$failures = [];

printf("%-38s %8s %8s %7s %8s %8s\n", 'url', 'total', 'sql', 'queries', 'php', 'rows');
foreach ($urls as $url) {
    $request = Request::create($url, 'GET');

    // The controller is called directly so the auth guard stays simple, but the
    // one middleware that changes its input has to run: without it date= arrives
    // as an empty string here and as null in the browser, which is exactly the
    // difference that let a 500 through while this script reported milliseconds.
    (new ConvertEmptyStringsToNull())->handle($request, function ($prepared) use (&$request) {
        $request = $prepared;
    });

    $request->setUserResolver(fn () => $verifier);
    $app->instance('request', $request);

    $hydrated = 0;
    $sqlMs = 0;
    $sqlCount = 0;

    $start = microtime(true);
    try {
        $controller->verification($request)->render();
        $failure = null;
    } catch (\Throwable $e) {
        // Never swallowed: a page that throws is the finding, not a missing row.
        // Hiding this once left the page returning 500 while this script said fine.
        $failure = get_class($e) . ': ' . $e->getMessage();
        $failures[] = str_replace('/verification?', '', $url) . "\n    " . $failure;
    }
    $totalMs = round((microtime(true) - $start) * 1000);

    printf(
        "%-38s %8s %8s %7s %8s %8s\n",
        str_replace('/verification?', '', $url),
        $failure ? 'ERROR' : $totalMs . ' ms',
        round($sqlMs) . ' ms',
        $sqlCount,
        ($totalMs - round($sqlMs)) . ' ms',
        number_format($hydrated)
    );
}

if ($failures) {
    echo "\n!!! มีหน้าที่พัง " . count($failures) . " หน้า !!!\n";
    foreach ($failures as $f) {
        echo '  ' . $f . "\n";
    }
}

echo "\n=== ต้นทุนต่อแถว (อ้างอิง) ===\n";
echo "บรรทัดที่ขึ้นต้นด้วย [เลิกทำแล้ว] จำลองวิธีเดิมเพื่อวัดว่าตัดอะไรออกไป\n";
echo "ไม่ใช่สิ่งที่หน้าเว็บทำอยู่ตอนนี้\n\n";

// The controller's own detail load, repeated here so each phase can be timed.
$sessionIds = DB::table('inspection_logs')
    ->join('inspection_sessions', 'inspection_sessions.id', '=', 'inspection_logs.session_id')
    ->whereIn('inspection_logs.verification_status', ['reclean', 'verified'])
    ->whereNotNull('inspection_logs.employee_id')
    ->groupBy('inspection_logs.session_id')
    ->orderByRaw('max(inspection_logs.inspected_at) desc')
    ->limit(20)
    ->pluck('inspection_logs.session_id');

$sqlMs = 0;

$start = microtime(true);
$logs = InspectionLog::with(['employee', 'checkpoint', 'employee.department', 'employee.shift', 'location', 'machine', 'session.inspector', 'session.department', 'verifier', 'correctiveAction.approvals'])
    ->whereIn('session_id', $sessionIds)
    ->orderBy('inspected_at', 'desc')
    ->get();
$loadMs = round((microtime(true) - $start) * 1000);

line('แถวที่โหลด (20 session)', number_format($logs->count()));
line('  - ในนั้นเป็นเวลา SQL', round($sqlMs) . ' ms');
line('  - ที่เหลือคือ hydrate เป็น model', ($loadMs - round($sqlMs)) . ' ms');

$start = microtime(true);
$groups = $logs->groupBy(fn ($log) => $log->employee_id
    ? $log->session_id . '_personnel'
    : $log->session_id . '_loc_' . ($log->location_id ?? 'unknown'));
line('จัดกลุ่ม', round((microtime(true) - $start) * 1000) . ' ms');
line('  จำนวนกลุ่ม', $groups->count());
line('  log ต่อกลุ่ม (เฉลี่ย)', $groups->count() ? round($logs->count() / $groups->count()) : 0);

// Every read of a date column re-parses it into a Carbon instance - there is no
// per-model cache for date casts - and the group builder walks the logs of each
// group a dozen times over.
$start = microtime(true);
foreach ($groups as $g) {
    $g->max('inspected_at');
    $g->max('inspected_at');
    $g->contains(fn ($l) => is_null($l->verified_at));
    $g->every(fn ($l) => ! is_null($l->acknowledged_at));
}
line('  [เลิกทำแล้ว] อ่านคอลัมน์วันที่ซ้ำ', round((microtime(true) - $start) * 1000) . ' ms');

$start = microtime(true);
foreach ($groups as $g) {
    $g->pluck('employee_id')->unique()->count();
    $g->contains('result', 'fail');
    $g->every(fn ($l) => in_array($l->verification_status, ['auto_verified', 'approved', 'verified']));
    $g->every(fn ($l) => $l->verification_status === 'approved');
    $g->every(fn ($l) => $l->verification_status === 'auto_verified');
    $g->contains('verification_status', 'reclean');
    $g->contains('verification_status', 'rejected');
    $g->filter(fn ($l) => $l->result === 'fail');
    $g->pluck('id')->toArray();
    $g->whereIn('result', ['pass', 'fail'])->count();
}
line('  [เลิกทำแล้ว] เดินวน log นับซ้ำ', round((microtime(true) - $start) * 1000) . ' ms');

echo "\n";
line('หน่วยความจำสูงสุดที่ใช้', round(memory_get_peak_usage(true) / 1048576) . ' MB');

echo "\nเสร็จแล้ว\n";
