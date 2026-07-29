<?php

namespace App\Console\Commands;

use App\Services\InspectionService;
use Illuminate\Console\Command;

class AutoCloseStaleSessions extends Command
{
    protected $signature = 'inspections:auto-close-stale';

    protected $description = 'Auto-close inspection sessions left open past their shift end + grace period';

    public function handle(InspectionService $service): int
    {
        $count = $service->autoCloseStaleSessions();
        $this->info("Auto-closed {$count} stale inspection session(s).");

        return self::SUCCESS;
    }
}
