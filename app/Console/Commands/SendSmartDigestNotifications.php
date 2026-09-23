<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InspectionSession;
use App\Models\User;
use App\Notifications\PendingVerificationDigestNotification;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class SendSmartDigestNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inspection:send-smart-digest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a digest notification of pending verifications to supervisors';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sessions = InspectionSession::where('status', 'completed')
            ->whereNull('notified_at')
            // A completed session is only worth an email while something on it
            // still needs a supervisor. Without this the digest listed every
            // session ever completed, most of them rows of "0 pending, 0 auto".
            ->whereHas('logs', fn ($q) => $q->whereNull('verification_status'))
            // Counted here so rendering the table does not fire two more
            // queries per row.
            ->withCount([
                'logs as pending_logs_count' => fn ($q) => $q->whereNull('verification_status'),
                'logs as auto_verified_logs_count' => fn ($q) => $q->where('verification_status', 'auto_verified'),
            ])
            ->get();

        if ($sessions->isEmpty()) {
            $this->info("No new pending verifications.");
            return;
        }

        $sessionsByDepartment = $sessions->groupBy('department_id');

        foreach ($sessionsByDepartment as $departmentId => $deptSessions) {
            $supervisors = User::where('department_id', $departmentId)
                ->whereIn('level', [3, 4])
                ->get();

            if ($supervisors->isNotEmpty()) {
                Notification::send($supervisors, new PendingVerificationDigestNotification($deptSessions));
                
                foreach ($deptSessions as $session) {
                    $session->update(['notified_at' => Carbon::now()]);
                }
                $this->info("Sent digest for department {$departmentId} with {$deptSessions->count()} sessions.");
            }
        }
    }
}
