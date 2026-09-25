<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A work area that is inspected on its own but still belongs to a parent.
     *
     * ห้องแคะ has no air shower, so its staff carry nine checkpoints where the
     * rest of Production carries ten, and QA wants it inspected and reported
     * separately. A personnel round targets department + shift, so separating
     * it means making it a department - and a department with no parent loses
     * its head, because acknowledging a finding requires being in the same
     * department and a user belongs to exactly one.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('parent_department_id')
                ->nullable()
                ->after('dept_code')
                ->constrained('departments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['parent_department_id']);
            $table->dropColumn('parent_department_id');
        });
    }
};
