<?php

namespace App\Exports;

use App\Models\Machine;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MachinesExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Machine::with('location')->get();
    }

    public function headings(): array
    {
        return [
            'ID (ห้ามแก้ไข)',
            'ชื่อเครื่องจักร / พื้นที่ย่อย',
            'ที่ตั้ง (Location)',
            'รายละเอียด',
            'สถานะ',
        ];
    }

    public function map($machine): array
    {
        return [
            $machine->id,
            $machine->name,
            $machine->location->location_name ?? '-',
            $machine->description ?? '',
            $machine->is_active ? 'ใช้งาน' : 'ไม่ใช้งาน',
        ];
    }
}
