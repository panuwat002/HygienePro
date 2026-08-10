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
        // 0: ID (ห้ามแก้ไข)
        // 1: location_name (ชื่อจุดประจำการ)
        // 2: description (คำอธิบาย)

        $id = trim($row[0] ?? '');
        $locationName = trim($row[1] ?? '');
        $description  = trim($row[2] ?? '');

        if (!$locationName) {
            return null;
        }

        $data = [
            'location_name' => $locationName,
            'description'   => $description ?: null
        ];

        if ($id) {
            $location = Location::find($id);
            if ($location) {
                $location->update($data);
                return $location;
            }
        }

        $normalizeLoc = function ($str) {
            return mb_strtolower(preg_replace('/^ห้อง\s*/u', '', str_replace(' ', '', $str)));
        };
        $normalizedSearch = $normalizeLoc($locationName);

        $location = Location::all()->first(function ($loc) use ($normalizeLoc, $normalizedSearch) {
            return $normalizeLoc($loc->location_name) === $normalizedSearch;
        });

        if ($location) {
            $location->update($data);
            return $location;
        }

        return Location::create($data);
    }
}
