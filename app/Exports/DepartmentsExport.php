<?php

namespace App\Exports;

use App\Models\Department;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DepartmentsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Department::all();
    }

    public function headings(): array
    {
        return [
            'รหัสแผนก',
            'ชื่อแผนก',
            'คำอธิบาย',
            'ประเภทการมองเห็น (global/isolated)',
        ];
    }

    public function map($department): array
    {
        return [
            $department->dept_code,
            $department->dept_name,
            $department->dept_description ?? '',
            $department->visibility_type ?? 'global',
        ];
    }
}
