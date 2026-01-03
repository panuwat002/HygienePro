<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_logs', function (Blueprint $table) {
            $table->foreignId('machine_id')->nullable()->constrained()->after('location_id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inspection_logs', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);
            $table->dropColumn('machine_id');
        });
    }
};
