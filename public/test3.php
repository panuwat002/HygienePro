<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$out = "";
$sessions = App\Models\InspectionSession::orderBy('id', 'desc')->take(5)->get();
foreach ($sessions as $s) {
    $out .= "Session {$s->id} - {$s->inspection_date}\n";
    $logs = App\Models\InspectionLog::where('session_id', $s->id)
                ->whereNull('verified_at')
                ->get();
    foreach ($logs as $l) {
        $out .= "  Log {$l->id}: Emp {$l->employee_id}, Status: " . ($l->verification_status ?? 'null') . ", Result: {$l->result}\n";
    }
}
file_put_contents(__DIR__.'/../storage/logs/test_out.txt', $out);
