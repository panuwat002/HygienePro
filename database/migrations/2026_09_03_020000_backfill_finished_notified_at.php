<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rounds finished before finished_notified_at existed already sent their supervisor
     * bell, manager CAR summary and LINE summary. Left NULL, reopening one with "ตรวจต่อ"
     * and finishing again would send all three a second time.
     *
     * updated_at is the closest record of when the round was closed.
     */
    public function up(): void
    {
        DB::table('inspection_sessions')
            ->where('status', 'completed')
            ->whereNull('finished_notified_at')
            ->update(['finished_notified_at' => DB::raw('updated_at')]);
    }

    /**
     * Not reversible: which rows were blank before the backfill isn't recorded, and clearing
     * the column would reintroduce the duplicate notifications.
     */
    public function down(): void
    {
        //
    }
};
