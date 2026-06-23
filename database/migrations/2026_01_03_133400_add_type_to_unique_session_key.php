<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

            // Drop the index without 'type'
            $table->dropUnique('session_unique_round_key');

            // Add the index with 'type'
            $table->unique(
                ['inspector_id', 'department_id', 'inspection_date', 'shift', 'round', 'type'], 
                'session_unique_round_key'
            );

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

            $table->unique(
                ['inspector_id', 'department_id', 'inspection_date', 'shift', 'round'], 
                'session_unique_round_key'
            );

            $table->foreign('inspector_id')->references('id')->on('users');
            $table->foreign('department_id')->references('id')->on('departments');
        });
    }
};
