<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('random_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('audit_date');
            $table->string('shift'); // morning, afternoon
            $table->string('status')->default('pending'); // pending, completed, missed
            $table->foreignId('auditor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('week_number'); // ISO week number for tracking
            $table->integer('year');
            $table->integer('sample_size')->default(10); // How many employees to sample
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'audit_date', 'shift'], 'unique_dept_date_shift');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('random_audits');
    }
};
