<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records who checked a fix, and when.
 *
 * A finding's record already says who raised it, who it was given to and who
 * did the work. The one signature missing was the one an auditor asks for
 * first: who went and looked afterwards. 'verified' was a status with nobody
 * attached to it.
 *
 * Left null for rows verified before this existed rather than filled in from
 * assigned_to or resolved_by. Those are different people doing a different
 * job, and guessing would put a name against a check that person may never
 * have made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corrective_actions', function (Blueprint $table) {
            $table->foreignId('verified_by')
                ->nullable()
                ->after('resolved_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('verified_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('corrective_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn('verified_at');
        });
    }
};
