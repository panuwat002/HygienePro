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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('prefix')->nullable()->after('id');
            $table->string('fname')->nullable()->after('prefix');
            $table->string('lname')->nullable()->after('fname');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['prefix', 'fname', 'lname']);
        });
    }
};
