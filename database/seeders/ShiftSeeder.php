<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Shift::truncate();
        Schema::enableForeignKeyConstraints();

        $shifts = [];
        
        // Start from 03:00 to 23:30
        $start = Carbon::createFromTime(3, 0, 0);
        $end = Carbon::createFromTime(23, 30, 0);

        while ($start <= $end) {
            $timeString = $start->format('H:i');
            $endTime = (clone $start)->addHours(9);
            $endTimeString = $endTime->format('H:i');

            $hour = $start->hour;
            $minute = $start->minute;
            $type = 'กะดึก'; 
            
            $decimalTime = $hour + ($minute / 60);

            if ($decimalTime >= 6 && $decimalTime <= 12) {
                $type = 'กะเช้า';
            } elseif ($decimalTime >= 12.5 && $decimalTime <= 18) {
                $type = 'กะบ่าย';
            } else {
                $type = 'กะดึก';
            }

            // Using dot format for name as requested in the image (e.g. 06.00-15.00)
            $nameTimeStart = $start->format('H.i');
            $nameTimeEnd = $endTime->format('H.i');
            $name = "{$type} {$nameTimeStart}-{$nameTimeEnd}";

            $shifts[] = [
                'shift_name' => $name,
                'shift_type' => $type,
                'start_time' => $timeString . ':00',
                'end_time'   => $endTime->format('H:i:s'),
                'is_dayoff'  => false,
            ];

            $start->addMinutes(30);
        }

        // Add 24:00 (00:00)
        $shifts[] = [
            'shift_name' => 'กะดึก 24.00-09.00',
            'shift_type' => 'กะดึก',
            'start_time' => '00:00:00',
            'end_time'   => '09:00:00',
            'is_dayoff'  => false,
        ];

        // Add Off Days
        $shifts[] = [
            'shift_name' => 'วันหยุดประจำสัปดาห์',
            'shift_type' => 'วันหยุด',
            'start_time' => null,
            'end_time'   => null,
            'is_dayoff'  => true,
        ];
        $shifts[] = [
            'shift_name' => 'วันหยุดประจำปี',
            'shift_type' => 'วันหยุด',
            'start_time' => null,
            'end_time'   => null,
            'is_dayoff'  => true,
        ];

        foreach ($shifts as $shift) {
            Shift::create($shift);
        }
    }
}
