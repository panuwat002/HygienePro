<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = [
            [
                'shift_name' => 'morning',
                'start_time' => '06:00:00',
                'end_time'   => '12:59:59',
            ],
            [
                'shift_name' => 'afternoon',
                'start_time' => '13:00:00',
                'end_time'   => '18:59:59',
            ],
            [
                'shift_name' => 'night',
                'start_time' => '19:00:00',
                'end_time'   => '05:59:59',
            ],
        ];

        foreach ($shifts as $shift) {
            Shift::updateOrCreate(
                ['shift_name' => $shift['shift_name']],
                $shift
            );
        }
    }
}
