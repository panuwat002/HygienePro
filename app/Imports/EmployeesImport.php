<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class EmployeesImport implements ToModel, WithStartRow
{
    /**
     * @return int
     */
    public function startRow(): int
    {
        return 2;
    }

    public function model(array $row)
    {
        // Check if row is empty/null
        if (!array_filter($row)) {
             return null;
        }

        // Mapping based on EmployeesExport structure:
        // 0: employee_id (รหัสพนักงาน)
        // 1: prefix (คำนำหน้า)
        // 2: fname (ชื่อจริง)
        // 3: lname (นามสกุล)
        // 4: department names (แผนก)
        // 5: level (ระดับ)

        $employeeId = trim($row[0] ?? '');
        $prefix = trim($row[1] ?? 'คุณ');
        $fname = trim($row[2] ?? '');
        $lname = trim($row[3] ?? '');
        $deptName = trim($row[4] ?? '');
        $level = trim($row[5] ?? '');

        // Fallback for "Name Surname" in single column if user uses old template (Column A = Name) represents legacy logic?
        // Let's stick to the Export format as the standard.
        // If ID is empty, generate one?
        if (!$employeeId) {
             $employeeId = Str::random(6);
        }

        // Logic check: if fname is empty but maybe column 0 is name?
        // Let's assume the Export format compliance.
        // If fname is empty, skip
        if (!$fname) {
            return null;
        }

        // Find or Create Department
        if (!$deptName) {
            $deptName = 'ส่วนกลาง';
        }
        $department = Department::firstOrCreate(
            ['dept_name' => $deptName],
            [
                'dept_code' => Str::slug($deptName, '_') . '_' . time(),
                'dept_description' => 'Imported via Excel'
            ]
        );

        return Employee::updateOrCreate(
            ['employee_id' => $employeeId],
            [
                'prefix'        => $prefix,
                'fname'         => $fname,
                'lname'         => $lname,
                'fullname'      => $prefix . ' ' . $fname . ' ' . $lname,
                'department_id' => $department->id,
                'level'         => $level,
                'qr_code_hash'  => Str::uuid(),
                'is_active'     => true,
            ]
        );
    }
}
