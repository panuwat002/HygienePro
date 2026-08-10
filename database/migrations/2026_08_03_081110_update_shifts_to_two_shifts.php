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
        // Update Morning Shift
        \Illuminate\Support\Facades\DB::table('shifts')->whereIn('shift_name', ['morning', 'กะเช้า'])->update([
            'start_time' => '06:00:00',
            'end_time' => '18:59:00',
        ]);

        // Update Night Shift
        \Illuminate\Support\Facades\DB::table('shifts')->whereIn('shift_name', ['night', 'กะดึก'])->update([
            'start_time' => '19:00:00',
            'end_time' => '05:59:00',
        ]);

        // Find the Afternoon Shift
        $afternoonShift = \Illuminate\Support\Facades\DB::table('shifts')->whereIn('shift_name', ['afternoon', 'กะบ่าย'])->first();

        if ($afternoonShift) {
            // Find morning shift to reassign employees
            $morningShift = \Illuminate\Support\Facades\DB::table('shifts')->whereIn('shift_name', ['morning', 'กะเช้า'])->first();
            
            if ($morningShift) {
                // Migrate employees on afternoon shift to morning shift
                \Illuminate\Support\Facades\DB::table('employees')->where('shift_id', $afternoonShift->id)->update([
                    'shift_id' => $morningShift->id
                ]);
            }
            
            // Delete Afternoon Shift
            \Illuminate\Support\Facades\DB::table('shifts')->where('id', $afternoonShift->id)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-insert afternoon shift
        \Illuminate\Support\Facades\DB::table('shifts')->insert([
            'shift_name' => 'กะบ่าย',
            'start_time' => '13:00:00',
            'end_time' => '18:59:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Reset morning shift back to original
        \Illuminate\Support\Facades\DB::table('shifts')->whereIn('shift_name', ['morning', 'กะเช้า'])->update([
            'start_time' => '06:00:00',
            'end_time' => '12:59:00',
        ]);
    }
};
