<?php

namespace App\Exports;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RosterExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $departmentId;
    protected $startDate;
    protected $endDate;
    protected $schedules;
    protected $employeeIds;

    public function __construct($departmentId, $startDate, $employeeIds = [])
    {
        $this->departmentId = $departmentId;
        $this->startDate = Carbon::parse($startDate)->startOfDay();
        $this->endDate = $this->startDate->copy()->addDays(6)->endOfDay();
        $this->employeeIds = $employeeIds;
    }

    public function collection()
    {
        $query = Employee::where('department_id', $this->departmentId)
            ->where('is_active', true);
            
        if (!empty($this->employeeIds)) {
            $query->whereIn('id', $this->employeeIds);
        }
        
        $employees = $query->get();

        if ($employees->isEmpty()) {
            $dummy = new \stdClass();
            $dummy->is_dummy = true;
            $dummy->employee_id = 'EX-001';
            $dummy->fname = 'ชื่อตัวอย่าง';
            $dummy->lname = 'นามสกุลตัวอย่าง';
            
            $sampleShift = Shift::where('department_id', $this->departmentId)
                ->orWhereNull('department_id')
                ->first();
            $dummy->sample_shift = $sampleShift ? $sampleShift->shift_name : 'Morning';

            return collect([$dummy]);
        }

        $this->schedules = EmployeeSchedule::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$this->startDate->toDateString(), $this->endDate->toDateString()])
            ->get()
            ->groupBy('employee_id');

        return $employees;
    }

    public function headings(): array
    {
        $headings = [
            'Employee ID',
            'Title',
            'First Name',
            'Last Name',
        ];

        for ($i = 0; $i < 7; $i++) {
            $date = $this->startDate->copy()->addDays($i);
            $dayName = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'][$date->dayOfWeek];
            $headings[] = $dayName . ' ' . $date->format('d/m/Y');
        }

        return $headings;
    }

    public function map($employee): array
    {
        if (isset($employee->is_dummy) && $employee->is_dummy) {
            $row = [
                $employee->employee_id,
                'นาย',
                $employee->fname,
                $employee->lname,
            ];
            // ตัวอย่างการใส่กะ
            for ($i = 0; $i < 7; $i++) {
                if ($i === 0) $row[] = $employee->sample_shift; // ตัวอย่างกะ
                elseif ($i === 6) $row[] = 'OFF'; // ตัวอย่างวันหยุด
                else $row[] = '';
            }
            return $row;
        }

        $row = [
            $employee->employee_id,
            $employee->prefix ?? '',
            $employee->fname,
            $employee->lname,
        ];

        $empSchedules = $this->schedules->get($employee->id);

        for ($i = 0; $i < 7; $i++) {
            $currentDate = $this->startDate->copy()->addDays($i)->toDateString();
            $schedule = $empSchedules ? $empSchedules->firstWhere('date', clone $this->startDate->copy()->addDays($i)->startOfDay()) : null;
            if (!$schedule && $empSchedules) {
                 $schedule = $empSchedules->firstWhere('date', clone $this->startDate->copy()->addDays($i));
            }

            if ($schedule) {
                if ($schedule->is_day_off) {
                    $row[] = 'OFF';
                } elseif ($schedule->shift) {
                    $row[] = $schedule->shift->shift_name;
                } else {
                    $row[] = '';
                }
            } else {
                $row[] = '';
            }
        }

        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
