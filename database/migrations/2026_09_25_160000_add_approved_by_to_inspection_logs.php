<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approval had nowhere of its own to be recorded, so managerApprove() wrote
 * the manager into verifier_id - the column that says which QA supervisor
 * carried out the verification.
 *
 * Three things went wrong the moment a manager pressed อนุมัติ:
 *
 *   1. the printed form's ผู้ทวนสอบ (QA Supervisor) box took the manager's
 *      name and signature, because ReportController fills it from verifier_id;
 *   2. the supervisor who actually verified stopped being recorded against
 *      the work at all;
 *   3. the 'self_verified' flag - there to show an FM-QA-22 auditor which
 *      rounds carried only one person's signature - went false for every
 *      approved round, because it compares verifier_id with inspector_id.
 *
 * Approval gets its own two columns. verifier_id goes back to meaning only
 * what it says.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_logs', function (Blueprint $table) {
            $table->foreignId('approved_by')
                ->nullable()
                ->after('verifier_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        // Rows already approved: verifier_id holds the approver, because that
        // is what the old code wrote there. Copy it across so the new columns
        // describe existing history too.
        //
        // What cannot be recovered is who verified those rows BEFORE the
        // manager overwrote it - that value was destroyed in place, and forms
        // reprinted for rounds approved before this migration will still show
        // the manager in the verifier box.
        DB::table('inspection_logs')
            ->where('verification_status', 'approved')
            ->whereNotNull('verifier_id')
            ->update([
                'approved_by' => DB::raw('verifier_id'),
                'approved_at' => DB::raw('verified_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('inspection_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
    }
};
