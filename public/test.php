<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$sessions = App\Models\InspectionSession::orderBy('id', 'desc')->take(10)->get();
foreach ($sessions as $s) {
    echo "Session {$s->id} ({$s->inspection_date}) - People Inspected: " . $s->logs()->distinct('employee_id')->count('employee_id') . "\n";
}
