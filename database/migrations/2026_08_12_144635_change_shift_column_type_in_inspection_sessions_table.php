<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inspection_sessions', function (Blueprint $table) {
            // Drop dependencies if any (FKs)
            $table->dropForeign(['inspector_id']);
            $table->dropForeign(['department_id']);

            $table->dropUnique('session_unique_round_key');
        });

        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->string('shift', 255)->change();
        });

        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->unique(['inspector_id', 'department_id', 'inspection_date', 'shift', 'round', 'type'], 'session_unique_round_key');
            
            // Restore FKs
            $table->foreign('inspector_id')->references('id')->on('users');
            $table->foreign('department_id')->references('id')->on('departments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->dropForeign(['inspector_id']);
            $table->dropForeign(['department_id']);
            
            $table->dropUnique('session_unique_round_key');
        });

        DB::statement("ALTER TABLE inspection_sessions MODIFY shift ENUM('morning', 'afternoon', 'night') NOT NULL");

        Schema::table('inspection_sessions', function (Blueprint $table) {
            $table->unique(['inspector_id', 'department_id', 'inspection_date', 'shift', 'round', 'type'], 'session_unique_round_key');
            
            $table->foreign('inspector_id')->references('id')->on('users');
            $table->foreign('department_id')->references('id')->on('departments');
        });
    }
};
