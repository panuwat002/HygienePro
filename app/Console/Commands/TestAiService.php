<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestAiService extends Command
{
    protected $signature = 'ai:test {text=พบเศษแก้วตกอยู่ และพื้นมีคราบน้ำมันเลอะเทอะ}';
    protected $description = 'Test the Python AI Service connection';

    public function handle()
    {
        $text = $this->argument('text');

        $this->info("Testing AI Service...");
        $this->info("Text: " . $text);
        $this->newLine();

        // Test raw HTTP connection first
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(3)->get('http://127.0.0.1:8001');
            $this->info("✅ Python server is reachable!");
        } catch (\Exception $e) {
            $this->error("❌ Cannot reach Python server: " . $e->getMessage());
            $this->warn("Make sure Python is running: python ai_service/main.py");
            return 1;
        }

        // Test AI tagging
        $tags = \App\Services\AIService::getTagsFromFinding($text);

        if (!empty($tags)) {
            $this->info("✅ AI Tags generated successfully:");
            foreach ($tags as $tag) {
                $this->line("   🏷  " . $tag);
            }
        } else {
            $this->warn("⚠️ AI returned no tags for this text.");
            $this->warn("Keywords in text might not match dictionary in main.py");
        }

        return 0;
    }
}
