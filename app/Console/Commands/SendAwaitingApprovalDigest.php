<?php

namespace App\Console\Commands;

use App\Models\InspectionSession;
use App\Models\Location;
use App\Models\User;
use App\Notifications\AwaitingApprovalDigestNotification;
use App\Support\VerificationGroups;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Tell QA managers what is waiting on their signature.
 *
 * Every other scheduled notification in this system is about verification and
 * addresses QA supervisors. Approval - the step that closes a round and locks
 * the session - had none, which is how 17,993 logs came to sit at 'verified'
 * against 4,174 ever approved. The work was never refused; nobody was told it
 * existed.
 */
class SendAwaitingApprovalDigest extends Command
{
    protected $signature = 'inspection:send-approval-digest';

    protected $description = 'Send QA managers a digest of rounds waiting on their approval';

    /**
     * How many rounds the email lists before it just gives the remainder as a
     * number. A manager scans this to decide whether to open the page, not to
     * work from it.
     */
    private const MAX_ROWS = 10;

    public function handle(VerificationGroups $groups): int
    {
        // Counted exactly the way the dashboard card and the awaiting_approval
        // tab count, so the number a manager is told is the number they land on.
        $awaiting = $groups->awaitingApproval();

        if ($awaiting->isEmpty()) {
            $this->info('Nothing is waiting on a manager.');

            return self::SUCCESS;
        }

        // Every approver is QA or an admin, and both have global visibility, so
        // one digest covers the whole plant; the table names the department per
        // row. If approval ever gains a department-scoped role, this has to
        // become per-recipient.
        $approvers = User::with('department')
            ->where(fn ($q) => $q->whereIn('role', ['manager', 'admin'])->orWhere('level', '>=', 5))
            ->get()
            ->filter(fn (User $user) => $user->can('approve'));

        if ($approvers->isEmpty()) {
            $this->warn('No one can approve - nothing sent.');

            return self::SUCCESS;
        }

        $personCount = $awaiting->where('is_person', true)->count();

        Notification::send($approvers, new AwaitingApprovalDigestNotification(
            total: $awaiting->count(),
            personCount: $personCount,
            areaCount: $awaiting->count() - $personCount,
            rows: $this->rows($awaiting),
        ));

        $this->info("Told {$approvers->count()} approver(s) about {$awaiting->count()} round(s).");

        return self::SUCCESS;
    }

    /**
     * The oldest rounds first - those are the ones rotting, and the ones a
     * manager most needs to see before deciding whether to open the page.
     */
    private function rows($awaiting): array
    {
        $oldest = $awaiting->sortBy('last_inspected_at')->take(self::MAX_ROWS)->values();

        $sessions = InspectionSession::with(['department', 'inspector'])
            ->whereIn('id', $oldest->pluck('session_id')->unique())
            ->get()
            ->keyBy('id');

        $locations = Location::whereIn('id', $oldest->pluck('loc_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $today = Carbon::now()->startOfDay();

        return $oldest->map(function ($group) use ($sessions, $locations, $today) {
            $session = $sessions->get($group->session_id);
            $inspected = Carbon::parse($group->last_inspected_at);

            return [
                'session_id' => $group->session_id,
                'kind' => $group->is_person ? 'person' : 'area',
                'label' => $group->is_person
                    ? 'ตรวจพนักงาน'
                    : ($locations->get($group->loc_id)?->location_name ?? 'ไม่ระบุพื้นที่'),
                // shift_label, not the raw shift column: that column holds keys
                // like "custom_11", which mean nothing to the person reading.
                'shift' => $session?->shift_label ?? '-',
                'department' => $session?->department?->dept_name ?? '-',
                'inspector' => $session?->inspector?->name ?? '-',
                // Whole days on the calendar, which is how someone reads "ค้างมา
                // 6 วัน" - not 5 because it is an hour short of six times 24.
                'waiting_days' => (int) $inspected->copy()->startOfDay()->diffInDays($today),
                'inspected_at' => $inspected->format('d/m/Y H:i'),
            ];
        })->all();
    }
}
