<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CorrectiveAction;
use App\Services\AIService;

class BackfillAiTags extends Command
{
    protected $signature = 'ai:backfill';
    protected $description = 'Generate AI Tags for all existing Corrective Actions that do not have them yet';

    public function handle()
    {
        $this->info("Scanning for existing records without AI tags...");

        // Fetch all actions, we will check in PHP if they need updating
        $actions = CorrectiveAction::all();

        if ($actions->isEmpty()) {
            $this->info("✅ All records already have AI tags!");
            return 0;
        }

        $this->info("Found " . $actions->count() . " records to process. Contacting Python AI Server...");
        $bar = $this->output->createProgressBar($actions->count());

        $updatedCount = 0;

        foreach ($actions as $action) {
            // Find the original finding text
            $text = '';
            if ($action->log) {
                // Use correction_action if available, fallback to finding, fallback to root_cause
                $text = $action->log->correction_action ?? $action->log->finding ?? $action->root_cause ?? '';
            } else {
                $text = $action->root_cause ?? '';
            }

            if (empty($action->ai_tags) && !empty(trim($text))) {
                $tags = AIService::getTagsFromFinding($text);
                if (!empty($tags)) {
                    $action->ai_tags = $tags;
                    $action->save();
                    $updatedCount++;
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("🎉 Done! Successfully generated AI tags for $updatedCount old records.");
        $this->info("You can now refresh the browser to see the tags on old items.");

        return 0;
    }
}
