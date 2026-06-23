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
            // 1. Drop FKs that might depend on the index
            // Note: In some MySQL configs, dropping an index used by a FK is forbidden.
            $table->dropForeign(['inspector_id']);
            $table->dropForeign(['department_id']);

            // 2. Drop the old unique index
            $table->dropUnique('session_unique_key');

            // 3. Add new unique index with 'round'
            $table->unique(
                ['inspector_id', 'department_id', 'inspection_date', 'shift', 'round'], 
                'session_unique_round_key'
            );

            // 4. Restore FKs
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
                ['inspector_id', 'department_id', 'inspection_date', 'shift'], 
                'session_unique_key'
            );

            $table->foreign('inspector_id')->references('id')->on('users');
            $table->foreign('department_id')->references('id')->on('departments');
        });
    }
};
