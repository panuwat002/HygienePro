<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InspectionSession;
use App\Models\User;
use App\Notifications\VerificationReminderNotification;
use App\Notifications\VerificationEscalatedNotification;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class EscalatePendingVerifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inspection:escalate-verifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders and escalate pending verifications to supervisors and managers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sessions = InspectionSession::where('status', 'completed')
            ->where('is_locked', false)
            ->get();

        $remindedCount = 0;
        $escalatedCount = 0;

        foreach ($sessions as $session) {
            $lastUpdated = $session->updated_at ?? $session->created_at;
            if (!$lastUpdated) continue;

            // Diff forward from the older moment. Carbon 3 returns a signed
            // value, so now()->diffInHours($past) is negative and both
            // thresholds below were unreachable - this command has never
            // reminded or escalated anyone.
            $hoursPending = $lastUpdated->diffInHours(Carbon::now());

            if ($hoursPending >= 24 && is_null($session->escalated_at)) {
                $managers = User::where('department_id', $session->department_id)
                    ->where('level', '>=', 5)
                    ->get();
                
                if ($managers->isNotEmpty()) {
                    Notification::send($managers, new VerificationEscalatedNotification($session));
                    $session->update(['escalated_at' => Carbon::now()]);
                    $escalatedCount++;
                    $this->info("Escalated session {$session->id} to managers.");
                }
            } 
            elseif ($hoursPending >= 4 && is_null($session->reminded_at) && is_null($session->escalated_at)) {
                $supervisors = User::where('department_id', $session->department_id)
                    ->whereIn('level', [3, 4])
                    ->get();

                if ($supervisors->isNotEmpty()) {
                    Notification::send($supervisors, new VerificationReminderNotification($session));
                    $session->update(['reminded_at' => Carbon::now()]);
                    $remindedCount++;
                    $this->info("Reminded supervisors for session {$session->id}.");
                }
            }
        }

        $this->info("Process completed. Reminded: {$remindedCount}, Escalated: {$escalatedCount}");
    }
}
