<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineMessagingChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (! method_exists($notification, 'toLine')) {
            return;
        }

        $message = $notification->toLine($notifiable);
        
        $token = env('LINE_CHANNEL_ACCESS_TOKEN');
        $groupId = env('LINE_GROUP_ID');

        if (!$token || !$groupId) {
            Log::warning('LINE Messaging API is not configured (Token or Group ID missing). Notification not sent.');
            return;
        }

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])->post('https://api.line.me/v2/bot/message/push', [
                    'to' => $groupId,
                    'messages' => [
                        [
                            'type' => 'text',
                            'text' => $message,
                        ]
                    ]
                ]);

            if ($response->failed()) {
                $responseBody = $response->body();
                if (str_contains($responseBody, 'monthly limit')) {
                    Log::warning('LINE Messaging API Quota Exceeded: ' . $responseBody);
                } else {
                    Log::error('LINE Messaging API Error: ' . $responseBody);
                }
            }
        } catch (\Exception $e) {
            Log::error('LINE Messaging API Exception: ' . $e->getMessage());
        }
    }
}
