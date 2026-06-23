<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('inspection_sessions')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('checkpoint_id')->constrained();
            $table->enum('result', ['pass', 'fail', 'absent'])->default('pass');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('correction_action')->nullable();
            $table->timestamp('inspected_at')->useCurrent();
            
            $table->string('checkpoint_title_snapshot')->nullable();
            $table->string('dept_snapshot')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_logs');
    }
};
