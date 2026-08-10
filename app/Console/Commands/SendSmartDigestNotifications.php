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
