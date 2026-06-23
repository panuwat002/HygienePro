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
        Schema::create('inspection_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            
            // Polymorphic relation to Location or Machine
            $table->string('targetable_type');
            $table->unsignedBigInteger('targetable_id');
            $table->index(['targetable_type', 'targetable_id']);

            $table->string('frequency')->default('daily'); // daily, weekly, monthly
            $table->json('days_of_week')->nullable(); // ["Mon", "Tue"] for weekly
            
            $table->time('start_time');
            $table->time('end_time');
            
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_schedules');
    }
};
