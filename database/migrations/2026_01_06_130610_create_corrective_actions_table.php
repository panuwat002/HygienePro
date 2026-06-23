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
        Schema::create('corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_log_id')->constrained()->onDelete('cascade');
            
            // Status Tracking
            $table->string('status')->default('open'); // open, assigned, resolved, closed
            
            // Actors
            $table->foreignId('escalated_by')->constrained('users'); // QA Manager
            $table->foreignId('assigned_to')->nullable()->constrained('users'); // PD Supervisor (Responsible)

            // Remediation Details
            $table->text('root_cause')->nullable();
            $table->text('action_taken')->nullable();
            $table->string('proof_image')->nullable();
            
            // Timestamps for Lifecycle
            $table->timestamp('escalated_at')->useCurrent();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('due_date')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corrective_actions');
    }
};
