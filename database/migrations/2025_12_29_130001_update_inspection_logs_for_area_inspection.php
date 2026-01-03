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
        Schema::table('inspection_logs', function (Blueprint $table) {
            // 1. Make employee_id nullable (for Area Inspection)
            $table->foreignId('employee_id')->nullable()->change();
            
            // 2. Add location_id (to know which area was inspected)
            $table->foreignId('location_id')->nullable()->after('session_id')->constrained('locations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inspection_logs', function (Blueprint $table) {
            // Revert changes (Warning: this might fail if there are nulls)
            $table->foreignId('employee_id')->nullable(false)->change();
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
