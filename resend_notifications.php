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
        
        $stats = [
            'total' => $session->logs()->count(),
            'pass' => $session->logs()->where('result', 'pass')->count(),
            'fail' => $session->logs()->where('result', 'fail')->count(),
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
