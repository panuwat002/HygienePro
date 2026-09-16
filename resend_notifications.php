<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sessionIds = [21, 22, 27];

foreach ($sessionIds as $id) {
    $session = App\Models\InspectionSession::find($id);
    if ($session && $session->status === 'completed') {
        echo "Resending LINE notification for Session ID: $id\n";
        
        $targetColumn = 'employee_id';
        $unit = 'คน';
        if ($session->type === 'machine') {
            $targetColumn = 'machine_id';
            $unit = 'เครื่อง';
        } elseif ($session->type === 'area') {
            $targetColumn = 'location_id';
            $unit = 'พื้นที่';
        }
        
        $totalTargets = $session->logs()->distinct($targetColumn)->count($targetColumn);
        $failedTargets = $session->logs()->where('result', 'fail')->distinct($targetColumn)->count($targetColumn);
        $passedTargets = $totalTargets - $failedTargets;

        $stats = [
            'total' => $session->logs()->count(),
            'pass' => $session->logs()->where('result', 'pass')->count(),
            'fail' => $session->logs()->where('result', 'fail')->count(),
            'total_targets' => $totalTargets,
            'passed_targets' => $passedTargets,
            'failed_targets' => $failedTargets,
            'unit' => $unit
        ];
        $randomAssigned = $session->logs()->whereNull('verification_status')->count();
        
        try {
            \Illuminate\Support\Facades\Notification::route(\App\Channels\LineMessagingChannel::class, '')
                ->notify(new \App\Notifications\SessionSummaryLineNotification($session, $stats, $randomAssigned));
            echo "Success for Session ID: $id\n";
        } catch (\Exception $e) {
            echo "Failed for Session ID: $id - " . $e->getMessage() . "\n";
        }
    } else {
        echo "Session ID $id not found or not completed.\n";
    }
}
echo "Done.\n";
