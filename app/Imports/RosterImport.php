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
            $rawEmployeeId = $row[0]; // First column is Employee ID
            if (!$rawEmployeeId) continue;
            
            // Fix: Trim employee ID to prevent phantom duplicate employees due to trailing spaces
            $employeeId = trim($rawEmployeeId);

            // Prevent phantom employees: require employee to exist in Master Data
            $employee = Employee::where('employee_id', $employeeId)->where('department_id', $this->departmentId)->first();
            
            if (!$employee) {
                throw new \Exception("ไม่พบพนักงานรหัส '{$employeeId}' ในแผนกนี้ โปรดเพิ่มข้อมูลพนักงานในระบบ Master Data ก่อนนำเข้า Roster");
            }

            for ($i = 0; $i < 7; $i++) {
                $colIndex = 4 + $i; // Days start at column index 4 (E)
                if (!isset($row[$colIndex])) {
                    // Cell might be completely empty
                    $cellValue = null;
                } else {
                    // Clean non-breaking spaces (NBSP) and regular spaces
                    $cellValue = trim(str_replace("\u{00A0}", ' ', (string) $row[$colIndex]));
                    // Replace En-dash, Em-dash, Minus sign with standard hyphen
                    $cellValue = str_replace(["\u{2013}", "\u{2014}", "\u{2212}"], '-', $cellValue);
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

                $shift = $this->findShift($shifts, $cellValue, $employeeId, $date);

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

    /**
     * Resolve one roster cell to exactly one shift.
     *
     * Roster cells carry the full time range ("17.00-02.00"), so the reliable comparison is
     * against the shift's own start/end. Substring matching on the name is not: every name
     * has the shape "<type> HH.mm-HH.mm", so "17.00" sits inside both "กะเช้า 08.00-17.00"
     * and "กะบ่าย 17.00-02.00". Taking whichever came first rostered people onto the wrong
     * shift with no error at all, and the roster decides who each inspection round covers.
     *
     * Anything ambiguous is an error now, never a guess.
     */
    protected function findShift(Collection $shifts, string $cellValue, string $employeeId, string $date): Shift
    {
        $where = "(พนักงานรหัส {$employeeId} วันที่ {$date})";
        $needle = $this->normaliseShiftText($cellValue);

        // 1. The cell names the shift outright.
        $exact = $shifts->filter(fn ($s) => $this->normaliseShiftText($s->shift_name) === $needle);
        if ($exact->count() === 1) {
            return $exact->first();
        }
        if ($exact->count() > 1) {
            $this->failAmbiguous($cellValue, $exact, $where);
        }

        // 2. The cell is a time range - compare it against the shift's own hours.
        if (preg_match('/^(\d{1,2})[.](\d{2})-(\d{1,2})[.](\d{2})$/', $needle, $m)) {
            $start = sprintf('%02d:%02d', $m[1], $m[2]);
            $end = sprintf('%02d:%02d', $m[3], $m[4]);

            $byTime = $shifts->filter(fn ($s) => substr((string) $s->start_time, 0, 5) === $start
                && substr((string) $s->end_time, 0, 5) === $end);

            if ($byTime->count() === 1) {
                return $byTime->first();
            }
            if ($byTime->count() > 1) {
                $this->failAmbiguous($cellValue, $byTime, $where);
            }
        }

        // 3. Last resort: the loose match the import used to make - but it has to land on a
        //    single shift. More than one means the cell does not say enough.
        $loose = $needle === '' ? collect() : $shifts->filter(
            fn ($s) => str_contains($this->normaliseShiftText($s->shift_name), $needle)
        );
        if ($loose->count() === 1) {
            return $loose->first();
        }
        if ($loose->count() > 1) {
            $this->failAmbiguous($cellValue, $loose, $where);
        }

        throw new \Exception("ไม่พบกะเวลา '{$cellValue}' ในระบบ {$where} โปรดเพิ่มกะเวลานี้ในระบบก่อนนำเข้า");
    }

    protected function failAmbiguous(string $cellValue, Collection $candidates, string $where): void
    {
        $names = $candidates->pluck('shift_name')->implode(', ');

        throw new \Exception(
            "กะเวลา '{$cellValue}' ตรงกับหลายกะในระบบ {$where}: {$names} "
            . 'โปรดระบุเป็นช่วงเวลาเต็ม เช่น 17.00-02.00'
        );
    }

    /**
     * Strip the unicode invisible spaces Excel leaves behind, and treat ':' and '.' as the
     * same separator, so "17:00 - 02:00" and "17.00-02.00" compare equal.
     */
    protected function normaliseShiftText(?string $value): string
    {
        return str_replace(
            ':',
            '.',
            preg_replace('/[\s\x{200B}-\x{200D}\x{FEFF}]+/u', '', mb_strtolower((string) $value))
        );
    }
}
