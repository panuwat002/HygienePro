<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separates who a finding was given to from who actually dealt with it.
 *
 * resolve() wrote `'assigned_to' => auth()->id()` - "auto-claim by resolver" -
 * so the moment QA fixed something on Production's behalf, the record stopped
 * saying it had ever been Production's. One column holding two facts, with the
 * second write destroying the first; the same shape as approval overwriting
 * the verifier, and the inspection date overwriting the entry date.
 *
 * It matters because QA acting for another department is meant to be the
 * exception made for speed, and nothing could count it. What cannot be counted
 * cannot be noticed turning into the rule.
 *
 * Backfilled from assigned_to for rows already resolved: for those the two are
 * the same value by construction, which is the whole problem, but it is the
 * best that can be said of them and leaves the column usable from here on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corrective_actions', function (Blueprint $table) {
            $table->foreignId('resolved_by')
                ->nullable()
                ->after('assigned_to')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('corrective_actions')
            ->whereIn('status', ['resolved', 'verified', 'closed'])
            ->whereNotNull('assigned_to')
            ->update(['resolved_by' => DB::raw('assigned_to')]);
    }

    public function down(): void
    {
        Schema::table('corrective_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
        });
    }
};
