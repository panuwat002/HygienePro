<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A random audit had no way of saying which round satisfied it, so nothing
 * could ever move it off 'pending' - the schedule was generated weekly and
 * read once on the dashboard, and that was the whole of its life.
 *
 * This is the link: the round a supervisor opens on the audit's department,
 * date and shift claims the audit, and finishing that round closes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->foreignId('random_audit_id')
                ->nullable()
                ->after('is_audit')
                ->constrained('random_audits')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('random_audit_id');
        });
    }
};
