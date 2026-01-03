<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeesExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Employee::with('department')->get();
    }

    public function headings(): array
    {
        return [
            'รหัสพนักงาน',
            'คำนำหน้า',
            'ชื่อจริง',
            'นามสกุล',
            'แผนก',
            'ระดับ',
        ];
    }

    public function map($employee): array
    {
        return [
            $employee->employee_id,
            $employee->prefix,
            $employee->fname,
            $employee->lname,
            $employee->department->dept_name ?? '-',
            $employee->level,
        ];
    }
}
