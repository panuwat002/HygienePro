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
            // Machine rounds cover 16-26 machines per room, so they dominated
            // the email without changing what a supervisor does about it.
            ->whereIn('type', ['personnel', 'area'])
            // Counted here so rendering the table does not fire one more query
            // per row.
            ->withCount([
                'logs as pending_logs_count' => fn ($q) => $q->whereNull('verification_status'),
            ])
            ->get();

        if ($sessions->isEmpty()) {
            $this->info("No new pending verifications.");
            return;
        }

        // Recipients are the people who can actually act: verifying requires
        // canVerify(), which is QA-only. Addressing the inspected department's
        // supervisors instead sent Production 67 rows they get 403 on, while QA
        // - the only ones who could clear them - were never told.
        $verifiers = User::with('department')
            ->whereNotNull('email')
            ->where(fn ($q) => $q->where('role', 'supervisor')->orWhere('level', 4))
            ->get()
            ->filter(fn (User $u) => $u->canVerify());

        if ($verifiers->isEmpty()) {
            $this->warn('No QA supervisor can receive the digest - nothing sent.');
            return;
        }

        // QA verifies for every department, so one digest carries them all; the
        // table names the department per row.
        Notification::send($verifiers, new PendingVerificationDigestNotification($sessions));

        foreach ($sessions as $session) {
            $session->update(['notified_at' => Carbon::now()]);
        }

        $this->info("Sent digest with {$sessions->count()} session(s) to {$verifiers->count()} QA supervisor(s).");
    }
}
