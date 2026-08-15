<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$session = \App\Models\InspectionSession::orderBy('id', 'desc')->first();
$logs = \App\Models\InspectionLog::where('session_id', $session->id)->get();
echo "Session ID: " . $session->id . "\n";
echo "Time: " . $session->inspection_date->format('H:i') . "\n";
echo "Total distinct employees: " . $logs->distinct('employee_id')->count('employee_id') . "\n";
$pending = $logs->whereNull('verification_status')->pluck('employee_id')->unique()->count();
echo "Pending (randomly selected): " . $pending . "\n";
$auto = $logs->where('verification_status', 'auto_verified')->pluck('employee_id')->unique()->count();
echo "Auto verified: " . $auto . "\n";
