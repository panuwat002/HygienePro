<?php

namespace App\Exports;

use App\Models\Checkpoint;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CheckpointsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Checkpoint::with('category')->get();
    }

    public function headings(): array
    {
        return [
            'หมวดหมู่',
            'ชื่อจุดตรวจ',
            'คำอธิบาย',
            'ประเภท (person/area)',
            'สถานะ',
        ];
    }

    public function map($checkpoint): array
    {
        return [
            $checkpoint->category->name ?? '',
            $checkpoint->title,
            $checkpoint->description ?? '',
            $checkpoint->type ?? 'person',
            $checkpoint->is_active ? 'ใช้งาน' : 'ไม่ใช้งาน',
        ];
    }
}
