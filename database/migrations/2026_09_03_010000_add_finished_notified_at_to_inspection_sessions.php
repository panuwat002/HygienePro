<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A session finished today can now be reopened with "ตรวจต่อ" and finished again.
     * Without a marker, every re-finish would resend the supervisor bell, the manager CAR
     * summary and the LINE summary — so record when those first went out.
     */
    public function up(): void
    {
        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->timestamp('finished_notified_at')->nullable()->after('locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->dropColumn('finished_notified_at');
        });
    }
};
