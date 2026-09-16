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
        // This route is public and CSRF-exempt (bootstrap/app.php), so the signature
        // is the only thing distinguishing LINE from anyone else on the network.
        // Without it, arbitrary attacker text was written into laravel.log on every
        // unauthenticated POST — forged log entries plus an uncapped disk-fill.
        if (! $this->hasValidSignature($request)) {
            Log::warning('Rejected LINE webhook with missing or invalid signature.', [
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature', 403);
        }

        $events = $request->input('events');

        if (! is_array($events)) {
            return response('OK', 200);
        }

        foreach ($events as $event) {
            // data_get() instead of direct array access: a malformed event such as
            // {"events":[{}]} previously raised "Undefined array key" and 500ed.
            $type = data_get($event, 'type');
            $sourceType = data_get($event, 'source.type');

            if ($type !== 'join' && $type !== 'message') {
                continue;
            }

            if ($sourceType === 'group') {
                $groupId = data_get($event, 'source.groupId');
                $prefix = $type === 'join' ? 'LINE Bot joined group' : 'Message received from group';
                Log::info($prefix . '. Group ID: ' . $groupId);
            } elseif ($type === 'join' && $sourceType === 'room') {
                Log::info('LINE Bot joined room. Room ID: ' . data_get($event, 'source.roomId'));
            }
        }

        return response('OK', 200);
    }

    /**
     * Verify the X-Line-Signature header against the raw request body, as required
     * by the LINE Messaging API.
     */
    private function hasValidSignature(Request $request): bool
    {
        $secret = config('services.line.secret');
        $signature = $request->header('X-Line-Signature');

        if (! $secret || ! $signature) {
            return false;
        }

        $expected = base64_encode(
            hash_hmac('sha256', $request->getContent(), $secret, true)
        );

        return hash_equals($expected, $signature);
    }
}
