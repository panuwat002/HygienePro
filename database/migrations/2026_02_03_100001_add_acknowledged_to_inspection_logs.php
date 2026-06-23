<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Gap 3: Add acknowledged_by/at for Dept Head acknowledgement flow
     */
    public function up(): void
    {
        Schema::table('inspection_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('acknowledged_by')->nullable()->after('verification_comment');
            $table->timestamp('acknowledged_at')->nullable()->after('acknowledged_by');
            
            $table->foreign('acknowledged_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inspection_logs', function (Blueprint $table) {
            $table->dropForeign(['acknowledged_by']);
            $table->dropColumn(['acknowledged_by', 'acknowledged_at']);
        });
    }
};
