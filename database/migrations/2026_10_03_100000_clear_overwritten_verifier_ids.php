<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Takes the approver's name back out of the verifier's column.
 *
 * Before 238e48b, managerApprove() wrote the manager's id into
 * inspection_logs.verifier_id - the column that says which QA supervisor
 * carried out the verification. On UAT that reached 25,007 rows across 101
 * rounds between 13 Aug and 25 Sep 2026, and every form reprinted for one of
 * them puts the manager's name in the ผู้ทวนสอบ (QA Supervisor) box.
 *
 * Who actually verified cannot be recovered from this column: it was
 * overwritten in place. But it does not have to be, because
 * ReportController fills that box from two sources -
 *
 *     $sessions->flatMap(fn($s) => $s->logs->pluck('verifier_id'))
 *         ->concat($sessions->pluck('verified_by'))
 *
 * - and approval never touched inspection_sessions.verified_by. Clearing the
 * log column alone therefore does two things at once: the 23 of 101 rounds
 * that still carry a session-level verifier print the real supervisor again,
 * and the other 78 print nothing, which is the truth. No value is guessed.
 *
 * Nothing is lost either: what sits in verifier_id on these rows is the
 * manager's id, and the migration before this one already copied it into
 * approved_by.
 *
 * verified_at is deliberately left alone. It was overwritten too, but
 * VerificationGroups treats a null verified_at as "nobody has verified this",
 * so clearing it would march all 25,007 rows back into the verification inbox
 * as outstanding work.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cleared = $this->overwrittenRows()->update(['verifier_id' => null]);

        if ($cleared > 0) {
            echo "  Cleared the approver out of verifier_id on {$cleared} log(s).\n";
        }
    }

    public function down(): void
    {
        // The value is still in approved_by, so this is exact rather than a
        // reconstruction - it puts back the same wrong name that was there.
        DB::table('inspection_logs')
            ->where('verification_status', 'approved')
            ->whereNull('verifier_id')
            ->whereNotNull('approved_by')
            ->whereNotNull('verified_at')
            ->whereColumn('approved_at', 'verified_at')
            ->update(['verifier_id' => DB::raw('approved_by')]);
    }

    /**
     * The fingerprint of an overwritten row.
     *
     * add_approved_by_to_inspection_logs backfilled approved_by and
     * approved_at straight from verifier_id and verified_at, so on a row
     * written before the fix both pairs are identical. A round approved since
     * then has two different people in those columns, and two different times,
     * and must not be touched.
     */
    private function overwrittenRows()
    {
        return DB::table('inspection_logs')
            ->where('verification_status', 'approved')
            ->whereNotNull('verifier_id')
            ->whereNotNull('approved_by')
            ->whereColumn('approved_by', 'verifier_id')
            ->whereColumn('approved_at', 'verified_at');
    }
};
