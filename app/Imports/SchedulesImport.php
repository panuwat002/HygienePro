<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SchedulesImport implements ToCollection
{
    public $skippedCount = 0;
    public $importedCount = 0;

    public function collection(Collection $rows)
    {
        $dateMap = []; // column_index => Carbon
        $employeeCodeCol = null;

        foreach ($rows as $rowIndex => $row) {
            // 1. Find the Date Header Row (look for columns containing dates like DD-MM-YYYY or Excel serial)
            if (empty($dateMap)) {
                $foundDates = false;
                foreach ($row as $colIndex => $cell) {
                    if (!$cell) continue;
                    
                    // Case 1: String date like "20-06-2026" or "20/06/2026"
                    if (is_string($cell) && preg_match('/^(\d{1,2})[\-\/](\d{1,2})[\-\/](\d{4})$/', trim($cell), $matches)) {
                        try {
                            $dateStr = str_replace('/', '-', trim($cell));
                            $dateMap[$colIndex] = Carbon::parse($dateStr);
                            $foundDates = true;
                        } catch (\Exception $e) {}
                    }
                    // Case 2: Excel Serial Date (e.g. 46200 for year 2026)
                    elseif (is_numeric($cell) && $cell > 30000 && $cell < 60000) {
                        try {
                            $dateMap[$colIndex] = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cell));
                            $foundDates = true;
                        } catch (\Exception $e) {}
                    }
                }
                
                if ($foundDates) {
                    continue; // Skip this header row once we found dates
                } else {
                    continue; // Keep looking in next rows if we didn't find dates
                }
            }

            // 2. Parse Employee Rows
            // Assume employee code is in column 1 (B) or 2 (C) based on typical layout.
            $employeeCode = null;
            $nameFull = 'Unknown';
            $deptName = 'ส่วนกลาง';
            $locationName = '';

            if (isset($row[1]) && is_numeric(trim($row[1]))) {
                $employeeCode = trim($row[1]);
                $nameFull = isset($row[2]) ? trim($row[2]) : 'Unknown';
                $deptName = isset($row[3]) && trim($row[3]) !== '' ? trim($row[3]) : 'ส่วนกลาง';
                $locationName = isset($row[4]) ? trim($row[4]) : '';
            } elseif (isset($row[0]) && is_numeric(trim($row[0]))) {
                $employeeCode = trim($row[0]);
                $nameFull = isset($row[1]) ? trim($row[1]) : 'Unknown';
                $deptName = isset($row[2]) && trim($row[2]) !== '' ? trim($row[2]) : 'ส่วนกลาง';
                $locationName = isset($row[3]) ? trim($row[3]) : '';
            }

            if (!$employeeCode) {
                continue;
            }

            // Find Employee in DB, including soft-deleted ones
            $employee = Employee::withTrashed()->where('employee_id', $employeeCode)
                ->orWhere('employee_id', ltrim($employeeCode, '0'))
                ->first();

            if (!$employee) {
                // Create missing employee automatically
                $employee = Employee::create([
                    'employee_id' => $employeeCode,
                    'fname' => $nameFull,
                    'lname' => '',
                    'prefix' => '',
                    'fullname' => $nameFull,
                    'is_active' => true,
                    'qr_code_hash' => (string) \Illuminate\Support\Str::uuid(),
                ]);
            } else if ($employee->trashed()) {
                $employee->restore();
            }

            // Update Department using correct column
            if ($deptName) {
                $normalizeDept = function ($str) {
                    return mb_strtolower(preg_replace('/^แผนก\s*/u', '', str_replace(' ', '', $str)));
                };
                $normalizedDept = $normalizeDept($deptName);
                
                $department = \App\Models\Department::all()->first(function ($d) use ($normalizeDept, $normalizedDept) {
                    return $normalizeDept($d->dept_name) === $normalizedDept;
                });
                
                if (!$department) {
                    $department = \App\Models\Department::create([
                        'dept_name' => $deptName,
                        'dept_code' => \Illuminate\Support\Str::slug($deptName, '_') . '_' . time(), 
                        'dept_description' => 'Auto Created from Schedule Import'
                    ]);
                }
                
                $employee->department_id = $department->id;
            }
            
            if ($nameFull && $nameFull !== 'Unknown') {
                $employee->fname = $nameFull;
                $employee->fullname = $nameFull;
            }
            $employee->save();
            
            $this->importedCount++;

            // Update Location using correctly mapped column
            if ($locationName) {
                // Support multi-line location names, taking the last line as primary
                $parts = preg_split('/[\r\n]+/', $locationName);
                $searchName = trim(end($parts)); 
                if ($searchName) {
                    $normalizeLoc = function ($str) {
                        return mb_strtolower(preg_replace('/^ห้อง\s*/u', '', str_replace(' ', '', $str)));
                    };
                    $normalizedSearch = $normalizeLoc($searchName);
                    
                    $location = \App\Models\Location::all()->first(function ($loc) use ($normalizeLoc, $normalizedSearch) {
                        return $normalizeLoc($loc->location_name) === $normalizedSearch;
                    });

                    if (!$location) {
                        $location = \App\Models\Location::create([
                            'location_name' => $searchName,
                            'location_code' => 'LOC_' . time(), 
                            'description' => 'Auto Created from Schedule Import'
                        ]);
                    }
                    
                    if ($location) {
                        $employee->location_id = $location->id;
                        $employee->save();
                    }
                }
            }

            $firstShiftFound = false;
            
            // 3. Process each date column for this employee
            foreach ($dateMap as $colIndex => $date) {
                $cellValue = isset($row[$colIndex]) ? trim($row[$colIndex]) : null;

                if (!$cellValue) {
                    continue;
                }

                $isDayOff = false;
                $startTime = null;
                $endTime = null;

                if (strtoupper($cellValue) === 'WH' || strtoupper($cellValue) === 'OFF') {
                    $isDayOff = true;
                } else {
                    // Try to extract times. Format might be "19.00 \n 04.00"
                    $parts = preg_split('/[\r\n]+/', $cellValue);
                    if (count($parts) >= 2) {
                        $startTime = $this->parseTime($parts[0]);
                        $endTime = $this->parseTime($parts[1]);
                    } else {
                        // If only one time is provided or they are space separated
                        $parts = explode(' ', preg_replace('/\s+/', ' ', $cellValue));
                        if (count($parts) >= 2) {
                            $startTime = $this->parseTime($parts[0]);
                            $endTime = $this->parseTime($parts[count($parts) - 1]);
                        } else {
                            // Might just be one time, or need special handling
                            $startTime = $this->parseTime($cellValue);
                        }
                    }
                    
                    // Extract times for Daily Schedule logging
                    if ($startTime && $endTime) {
                        $shift = \App\Models\Shift::where('start_time', $startTime)
                            ->where('end_time', $endTime)
                            ->first();
                            
                        if (!$shift) {
                            $timeStr = Carbon::parse($startTime)->format('H:i:s');
                            $shift = \App\Models\Shift::where(function ($q) use ($timeStr) {
                                $q->where('start_time', '<=', $timeStr)->where('end_time', '>=', $timeStr);
                            })->orWhere(function ($q) use ($timeStr) {
                                $q->whereColumn('start_time', '>', 'end_time')
                                  ->where(function ($sub) use ($timeStr) {
                                      $sub->where('start_time', '<=', $timeStr)
                                          ->orWhere('end_time', '>=', $timeStr);
                                  });
                            })->first();
                        }
                        // Note: We deliberately do NOT save this shift as the employee's default shift
                        // to prevent overwriting their master data.
                    }
                }

                // Save to database
                EmployeeSchedule::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'date' => $date->format('Y-m-d'),
                    ],
                    [
                        'shift_id' => isset($shift) && $shift ? $shift->id : null,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'is_day_off' => $isDayOff,
                    ]
                );
            }
        }
    }

    private function parseTime($timeStr)
    {
        $timeStr = trim($timeStr);
        if (!$timeStr) return null;
        
        // Convert "19.00" or "19:00" to "19:00:00"
        $timeStr = str_replace('.', ':', $timeStr);
        
        try {
            return Carbon::parse($timeStr)->format('H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}
