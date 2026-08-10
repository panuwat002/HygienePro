<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SchedulesTemplateExport implements FromArray, WithStyles
{
    public function array(): array
    {
        $startDate = now()->startOfWeek();
        
        $headers1 = ['ส่วนงาน/Support', 'รหัสพนักงาน', 'ชื่อ-สกุล'];
        $headers2 = ['', '', ''];
        $exampleRow1 = ['ผลิต', '680006', 'MISS HTET HTET'];
        $exampleRow2 = ['ผลิต', '680007', 'MISS SANDAR'];
        $exampleRow3 = ['ผลิต', '680008', 'นาย สมมติ ทดสอบ'];

        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $headers1[] = $date->format('d-m-Y');
            $headers2[] = $this->getThaiDay($date->dayOfWeek);
            
            if ($i == 0 || $i == 1) {
                // Example data: multi-line time
                $exampleRow1[] = "19.00\n04.00"; 
                $exampleRow2[] = "08.00\n17.00";
            } elseif ($i == 6) {
                $exampleRow1[] = "WH";
                $exampleRow2[] = "WH";
            } else {
                $exampleRow1[] = "19.00\n04.00";
                $exampleRow2[] = "08.00\n17.00";
            }
            $exampleRow3[] = "OFF";
        }

        return [
            $headers1,
            $headers2,
            $exampleRow1,
            $exampleRow2,
            $exampleRow3,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:J2')->getFont()->setBold(true);
        $sheet->getStyle('A1:J2')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A3:J5')->getAlignment()->setWrapText(true); // enable wrap text for \n
        $sheet->getStyle('D3:J5')->getAlignment()->setHorizontal('center');
        
        return [];
    }

    private function getThaiDay($dayOfWeek)
    {
        $days = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
        return $days[$dayOfWeek] ?? '';
    }
}
