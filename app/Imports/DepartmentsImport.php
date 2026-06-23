<?php

namespace App\Imports;

use App\Models\Department;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class DepartmentsImport implements ToModel, WithStartRow
{
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

        // Mapping based on DepartmentsExport structure:
        // 0: dept_code (รหัสแผนก)
        // 1: dept_name (ชื่อแผนก)
        // 2: dept_description (คำอธิบาย)
        // 3: visibility_type (ประเภทการมองเห็น)

        $deptCode        = trim($row[0] ?? '');
        $deptName        = trim($row[1] ?? '');
        $deptDescription = trim($row[2] ?? '');
        $visibilityType  = trim($row[3] ?? 'global');

        if (!$deptName || !$deptCode) {
            return null;
        }

        // Validate visibility_type
        if (!in_array($visibilityType, ['global', 'isolated'])) {
            $visibilityType = 'global';
        }

        return Department::updateOrCreate(
            ['dept_code' => $deptCode],
            [
                'dept_name'        => $deptName,
                'dept_description' => $deptDescription ?: null,
                'visibility_type'  => $visibilityType,
            ]
        );
    }
}
