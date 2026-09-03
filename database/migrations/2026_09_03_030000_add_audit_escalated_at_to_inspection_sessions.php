<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The random-audit escalation is a control, not an announcement: it must be able to fire
     * the first time the fail rate crosses the threshold, even when that happens on a
     * "ตรวจต่อ" continuation rather than the first finish. Sharing finished_notified_at with
     * the announcements meant a round that only went over the line on the continuation never
     * escalated at all.
     *
     * Deliberately NOT the existing escalated_at, which EscalatePendingVerifications uses for
     * a different thing entirely (verification pending more than 24h). Sharing that column
     * would let either escalation silently suppress the other.
     */
    public function up(): void
    {
        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->timestamp('audit_escalated_at')->nullable()->after('finished_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->dropColumn('audit_escalated_at');
        });
    }
};
