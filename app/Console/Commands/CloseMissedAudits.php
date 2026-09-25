<?php

namespace App\Console\Commands;

use App\Support\RandomAuditLoop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retires random audits whose day has gone by with nobody carrying them out.
 *
 * Without it the dashboard's "ภารกิจสุ่มตรวจวันนี้" card accumulates: every
 * audit `audit:generate` has ever drawn is still 'pending', including ones
 * from August, because no code path has ever moved that column.
 */
class CloseMissedAudits extends Command
{
    protected $signature = 'audit:close-missed';

    protected $description = 'Mark random audits whose date has passed without being carried out as missed';

    public function handle(RandomAuditLoop $loop): int
    {
        $missed = $loop->closeMissed();

        if ($missed === 0) {
            $this->info('No overdue random audits.');

            return Command::SUCCESS;
        }

        $this->warn("Marked {$missed} random audit(s) as missed.");

        // Worth a log line: a department missing its audit week after week is
        // the finding, not the housekeeping.
        Log::info('RandomAudit: retired overdue audits', ['missed' => $missed]);

        return Command::SUCCESS;
    }
}
