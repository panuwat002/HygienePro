<?php

namespace App\Exports;

use App\Models\Location;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LocationsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Location::withCount('checkpoints')->get();
    }

    public function headings(): array
    {
        return [
            'ชื่อจุดประจำการ',
            'คำอธิบาย',
        ];
    }

    public function map($location): array
    {
        return [
            $location->location_name,
            $location->description ?? '',
        ];
    }
}
