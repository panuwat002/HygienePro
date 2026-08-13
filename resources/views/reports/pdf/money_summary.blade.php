<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { margin: 15px 20px; }
        @font-face {
            font-family: 'THSarabunNew';
            font-style: normal;
            font-weight: normal;
            src: url("{{ public_path('fonts/THSarabunNew.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'THSarabunNew';
            font-style: normal;
            font-weight: bold;
            src: url("{{ public_path('fonts/THSarabunNew-Bold.ttf') }}") format('truetype');
        }
        body { font-family: "THSarabunNew", sans-serif; font-size: 13px; line-height: 1.1; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 4px 6px; text-align: center; font-size: 12px; }
        th { background-color: #f3f4f6; font-weight: bold; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-danger { color: #dc2626; font-weight: bold; }
        .header { text-align: center; margin-bottom: 10px; }
        .header h2 { font-size: 18px; margin: 0; }
        .summary-box { border: 1px solid #000; padding: 8px; margin-bottom: 10px; background-color: #fef2f2; }
    </style>
</head>
<body>
    <div class="header">
        <h2>รายงานสรุปมูลค่าความเสียหาย (Money Report / Cost of Non-Conformance)</h2>
        <p>ประจำเดือน: {{ \Carbon\Carbon::parse($month . '-01')->format('m/Y') }}</p>
    </div>

    <div class="summary-box">
        <strong>มูลค่าความเสียหายประเมินรวมทั้งสิ้น (Total Financial Loss):</strong>
        <span class="text-danger" style="font-size: 16px;">฿{{ number_format($totalLoss, 2) }}</span>
        (จำนวนรายการบกพร่องทั้งสิ้น: {{ count($logs) }} รายการ)
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%">ลำดับ</th>
                <th style="width: 12%">วัน-เวลา</th>
                <th style="width: 12%">แผนก</th>
                <th style="width: 16%">เป้าหมาย</th>
                <th style="width: 20%">หัวข้อที่ตก (Defect)</th>
                <th style="width: 20%">การแก้ไข & มาตรการป้องกัน</th>
                <th style="width: 15%">มูลค่าเสียหาย</th>
            </tr>
        </thead>
        <tbody>
            @php $i = 1; @endphp
            @forelse($logs as $log)
                @php
                    $target = $log->employee ? $log->employee->fullname : ($log->machine ? $log->machine->name : ($log->location ? $log->location->location_name : '-'));
                    $lossAmt = ($log->correctiveAction && $log->correctiveAction->financial_loss > 0)
                        ? (float) $log->correctiveAction->financial_loss
                        : ((float) ($log->checkpoint->default_cost_impact ?? 500));
                @endphp
                <tr>
                    <td>{{ $i++ }}</td>
                    <td>{{ $log->inspected_at ? $log->inspected_at->format('d/m/Y H:i') : '-' }}</td>
                    <td class="text-left">{{ $log->session->department->dept_name ?? '-' }}</td>
                    <td class="text-left">{{ $target }}</td>
                    <td class="text-left text-danger">{{ $log->checkpoint->title ?? '-' }}</td>
                    <td class="text-left">
                        @if($log->correctiveAction && $log->correctiveAction->preventive_action)
                            <div style="font-weight: bold; color: #b45309;">ป้องกัน: {{ $log->correctiveAction->preventive_action }}</div>
                        @elseif($log->correctiveAction && $log->correctiveAction->action_taken)
                            <div>แก้ไข: {{ $log->correctiveAction->action_taken }}</div>
                        @elseif($log->note)
                            <div>{{ $log->note }}</div>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right text-danger">฿{{ number_format($lossAmt, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">ไม่พบข้อมูลความเสียหายประจำเดือนนี้</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
