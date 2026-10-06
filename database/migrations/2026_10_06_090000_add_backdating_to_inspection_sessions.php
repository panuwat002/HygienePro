<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a round say which day's work it covers, separately from the day it was
 * typed in.
 *
 * The two were the same value. inspection_date came straight off the clock
 * (InspectionService::startSession) and so did every log's inspected_at, so a
 * round that could not be entered on the day it belonged to had nowhere to go:
 * QA received the shift roster from Production a day late, could not pick a
 * shift without it, and 5 Oct 2026 was left with no record at all.
 *
 * A system of record has to hold both facts. inspection_date is now the day
 * the work covers; created_at, which was always there, is the day it was
 * entered. Where they differ, backdated_reason says why and backdated_by says
 * who authorised it - and the printed form prints all of it, because a
 * backdated record that announces itself is evidence and a silent one is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->text('backdated_reason')->nullable()->after('inspection_date');

            $table->foreignId('backdated_by')
                ->nullable()
                ->after('backdated_reason')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('backdated_by');
            $table->dropColumn('backdated_reason');
        });
    }
};
