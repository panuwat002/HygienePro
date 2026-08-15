<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineWebhookController extends Controller
{
    /**
     * Handle incoming webhooks from LINE Messaging API.
     */
    public function handle(Request $request)
    {
        $events = $request->input('events', []);

        foreach ($events as $event) {
            // Log when bot is added to a group or room
            if ($event['type'] === 'join') {
                $source = $event['source'];
                if ($source['type'] === 'group') {
                    $groupId = $source['groupId'];
                    Log::info("LINE Bot joined group. Group ID: {$groupId}");
                } elseif ($source['type'] === 'room') {
                    $roomId = $source['roomId'];
                    Log::info("LINE Bot joined room. Room ID: {$roomId}");
                }
            }
            
            // Also log group ID if someone sends a message in the group
            if ($event['type'] === 'message') {
                $source = $event['source'];
                if ($source['type'] === 'group') {
                    $groupId = $source['groupId'];
                    Log::info("Message received from group. Group ID: {$groupId}");
                }
            }
        }

        return response('OK', 200);
    }
}
