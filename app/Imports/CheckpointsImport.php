<?php

namespace App\Imports;

use App\Models\Checkpoint;
use App\Models\CheckpointCategory;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class CheckpointsImport implements ToModel, WithStartRow
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

        // Mapping based on CheckpointsExport structure:
        // 0: category name (หมวดหมู่)
        // 1: title (ชื่อจุดตรวจ)
        // 2: description (คำอธิบาย)
        // 3: type (ประเภท person/area)
        // 4: status (สถานะ)

        $categoryName = trim($row[0] ?? '');
        $title        = trim($row[1] ?? '');
        $description  = trim($row[2] ?? '');
        $type         = trim($row[3] ?? 'person');
        $status       = trim($row[4] ?? 'ใช้งาน');

        if (!$title) {
            return null;
        }

        // Validate type
        if (!in_array($type, ['person', 'area'])) {
            $type = 'person';
        }

        // Determine is_active
        $isActive = !in_array($status, ['ไม่ใช้งาน', 'inactive', '0', 'no', 'false']);

        // Find or create category
        $categoryId = null;
        if ($categoryName) {
            $category   = CheckpointCategory::firstOrCreate(
                ['name' => $categoryName],
                ['is_active' => true]
            );
            $categoryId = $category->id;
        }

        return Checkpoint::updateOrCreate(
            ['title' => $title],
            [
                'description' => $description ?: null,
                'category_id' => $categoryId,
                'type'        => $type,
                'is_active'   => $isActive,
            ]
        );
    }
}
