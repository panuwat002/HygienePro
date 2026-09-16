<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestLineNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'line:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sending a message to LINE group';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = config('services.line.token');
        $groupId = config('services.line.group_id');

        if (!$token || !$groupId) {
            $this->error('LINE_CHANNEL_ACCESS_TOKEN or LINE_GROUP_ID is missing in .env');
            return;
        }

        $this->info('Sending test message to LINE Group: ' . $groupId);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ])->post('https://api.line.me/v2/bot/message/push', [
            'to' => $groupId,
            'messages' => [
                [
                    'type' => 'text',
                    'text' => '✅ ทดสอบจากระบบ HygienePro: การเชื่อมต่อสมบูรณ์ 100% พร้อมใช้งานครับ!',
                ]
            ]
        ]);

        if ($response->successful()) {
            $this->info('Message sent successfully!');
        } else {
            $this->error('Failed to send message: ' . $response->body());
        }
    }
}
