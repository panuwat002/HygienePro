<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspector_id')->constrained('users');
            $table->foreignId('department_id')->constrained();
            $table->date('inspection_date');
            $table->enum('shift', ['morning', 'afternoon', 'night']);
            $table->string('status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            
            $table->unique(['inspector_id', 'department_id', 'inspection_date', 'shift'], 'session_unique_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_sessions');
    }
};
