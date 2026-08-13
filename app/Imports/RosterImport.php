<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;

class RosterImport implements ToCollection, WithStartRow
{
    protected $departmentId;
    protected $startDate;

    public function __construct($departmentId, $startDate)
    {
        $this->departmentId = $departmentId;
        $this->startDate = Carbon::parse($startDate)->startOfDay();
    }

    public function startRow(): int
    {
        return 2; // Skip heading row
    }

    public function collection(Collection $rows)
    {
        // Cache shifts to avoid querying for every cell
        $shifts = Shift::where('department_id', $this->departmentId)
            ->orWhereNull('department_id')
            ->get();

        foreach ($rows as $row) {
            $employeeId = $row[0]; // First column is Employee ID
            if (!$employeeId) continue;

            // Loop Engineering: Auto-create employee if they don't exist in this department
            $employee = Employee::firstOrCreate(
                ['employee_id' => $employeeId, 'department_id' => $this->departmentId],
                [
                    'prefix' => $row[1] ?? '',
                    'fname' => $row[2] ?? '',
                    'lname' => $row[3] ?? '',
                    'fullname' => trim(($row[1] ?? '') . ' ' . ($row[2] ?? '') . ' ' . ($row[3] ?? '')),
                    'qr_code_hash' => \Illuminate\Support\Str::random(32),
                    'is_active' => true,
                ]
            );

            for ($i = 0; $i < 7; $i++) {
                $colIndex = 4 + $i; // Days start at column index 4 (E)
                if (!isset($row[$colIndex])) {
                    // Cell might be completely empty
                    $cellValue = null;
                } else {
                    $cellValue = trim($row[$colIndex]);
                }

                $date = $this->startDate->copy()->addDays($i)->toDateString();

                if (empty($cellValue)) {
                    // If empty, delete schedule
                    EmployeeSchedule::where('employee_id', $employee->id)
                        ->where('date', $date)
                        ->delete();
                    continue;
                }

                $upperCell = strtoupper($cellValue);
                if ($upperCell === 'OFF' || $upperCell === 'WH' || str_contains($upperCell, 'หยุด')) {
                    EmployeeSchedule::updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'date' => $date,
                        ],
                        [
                            'shift_id' => null,
                            'is_day_off' => true,
                        ]
                    );
                    continue;
                }

                // Find shift by name (Robust Matching for Loop Engineering)
                $cleanCellValue = str_replace(':', '.', preg_replace('/\s+/', '', strtolower($cellValue)));
                $shift = $shifts->first(function($s) use ($cleanCellValue, $cellValue) {
                    if (trim($s->shift_name) === $cellValue) return true;
                    $cleanShiftName = str_replace(':', '.', preg_replace('/\s+/', '', strtolower($s->shift_name)));
                    return $cleanCellValue !== '' && str_contains($cleanShiftName, $cleanCellValue);
                });

                if ($shift) {
                    EmployeeSchedule::updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'date' => $date,
                        ],
                        [
                            'shift_id' => $shift->id,
                            'is_day_off' => false,
                        ]
                    );
                }
            }
        }
    }
}
