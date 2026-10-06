<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives an area an owner.
 *
 * Employees have a department; areas and machines never did. So when a finding
 * was raised against a room, nothing in the data said whose room it was, and
 * the corrective action had to be attributed to the only department on the
 * record - the one on the session, which for an area round is whoever walked
 * it:
 *
 *     // For Area/Machine, if no department selected, use User's department
 *     $deptId = Auth::user()->department_id ?? Department::first()?->id;
 *
 * That is always QA. Every area finding therefore landed on QA to fix, so the
 * department that inspects became the department that repairs, QA ended up
 * checking its own work, and the people who actually run the room were never
 * told there was a problem in it.
 *
 * Nullable, and every read falls back to the old behaviour while it is unset,
 * so the rooms can be assigned to departments at whatever pace suits rather
 * than all at once before anything works.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('location_name')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
