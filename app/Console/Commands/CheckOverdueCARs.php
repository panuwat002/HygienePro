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
        // For MVP: Send to all Admins (level 10).
        
        $admins = \App\Models\User::where('level', 10)->get();
        
        foreach ($admins as $admin) {
            if ($admin->email && $admin->wantsEmailFor('email_car_overdue')) {
                \Illuminate\Support\Facades\Mail::to($admin->email)->send(new \App\Mail\CAROverdueReport($overdueActions));
            }
        }
        
        $this->info('Notifications sent successfully.');
    }
}
