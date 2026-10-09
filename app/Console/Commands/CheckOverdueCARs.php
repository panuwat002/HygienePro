<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckOverdueCARs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'car:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue Corrective Action Requests and notify executives';

    /**
     * Execute the console command.
     *
     * Two different things used to come out of one query. The filter was
     * `status != 'closed'`, so a finding that had been fixed and written up
     * kept being announced every morning as "ค้างแก้ไขเกินกำหนด" with an hour
     * count that climbed for as long as nobody verified it. The department
     * that had done the work on the 7th was still being named on the 9th,
     * while the person actually holding things up - QA, who had not pressed
     * verify - was never mentioned.
     *
     * One word, "overdue", answering two questions with different owners. So
     * the two are separated here: late to fix belongs to the department, late
     * to verify belongs to QA, and a finding fixed on time appears in neither.
     */
    public function handle()
    {
        $overdueActions = \App\Models\CorrectiveAction::with(['log.checkpoint', 'log.session.department', 'assignee'])
            ->whereIn('status', ['open', 'assigned'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->get();

        // Measured from resolved_at rather than due_date: whoever fixed it has
        // finished, and the clock that matters now is how long the check has
        // been waiting. A finding fixed late and verified promptly is not a
        // verification problem.
        $awaitingVerification = \App\Models\CorrectiveAction::with(['log.checkpoint', 'log.session.department', 'resolver'])
            ->where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<', now()->subHours((int) config('inspection.car.verify_reminder_hours', 24)))
            ->get();

        if ($overdueActions->isEmpty() && $awaitingVerification->isEmpty()) {
            $this->info('No overdue actions found.');
            return;
        }

        $this->info('Found ' . $overdueActions->count() . ' overdue actions and '
            . $awaitingVerification->count() . ' awaiting verification.');

        // Group by Department to send specific reports? OR One Big Report?
        // User said "Notify executives".
        // Let's send one comprehensive report to Admin/Safety Manager for now.
        // And maybe individual notices to Dept Managers later.
        // Send to all Admins. Match on role, not level: nothing in the app ever
        // assigns level 10 (UserController tops out at 9), so the old
        // where('level', 10) matched no one and this mail silently never sent.
        $admins = \App\Models\User::where('role', 'admin')->get();

        // Only the genuinely-late-to-fix list goes out by mail. A report titled
        // "Overdue CAR" listing work that was finished on time is the thing
        // being fixed here, not something to re-send under a nicer heading.
        $mailed = 0;
        if ($overdueActions->isNotEmpty()) {
            foreach ($admins as $admin) {
                if ($admin->email && $admin->wantsEmailFor('email_car_overdue')) {
                    \Illuminate\Support\Facades\Mail::to($admin->email)->send(new \App\Mail\CAROverdueReport($overdueActions));
                    $mailed++;
                }
            }

            if ($mailed === 0) {
                $this->warn('No admin recipients matched - overdue CAR email was NOT sent to anyone.');
            }
        }

        // Send LINE Notification
        try {
            \Illuminate\Support\Facades\Notification::route(\App\Channels\LineMessagingChannel::class, '')
                ->notify(new \App\Notifications\OverdueCARLineNotification($overdueActions, $awaitingVerification));
            // LineMessagingChannel logs and swallows API errors, so reaching this
            // line means "handed off", not "delivered". Check the log for failures.
            $this->info('LINE notification dispatched (delivery errors, if any, are in the log).');
        } catch (\Exception $e) {
            $this->error('Failed to send LINE notification: ' . $e->getMessage());
        }
        
        $this->info("Overdue CAR notifications dispatched (email recipients: {$mailed}).");
    }
}
