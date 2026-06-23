<?php

namespace App\Imports;

use App\Models\Location;
use App\Models\Machine;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class MachinesImport implements ToModel, WithStartRow
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

        // Mapping based on MachinesExport structure:
        // 0: code (รหัสเครื่องจักร)
        // 1: name (ชื่อเครื่องจักร / พื้นที่ย่อย)
        // 2: location name (ที่ตั้ง)
        // 3: description (รายละเอียด)
        // 4: status (สถานะ) - optional

        $code = trim($row[0] ?? '');
        $name = trim($row[1] ?? '');
        $locationName = trim($row[2] ?? '');
        $description = trim($row[3] ?? '');
        $status = trim($row[4] ?? 'ใช้งาน');

        // Name is required
        if (!$name) {
            return null;
        }

        // Generate code if empty
        if (!$code) {
            $code = 'M-' . Str::random(6);
        }

        // Find or Create Location
        if (!$locationName) {
            $locationName = 'ส่วนกลาง';
        }
        $location = Location::firstOrCreate(
            ['location_name' => $locationName],
            ['description' => 'Imported via Excel']
        );

        // Determine is_active status
        $isActive = !in_array($status, ['ไม่ใช้งาน', 'inactive', '0', 'no', 'false']);

        return Machine::updateOrCreate(
            ['code' => $code],
            [
                'name'        => $name,
                'location_id' => $location->id,
                'description' => $description,
                'is_active'   => $isActive,
            ]
        );
    }
}
