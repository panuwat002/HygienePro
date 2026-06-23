<?php

namespace App\Imports;

use App\Models\Location;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class LocationsImport implements ToModel, WithStartRow
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

        // Mapping based on LocationsExport structure:
        // 0: location_name (ชื่อจุดประจำการ)
        // 1: description (คำอธิบาย)

        $locationName = trim($row[0] ?? '');
        $description  = trim($row[1] ?? '');

        if (!$locationName) {
            return null;
        }

        return Location::updateOrCreate(
            ['location_name' => $locationName],
            ['description'   => $description ?: null]
        );
    }
}
