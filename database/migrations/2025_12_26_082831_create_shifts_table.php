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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('cascade');
            $table->string('shift_name');
            $table->string('shift_type')->nullable(); // กะเช้า, กะบ่าย, กะดึก
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_dayoff')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('shifts');
        Schema::enableForeignKeyConstraints();
    }
};
