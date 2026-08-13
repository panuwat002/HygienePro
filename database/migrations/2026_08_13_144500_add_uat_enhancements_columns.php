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
        if (Schema::hasTable('checkpoints') && !Schema::hasColumn('checkpoints', 'default_cost_impact')) {
            Schema::table('checkpoints', function (Blueprint $table) {
                $table->decimal('default_cost_impact', 10, 2)->default(0)->nullable()->after('type');
            });
        }

        if (Schema::hasTable('corrective_actions') && !Schema::hasColumn('corrective_actions', 'financial_loss')) {
            Schema::table('corrective_actions', function (Blueprint $table) {
                $table->decimal('financial_loss', 10, 2)->default(0)->nullable()->after('status');
            });
        }

        if (Schema::hasTable('inspection_sessions')) {
            Schema::table('inspection_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('inspection_sessions', 'is_sampling')) {
                    $table->boolean('is_sampling')->default(false)->after('is_audit');
                }
                if (!Schema::hasColumn('inspection_sessions', 'sample_size')) {
                    $table->integer('sample_size')->nullable()->after('is_sampling');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('checkpoints') && Schema::hasColumn('checkpoints', 'default_cost_impact')) {
            Schema::table('checkpoints', function (Blueprint $table) {
                $table->dropColumn('default_cost_impact');
            });
        }

        if (Schema::hasTable('corrective_actions') && Schema::hasColumn('corrective_actions', 'financial_loss')) {
            Schema::table('corrective_actions', function (Blueprint $table) {
                $table->dropColumn('financial_loss');
            });
        }

        if (Schema::hasTable('inspection_sessions')) {
            Schema::table('inspection_sessions', function (Blueprint $table) {
                if (Schema::hasColumn('inspection_sessions', 'is_sampling')) {
                    $table->dropColumn('is_sampling');
                }
                if (Schema::hasColumn('inspection_sessions', 'sample_size')) {
                    $table->dropColumn('sample_size');
                }
            });
        }
    }
};
