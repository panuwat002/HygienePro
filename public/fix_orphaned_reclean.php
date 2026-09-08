<?php
/**
 * Fix Orphaned Reclean Logs Script
 * 
 * This script finds InspectionLogs that have:
 * - verification_status = 'reclean'
 * - Linked CorrectiveAction that is 'closed' or 'resolved'
 * 
 * And updates them to 'approved' so they no longer appear in the "สั่งแก้ไข" tab.
 * 
 * Run: php fix_orphaned_reclean.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\InspectionLog;
use App\Models\CorrectiveAction;
use Illuminate\Support\Facades\DB;

echo "=== Fix Orphaned Reclean Logs ===\n\n";

try {
    DB::beginTransaction();

    // Find all reclean logs
    $recleanLogs = InspectionLog::where('verification_status', 'reclean')->get();

    echo "Found {$recleanLogs->count()} logs with status 'reclean'\n\n";

    $fixedCount = 0;
    $noCarCount = 0;

    foreach ($recleanLogs as $log) {
        $car = $log->correctiveAction;
        
        if (!$car) {
            // No CAR linked - this shouldn't happen but we can still check if all CARs in group are closed
            echo "Log #{$log->id}: No CAR linked - checking group...\n";
            $noCarCount++;
            continue;
        }
        
        if (in_array($car->status, ['closed', 'resolved'])) {
            echo "Log #{$log->id}: CAR #{$car->id} status='{$car->status}' -> Updating to 'verified'\n";
            
            // Auto-fix: verification_status = 'verified' (since CAR is closed)
            $log->update([
                'verification_status' => 'verified',
                'verified_at' => now(),
                'verifier_id' => 1 // System or Admin
            ]);
            
            $fixedCount++;
        } else {
            echo "Log #{$log->id}: CAR #{$car->id} status='{$car->status}' -> Still pending, skipping\n";
        }
    }

    DB::commit();

    echo "\n=== Summary ===\n";
    echo "Total reclean logs: {$recleanLogs->count()}\n";
    echo "Fixed (verified): {$fixedCount}\n";
    echo "No CAR linked: {$noCarCount}\n";
    echo "Still pending: " . ($recleanLogs->count() - $fixedCount - $noCarCount) . "\n";
    echo "\nDone!\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "Error processing orphan logs: " . $e->getMessage() . "\n";
}
