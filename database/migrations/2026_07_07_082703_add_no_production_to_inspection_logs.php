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
        // Modify the enum to include 'no_production'.
        // Raw ENUM ALTER is MySQL-only; skip on other drivers (e.g. sqlite in tests),
        // where `result` is a plain string column and accepts the new value already.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inspection_logs MODIFY COLUMN result ENUM('pass', 'fail', 'absent', 'no_production') DEFAULT 'pass'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inspection_logs', function (Blueprint $table) {
            //
        });
    }
};
