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
            $table->timestamp('verified_at')->nullable()->after('updated_at');
            $table->unsignedBigInteger('verifier_id')->nullable()->after('verified_at');
            // We won't add constraint yet to avoid issues if users table is non-standard, 
            // but usually it is constrained('users'). Let's keep it simple for now or use bigInteger.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inspection_logs', function (Blueprint $table) {
            $table->dropColumn(['verified_at', 'verifier_id']);
        });
    }
};
