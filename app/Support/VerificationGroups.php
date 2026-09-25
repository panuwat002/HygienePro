<?php

namespace App\Support;

use App\Models\InspectionLog;
use Illuminate\Support\Collection;

/**
 * Which rounds exist on /verification, and what state each one is in.
 *
 * A "group" is one card on that page: a personnel round is one group per
 * session, an area round one group per location. The page, its detail
 * endpoint, the dashboard cards and the approval digest all have to agree
 * about how many groups are waiting and which ones - a card that says 225 and
 * a page that shows 50 is a bug, so they share this one implementation rather
 * than each counting for themselves.
 */
class VerificationGroups
{
    /**
     * How long finished work stays in the inbox after somebody signs it off.
     *
     * Long enough that a manager who approves a batch sees it land, and that
     * "what did I close this week" is answerable without picking dates. Short
     * enough that the page stays bounded - an unbounded clause on this table is
     * exactly what once made it load 17,993 rows to render twenty.
     */
    public const RECENTLY_CLOSED_DAYS = 7;

    /**
     * The rows /verification works from, for a given date window and scope.
     *
     * The page summarises these in SQL and then loads only the groups it shows;
     * the detail endpoint reuses the same predicate for one group, so the two
     * can never disagree about which logs belong to a card. Columns are
     * qualified because the summary joins machines, which has a location_id too.
     */
    public function logQuery(?string $startDate, ?string $endDate, ?int $scopeDeptId)
    {
        $query = InspectionLog::query()
            ->where(function ($q) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    // An explicitly picked range applies to every status, so the
                    // tab badges still add up within that window.
                    $q->whereBetween('inspection_logs.inspected_at', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59',
                    ]);
                } else {
                    // Inbox Mode: everything still waiting on somebody, plus
                    // today's finished work for context.
                    $q->whereNull('inspection_logs.verified_at')
                      ->orWhereIn('inspection_logs.verification_status', ['reclean', 'verified']);

                    $q->orWhere(function ($subQ) {
                        $subQ->whereIn('inspection_logs.verification_status', ['approved', 'auto_verified'])
                             ->where(function ($when) {
                                 // Inspected today: the round that was walked,
                                 // verified and signed inside one shift.
                                 $when->whereDate('inspection_logs.inspected_at', date('Y-m-d'))
                                     // Or signed off recently, whenever it was
                                     // inspected. Without this an approval on old
                                     // work matched no clause at all: not awaiting
                                     // approval any more, and not inspected today -
                                     // so a manager who approved 225 six-week-old
                                     // rounds watched every tab go to zero with no
                                     // evidence anything had happened.
                                     //
                                     // Coalesced because approval no longer
                                     // restamps verified_at - it has its own
                                     // column now. An auto-verified log never
                                     // had an approval, so it falls back to
                                     // when it was verified.
                                     ->orWhereRaw(
                                         'coalesce(inspection_logs.approved_at, inspection_logs.verified_at) >= ?',
                                         [now()->subDays(self::RECENTLY_CLOSED_DAYS)->startOfDay()]
                                     );
                             });
                    });
                }
            })
            ->where(function ($q) {
                $q->whereNull('inspection_logs.location_id')
                  ->orWhereDoesntHave('location', function ($sub) {
                      $sub->doesntHave('checkpoints')
                          ->doesntHave('machines');
                  });
            });

        if ($scopeDeptId !== null) {
            $query->where(function ($q) use ($scopeDeptId) {
                $q->whereHas('employee', fn ($subQ) => $subQ->where('department_id', $scopeDeptId))
                  ->orWhere(function ($q2) use ($scopeDeptId) {
                      $q2->whereNull('inspection_logs.employee_id')
                         ->whereHas('session', fn ($sq) => $sq->where('department_id', $scopeDeptId));
                  });
            });
        }

        return $query;
    }

    /**
     * One narrow row per group, with every count a card shows.
     *
     * The page renders 20 groups but used to hydrate every log the predicate
     * matched to work out which 20 those were - 17,993 rows on the UAT database,
     * growing with every round ever verified. Counting here instead means the
     * tab, the badges and the page slice are all settled before a log is read.
     */
    public function summarise($query, bool $withCardDetail = false): Collection
    {
        // Only the twenty groups on screen need the card columns. Counting them
        // for all 215 - two count(distinct) among them - put ~110ms on every
        // page, including the tabs that show nothing at all.
        $cardDetail = $withCardDetail ? [
            "sum(case when inspection_logs.verification_status = 'verified' then 1 else 0 end) as n_verified",
            'count(distinct inspection_logs.employee_id) as n_employees',
            'count(distinct inspection_logs.machine_id) as n_machines',
            'sum(case when inspection_logs.machine_id is null then 1 else 0 end) as n_without_machine',
            "sum(case when inspection_logs.result = 'pass' then 1 else 0 end) as n_pass",
            "sum(case when inspection_logs.result = 'fail' then 1 else 0 end) as n_fail",
            "sum(case when inspection_logs.result = 'no_production' then 1 else 0 end) as n_no_production",
            "sum(case when inspection_logs.result = 'absent' then 1 else 0 end) as n_absent",
            // A failure nobody has signed off yet - what makes a group read as failed.
            "sum(case when inspection_logs.result = 'fail' and (inspection_logs.verification_status is null"
                . " or inspection_logs.verification_status not in ('approved', 'auto_verified'))"
                . ' then 1 else 0 end) as n_outstanding_fail',
            'sum(case when inspection_logs.acknowledged_at is null then 1 else 0 end) as n_unacknowledged',
        ] : [];

        return $query
            ->leftJoin('machines', function ($join) {
                // Matches the model side, which resolves a soft-deleted machine
                // to null and lands the group under 'unknown'.
                $join->on('machines.id', '=', 'inspection_logs.machine_id')
                     ->whereNull('machines.deleted_at');
            })
            ->selectRaw(implode(', ', array_merge([
                'inspection_logs.session_id as session_id',
                'case when inspection_logs.employee_id is null then 0 else 1 end as is_person',
                // A personnel round is one group per session; an area round is one
                // group per location, and a machine counts as its location.
                'case when inspection_logs.employee_id is not null then null'
                    . ' else coalesce(inspection_logs.location_id, machines.location_id) end as loc_id',
                'count(*) as n_total',
                'sum(case when inspection_logs.verified_at is null then 1 else 0 end) as n_unverified',
                "sum(case when inspection_logs.verification_status = 'rejected' then 1 else 0 end) as n_rejected",
                "sum(case when inspection_logs.verification_status = 'reclean' then 1 else 0 end) as n_reclean",
                "sum(case when inspection_logs.verification_status = 'approved' then 1 else 0 end) as n_approved",
                "sum(case when inspection_logs.verification_status = 'auto_verified' then 1 else 0 end) as n_auto",
                'max(inspection_logs.inspected_at) as last_inspected_at',
            ], $cardDetail)))
            ->groupBy('session_id', 'is_person', 'loc_id')
            ->orderByDesc('last_inspected_at')
            ->toBase()
            ->get()
            ->map(function ($row) {
                $row->is_person = (bool) $row->is_person;
                $row->group_key = $row->is_person
                    ? $row->session_id . '_personnel'
                    : $row->session_id . '_loc_' . ($row->loc_id ?? 'unknown');
                $row->group_status = $this->statusFromCounts($row);

                return $row;
            });
    }

    /**
     * Every group sitting on a manager's desk: QA has verified all of it and
     * nobody has approved it.
     *
     * This is the number the dashboard card shows and the awaiting_approval
     * tab lists, so the digest quoting it cannot drift from either.
     */
    public function awaitingApproval(?int $scopeDeptId = null): Collection
    {
        return $this->summarise($this->logQuery(null, null, $scopeDeptId))
            ->where('group_status', 'verified')
            ->values();
    }

    /**
     * The tab a group belongs in, worked out from SQL counters instead of from
     * its loaded logs — so /verification can decide which 20 groups to show
     * before loading a single one of them.
     *
     * Mirrors the ladder the group builder applies: a group made only of
     * approved and auto-verified logs counts as approved, and anything
     * unverified or rejected outranks a re-clean.
     */
    public function statusFromCounts(object $row): string
    {
        $total = (int) $row->n_total;
        $approved = (int) $row->n_approved;
        $autoVerified = (int) $row->n_auto;

        $isAutoVerified = $autoVerified === $total;
        $isApproved = ($approved + $autoVerified) === $total && ! $isAutoVerified;

        if ($isApproved) {
            return 'approved';
        }

        if ($isAutoVerified) {
            return 'auto_verified';
        }

        if ((int) $row->n_rejected > 0 || (int) $row->n_unverified > 0) {
            return 'pending';
        }

        if ((int) $row->n_reclean > 0) {
            return 'reclean';
        }

        return 'verified';
    }
}
