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
            $table->string('verification_status')->nullable()->after('verifier_id');
            $table->text('verification_comment')->nullable()->after('verification_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inspection_logs', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'verification_comment']);
        });
    }
};
