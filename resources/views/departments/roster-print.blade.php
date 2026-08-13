<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตารางการทำงาน - {{ $department->dept_name }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Sarabun', sans-serif;
            background: #fff;
            color: #222;
            padding: 15px 25px;
        }

        /* Header */
        .doc-header {
            margin-bottom: 12px;
        }
        .doc-header table {
            width: 100%;
            border: none;
        }
        .doc-header td {
            border: none;
            padding: 2px 0;
            vertical-align: top;
        }
        .doc-title {
            font-size: 18px;
            font-weight: 700;
            text-align: center;
            padding-bottom: 8px;
        }
        .doc-info {
            font-size: 12px;
            color: #444;
        }
        .doc-info strong {
            color: #000;
        }
        .header-line {
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
            height: 4px;
            margin-bottom: 10px;
        }

        /* Main Table */
        .roster {
            width: 100%;
            border-collapse: collapse;
        }
        .roster th {
            background: #e8e8e8 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 3px;
            border: 1px solid #888;
            text-align: center;
        }
        .roster th.emp-header {
            text-align: left;
            padding-left: 6px;
            width: 22%;
        }
        .roster td {
            border: 1px solid #bbb;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
            height: 28px;
            font-size: 11px;
        }
        .roster tbody tr:nth-child(even) td {
            background: #f7f7f7 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .roster .emp-cell {
            text-align: left;
            padding-left: 6px;
            white-space: nowrap;
        }
        .roster .emp-num {
            display: inline-block;
            width: 22px;
            text-align: right;
            margin-right: 4px;
            color: #999;
            font-size: 10px;
        }
        .roster .emp-name {
            font-weight: 600;
            font-size: 11px;
        }
        .roster .emp-id {
            font-size: 9px;
            color: #999;
            margin-left: 26px;
        }
        .day-off { color: #d00; font-weight: 700; font-size: 10px; }
        .shift-info { font-size: 11px; font-weight: 600; }
        .shift-time { font-size: 9px; color: #777; }

        /* Footer */
        .doc-footer {
            margin-top: 8px;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #ccc;
            padding-top: 4px;
        }
        .signature-row {
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            padding: 0 40px;
            page-break-inside: avoid;
        }
        .sig {
            text-align: center;
            font-size: 11px;
        }
        .sig-line {
            width: 180px;
            border-bottom: 1px dotted #555;
            height: 30px;
            margin: 0 auto 3px;
        }
        .sig-title { font-weight: 600; }
        .sig-date { font-size: 10px; color: #888; }

        @media print {
            @page { size: A4 landscape; margin: 8mm; }
            body { padding: 0; }
            .no-print { display: none !important; }
        }
        .print-bar {
            text-align: center;
            margin: 25px 0 0;
        }
        .print-bar button {
            font-family: 'Sarabun', sans-serif;
            padding: 8px 28px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 4px;
        }
        .btn-p { background: #2563eb; color: #fff; }
        .btn-c { background: #e5e7eb; color: #333; }
    </style>
</head>
<body onload="window.print()">

    <!-- Header -->
    <div class="doc-header">
        <div class="doc-title">ตารางการทำงานประจำสัปดาห์</div>
        <div class="header-line"></div>
        <table>
            <tr>
                <td class="doc-info" style="width:33%"><strong>แผนก:</strong> {{ $department->dept_name }}</td>
                <td class="doc-info" style="width:34%; text-align:center"><strong>สัปดาห์:</strong> {{ $startDate->format('d/m/Y') }} – {{ $endDate->format('d/m/Y') }}</td>
                <td class="doc-info" style="width:33%; text-align:right"><strong>พนักงาน:</strong> {{ $employees->count() }} คน</td>
            </tr>
        </table>
    </div>

    <!-- Roster Table -->
    <table class="roster">
        <thead>
            <tr>
                <th class="emp-header">พนักงาน</th>
                @for($i = 0; $i < 7; $i++)
                    @php $d = $startDate->copy()->addDays($i); @endphp
                    <th>{{ ['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'][$d->dayOfWeek] }} {{ $d->format('d/m') }}</th>
                @endfor
            </tr>
        </thead>
        @php
            $weekDates = [];
            for($i = 0; $i < 7; $i++) {
                $weekDates[] = $startDate->copy()->addDays($i)->toDateString();
            }
        @endphp
        <tbody>
            @foreach($employees as $idx => $employee)
                <tr>
                    <td class="emp-cell">
                        <span class="emp-num">{{ $idx + 1 }}.</span>
                        <span class="emp-name">{{ $employee->fname }} {{ $employee->lname }}</span>
                        <div class="emp-id">{{ $employee->employee_id }}</div>
                    </td>
                    @foreach($weekDates as $currentDate)
                        @php
                            $empSchedule = $schedules[$employee->id][$currentDate] ?? null;
                            if (!$empSchedule && $schedules instanceof \Illuminate\Support\Collection) {
                                $empSchedule = $schedules->get($employee->id)?->firstWhere('date', clone $startDate->copy()->addDays($loop->index)->startOfDay());
                                if (!$empSchedule) {
                                    $empSchedule = $schedules->get($employee->id)?->firstWhere('date', clone $startDate->copy()->addDays($loop->index));
                                }
                            }
                        @endphp
                        <td>
                            @if($empSchedule && $empSchedule->is_day_off)
                                <span class="day-off">✕ หยุด</span>
                            @elseif($empSchedule && $empSchedule->shift_id)
                                @php $s = $shifts->firstWhere('id', $empSchedule->shift_id); @endphp
                                @if($s)
                                    <div class="shift-info">{{ $s->shift_name }}</div>
                                    <div class="shift-time">{{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($s->end_time)->format('H:i') }}</div>
                                @endif
                            @else
                                –
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Legend -->
    <div class="doc-footer">
        <strong>หมายเหตุ:</strong> " – " = ทำงานตามกะหลักประจำตัว &nbsp;|&nbsp; <span class="day-off">✕ หยุด</span> = วันหยุด
    </div>

    <!-- Signatures -->
    <div class="signature-row">
        <div class="sig">
            <div class="sig-line"></div>
            <div class="sig-title">ผู้จัดทำ</div>
            <div class="sig-date">วันที่ ......./......./............</div>
        </div>
        <div class="sig">
            <div class="sig-line"></div>
            <div class="sig-title">หัวหน้างาน</div>
            <div class="sig-date">วันที่ ......./......./............</div>
        </div>
        <div class="sig">
            <div class="sig-line"></div>
            <div class="sig-title">ผู้อนุมัติ</div>
            <div class="sig-date">วันที่ ......./......./............</div>
        </div>
    </div>

    <!-- Screen buttons -->
    <div class="print-bar no-print">
        <button class="btn-p" onclick="window.print()">🖨️ พิมพ์</button>
        <button class="btn-c" onclick="window.close()">ปิด</button>
    </div>

</body>
</html>
