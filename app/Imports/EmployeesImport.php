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
        // 0: ID (ห้ามแก้ไข)
        // 1: employee_id (รหัสพนักงาน)
        // 2: prefix (คำนำหน้า)
        // 3: fname (ชื่อจริง)
        // 4: lname (นามสกุล)
        // 5: department names (แผนก)
        // 6: level (ระดับ)

        $id = trim($row[0] ?? '');
        $employeeId = trim($row[1] ?? '');
        $prefix = trim($row[2] ?? 'คุณ');
        $fname = trim($row[3] ?? '');
        $lname = trim($row[4] ?? '');
        $deptName = trim($row[5] ?? '');
        $level = trim($row[6] ?? '');
        $shiftName = trim($row[7] ?? '');

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

        $shiftId = null;
        if ($shiftName && $shiftName !== '-') {
            $shift = \App\Models\Shift::firstOrCreate(
                ['shift_name' => $shiftName],
                [
                    'shift_code' => Str::slug($shiftName, '_') . '_' . time(),
                    'description' => 'Imported via Excel',
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00'
                ]
            );
            $shiftId = $shift->id;
        }

        $data = [
            'employee_id'   => $employeeId,
            'prefix'        => $prefix,
            'fname'         => $fname,
            'lname'         => $lname,
            'fullname'      => $prefix . ' ' . $fname . ' ' . $lname,
            'department_id' => $department->id,
            'level'         => $level,
            'qr_code_hash'  => Str::uuid(),
            'is_active'     => true,
        ];

        if ($shiftId) {
            $data['shift_id'] = $shiftId;
        }

        if ($id) {
            $employee = Employee::withTrashed()->find($id);
            if ($employee) {
                if ($employee->trashed()) {
                    $employee->restore();
                }
                unset($data['qr_code_hash']); // Don't overwrite existing QR hash
                $employee->update($data);
                return $employee;
            }
        }
        
        // If no ID or ID not found, check by employee_id just in case, or create new
        $employee = Employee::withTrashed()->where('employee_id', $employeeId)->first();
        if ($employee) {
            if ($employee->trashed()) {
                $employee->restore();
            }
            unset($data['qr_code_hash']);
            $employee->update($data);
            return $employee;
        }

        return Employee::create($data);
    }
}
