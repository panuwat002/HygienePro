<?php

namespace App\Console\Commands;

use App\Models\CorrectiveAction;
use App\Models\InspectionSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Is the LINE channel working, and can it afford what we point at it?
 *
 * The channel blew its monthly quota on 2026-09-03 and was switched off. Two
 * things have to be true before turning it back on: the API has to answer, and
 * the volume we would send has to fit inside the allowance. This reports both.
 *
 * Sends nothing unless --send is passed. The quota endpoints are GETs and do
 * not consume messages.
 */
class CheckLineChannel extends Command
{
    protected $signature = 'line:check {--send : Push one test message, spending one of the allowance}';

    protected $description = 'Report the LINE channel quota and what this system would send against it';

    public function handle(): int
    {
        $token = config('services.line.token');
        $groupId = config('services.line.group_id');

        $this->components->twoColumnDetail('Channel access token', $this->mask($token));
        $this->components->twoColumnDetail('Target (group id)', $this->mask($groupId));

        if (! $token || ! $groupId) {
            $this->components->error('LINE is not configured. Set LINE_CHANNEL_ACCESS_TOKEN and LINE_GROUP_ID.');

            return Command::FAILURE;
        }

        if (! $this->reportQuota($token)) {
            return Command::FAILURE;
        }

        // The quota above is the half that matters most and it has already
        // printed. A database that cannot be reached must not take it away.
        try {
            $this->reportProjectedVolume();
        } catch (\Throwable $e) {
            $this->newLine();
            $this->components->warn('Could not measure what we would send: ' . $e->getMessage());
        }

        if ($this->option('send')) {
            return $this->sendProbe($token, $groupId);
        }

        $this->newLine();
        $this->components->info('Nothing was sent. Add --send to push one test message.');

        return Command::SUCCESS;
    }

    /**
     * What LINE itself says is left. Asked rather than assumed, because the
     * only reason the channel was off is that we ran out.
     */
    private function reportQuota(string $token): bool
    {
        $this->newLine();
        $this->components->info('Quota, as reported by LINE');

        // TLS verification stays on. This request carries the channel access
        // token in an Authorization header.
        $headers = ['Authorization' => 'Bearer ' . $token];

        try {
            $quota = Http::withHeaders($headers)->get('https://api.line.me/v2/bot/message/quota');
            $used = Http::withHeaders($headers)->get('https://api.line.me/v2/bot/message/quota/consumption');
        } catch (\Throwable $e) {
            $this->components->error('Could not reach the LINE API: ' . $e->getMessage());

            return false;
        }

        if ($quota->failed()) {
            // The body is the useful part - an expired token and a blown quota
            // say different things here.
            $this->components->error('LINE refused the quota request: ' . $quota->body());

            return false;
        }

        $type = $quota->json('type');
        $limit = $quota->json('value');
        $consumed = $used->successful() ? $used->json('totalUsage') : null;

        $this->components->twoColumnDetail('Plan type', (string) $type);
        $this->components->twoColumnDetail('Monthly allowance', $limit === null ? 'unlimited' : number_format($limit));
        $this->components->twoColumnDetail('Used this month', $consumed === null ? '(unavailable)' : number_format($consumed));

        if ($limit !== null && $consumed !== null) {
            $left = max(0, $limit - $consumed);
            $this->components->twoColumnDetail('Left', number_format($left));

            if ($left === 0) {
                $this->components->warn('Nothing left this month. Anything sent now will be refused.');
            }
        }

        return true;
    }

    /**
     * What this system would spend in a month, measured from the last 30 days
     * of real inspections rather than guessed.
     */
    private function reportProjectedVolume(): void
    {
        $since = now()->subDays(30);

        // One push per EMPLOYEE with a failure, not per round - this is the
        // line that spends the allowance.
        $perFailedPerson = DB::table('inspection_logs')
            ->join('inspection_sessions', 'inspection_sessions.id', '=', 'inspection_logs.session_id')
            ->where('inspection_logs.result', 'fail')
            ->whereNotNull('inspection_logs.employee_id')
            ->where('inspection_sessions.inspection_date', '>=', $since->toDateString())
            ->distinct()
            ->count(DB::raw("CONCAT(inspection_logs.session_id, '-', inspection_logs.employee_id)"));

        // One per finished round.
        $perRound = InspectionSession::where('inspection_date', '>=', $since->toDateString())
            ->where('status', 'completed')
            ->count();

        // One per area/machine target with a failure, from the bulk area save.
        $perFailedArea = DB::table('inspection_logs')
            ->join('inspection_sessions', 'inspection_sessions.id', '=', 'inspection_logs.session_id')
            ->where('inspection_logs.result', 'fail')
            ->whereNull('inspection_logs.employee_id')
            ->where('inspection_sessions.inspection_date', '>=', $since->toDateString())
            ->distinct()
            ->count(DB::raw("CONCAT(inspection_logs.session_id, '-', IFNULL(inspection_logs.machine_id, 0), '-', IFNULL(inspection_logs.location_id, 0))"));

        // Daily, whether or not anything happened.
        $digest = 30;

        // Daily, but only on days something was actually overdue.
        $overdueDays = CorrectiveAction::whereNotNull('due_date')
            ->where('due_date', '>=', $since)
            ->whereNotIn('status', ['closed'])
            ->count() > 0 ? 30 : 0;

        $rows = [
            ['แจ้งพนักงานตรวจไม่ผ่าน (รายคน)', $perFailedPerson, 'InspectionController::storeLog'],
            ['สรุปรอบตรวจ (รายรอบ)', $perRound, 'InspectionService::finishSession'],
            ['แจ้งพื้นที่/เครื่องจักรไม่ผ่าน', $perFailedArea, 'AreaInspectionController'],
            ['สรุปประจำวัน', $digest, 'routes/console.php 08:00'],
            ['CAR เกินกำหนด', $overdueDays, 'car:check-overdue 08:30'],
        ];

        $this->newLine();
        $this->components->info('What this system would send in a month (from the last 30 days of real data)');
        $this->table(['Notification', 'Messages/month', 'Sent from'], $rows);

        $total = array_sum(array_column($rows, 1));
        $this->components->twoColumnDetail('<fg=yellow>TOTAL</>', '<fg=yellow>' . number_format($total) . ' messages/month</>');
    }

    private function sendProbe(string $token, string $groupId): int
    {
        $text = "ทดสอบการแจ้งเตือน HygienePro\n"
            . 'ส่งเมื่อ ' . now()->format('d/m/Y H:i') . "\n"
            . 'หากได้รับข้อความนี้ แปลว่าช่องทาง LINE กลับมาใช้งานได้แล้ว';

        $this->newLine();
        $this->components->info('Sending one test message...');

        // Deliberately not routed through LineMessagingChannel: that logs and
        // swallows the API's answer, and the answer is the whole point here.
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ])->post('https://api.line.me/v2/bot/message/push', [
            'to' => $groupId,
            'messages' => [['type' => 'text', 'text' => $text]],
        ]);

        if ($response->successful()) {
            $this->components->info('LINE accepted it (HTTP ' . $response->status() . '). Check the group.');

            return Command::SUCCESS;
        }

        $this->components->error('LINE refused it (HTTP ' . $response->status() . ')');
        $this->line('  ' . $response->body());

        return Command::FAILURE;
    }

    private function mask(?string $value): string
    {
        if (! $value) {
            return '<fg=red>not set</>';
        }

        return strlen($value) <= 8
            ? 'set'
            : 'set (' . substr($value, 0, 4) . '...' . substr($value, -4) . ')';
    }
}
