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
     */
    public function handle()
    {
        $overdueActions = \App\Models\CorrectiveAction::with(['log.checkpoint', 'log.session.department', 'assignee'])
            ->where('status', '!=', 'closed')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->get();

        if ($overdueActions->isEmpty()) {
            $this->info('No overdue actions found.');
            return;
        }

        $this->info('Found ' . $overdueActions->count() . ' overdue actions.');

        // Group by Department to send specific reports? OR One Big Report?
        // User said "Notify executives".
        // Let's send one comprehensive report to Admin/Safety Manager for now.
        // And maybe individual notices to Dept Managers later.
        // Send to all Admins. Match on role, not level: nothing in the app ever
        // assigns level 10 (UserController tops out at 9), so the old
        // where('level', 10) matched no one and this mail silently never sent.
        $admins = \App\Models\User::where('role', 'admin')->get();
        
        $mailed = 0;
        foreach ($admins as $admin) {
            if ($admin->email && $admin->wantsEmailFor('email_car_overdue')) {
                \Illuminate\Support\Facades\Mail::to($admin->email)->send(new \App\Mail\CAROverdueReport($overdueActions));
                $mailed++;
            }
        }

        if ($mailed === 0) {
            $this->warn('No admin recipients matched - overdue CAR email was NOT sent to anyone.');
        }

        // Send LINE Notification
        try {
            \Illuminate\Support\Facades\Notification::route(\App\Channels\LineMessagingChannel::class, '')
                ->notify(new \App\Notifications\OverdueCARLineNotification($overdueActions));
            // LineMessagingChannel logs and swallows API errors, so reaching this
            // line means "handed off", not "delivered". Check the log for failures.
            $this->info('LINE notification dispatched (delivery errors, if any, are in the log).');
        } catch (\Exception $e) {
            $this->error('Failed to send LINE notification: ' . $e->getMessage());
        }
        
        $this->info("Overdue CAR notifications dispatched (email recipients: {$mailed}).");
    }
}
