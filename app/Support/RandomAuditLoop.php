<?php

namespace App\Support;

use App\Models\InspectionSession;
use App\Models\RandomAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Closes the loop on the weekly random-audit schedule.
 *
 * `audit:generate` has drawn a department, a date and a shift every week since
 * July, and nothing in the codebase has ever written `random_audits.status`
 * again afterwards - so `auditor_id`, `started_at` and `completed_at` were
 * never filled in, `isMissed()` was never called, and the dashboard card kept
 * showing audits from weeks gone by because 'pending' was the only state an
 * audit could ever reach.
 *
 * The three moments that state actually changes live here, so the page, the
 * dashboard card and the scheduled command cannot disagree about them.
 */
class RandomAuditLoop
{
    /**
     * Only a QA supervisor and above can satisfy an audit. The audit is a
     * cross-check ON the ordinary rounds, so an ordinary round by the usual
     * inspector must not be able to tick it off - that would make the schedule
     * close itself, which is the thing it exists to prevent.
     */
    public const MIN_LEVEL = 4;

    /**
     * Link the round to the audit it satisfies, if today's schedule drew this
     * department for this shift. Returns the audit claimed, or null.
     */
    public function claim(InspectionSession $session, User $user): ?RandomAudit
    {
        if ($session->random_audit_id !== null) {
            return $session->randomAudit;
        }

        if ($user->level < self::MIN_LEVEL && ! $user->isAdmin()) {
            return null;
        }

        return DB::transaction(function () use ($session, $user) {
            $candidates = RandomAudit::open()
                ->where('department_id', $session->department_id)
                ->whereDate('audit_date', $session->inspection_date)
                ->lockForUpdate()
                ->get();

            $audit = $candidates->first(fn (RandomAudit $a) => $this->isClaimableBy($session, $a));

            if (! $audit) {
                return null;
            }

            // Taking an audit over from an abandoned round: that round is no
            // longer the evidence for it, so it must stop pointing at it - a
            // audit has one round, or hasOne() starts picking arbitrarily.
            InspectionSession::where('random_audit_id', $audit->id)
                ->whereKeyNot($session->getKey())
                ->update(['random_audit_id' => null]);

            $audit->update([
                'status' => RandomAudit::IN_PROGRESS,
                'auditor_id' => $user->id,
                // Kept from the first attempt: when the department was first
                // visited is the fact, not when somebody restarted the round.
                'started_at' => $audit->started_at ?? now(),
            ]);

            $session->update([
                'random_audit_id' => $audit->id,
                'is_audit' => true,
            ]);

            return $audit;
        });
    }

    /**
     * Mark the audit carried out. Called when the round that claimed it is
     * finished; a reopened round finishing a second time leaves the original
     * completion time alone.
     */
    public function complete(InspectionSession $session): ?RandomAudit
    {
        $audit = $session->randomAudit;

        if (! $audit || $audit->status === RandomAudit::COMPLETED) {
            return $audit;
        }

        $audit->update([
            'status' => RandomAudit::COMPLETED,
            'completed_at' => now(),
            'auditor_id' => $audit->auditor_id ?? $session->inspector_id,
        ]);

        return $audit;
    }

    /**
     * Retire audits whose day has passed with nobody doing them. Without this
     * an audit nobody carried out stays 'pending' forever and the dashboard
     * card grows by one department every week.
     *
     * Returns how many were retired.
     */
    public function closeMissed(): int
    {
        return RandomAudit::open()
            ->whereDate('audit_date', '<', RandomAudit::currentBusinessDate())
            ->update(['status' => RandomAudit::MISSED]);
    }

    /**
     * An audit belongs to one round at a time.
     *
     * A second round opened the same day must not steal an audit somebody is
     * part-way through - but it must be able to pick up one whose round was
     * abandoned ("เริ่มรอบใหม่" closes the old round without finishing it),
     * or that audit would sit at 'in_progress' until it was recorded missed.
     */
    private function isClaimableBy(InspectionSession $session, RandomAudit $audit): bool
    {
        if (! $this->coversShift($session, $audit)) {
            return false;
        }

        if ($audit->status === RandomAudit::PENDING) {
            return true;
        }

        return ! $audit->session()
            ->whereIn('status', ['in_progress', 'paused'])
            ->exists();
    }

    /**
     * Does this round cover the shift the audit asked for?
     *
     * A personnel round names shifts by key ('custom_11,custom_12'); an area or
     * machine round names one of 'morning'/'afternoon'/'night'. The audit only
     * ever says 'morning' or 'afternoon', so the two are compared on the
     * canonical shift_type both sides can be reduced to - with the raw keys
     * checked first, which is what still matches when the shifts table holds
     * no row of that type at all.
     */
    private function coversShift(InspectionSession $session, RandomAudit $audit): bool
    {
        $tokens = array_filter(array_map('trim', explode(',', (string) $session->shift)));

        if (in_array($audit->shift, $tokens, true)) {
            return true;
        }

        $types = collect($session->getResolvedShifts()['shifts'])
            ->pluck('shift_type')
            ->filter()
            ->all();

        return in_array($audit->shiftType(), $types, true);
    }
}
