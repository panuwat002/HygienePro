<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page {
            margin: 5px 10px 5px 10px; /* Minimal page margins */
        }
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
        body {
            font-family: "THSarabunNew", sans-serif;
            font-size: 10px; /* Reduced to 10px */
            line-height: 0.9; /* Very tight line height */
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-top: 1px solid #000;
            border-left: 1px solid #000;
            font-size: 9px;
            margin-bottom: 2px;
            page-break-inside: auto;
        }
        th, td {
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            padding: 2px;
            text-align: center;
            vertical-align: middle;
        }
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        thead {
            display: table-header-group;
        }
        tfoot {
            display: table-footer-group;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            padding: 2px;
        }
        .header {
            text-align: center;
            margin-bottom: 2px;
        }
        .header h1 {
            font-size: 14px;
            font-weight: bold;
            margin: 0;
        }
        .header p {
             margin: 0;
             font-size: 10px;
        }
        .text-left { text-align: left; padding-left: 2px; }
        .text-danger { color: red; font-weight: bold; }
        .text-success { color: green; font-weight: bold; }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 2px;
            text-decoration: underline;
            page-break-after: avoid;
            margin-top: 2px;
        }
        .signatures {
            margin-top: 5px;
            width: 100%;
            page-break-inside: avoid;
        }
        .signature-box {
            width: 33%;
            float: left;
            text-align: center;
            font-size: 9px;
            line-height: normal;
        }
        .signature-line {
            border-bottom: 1px dotted #000;
            width: 85%; 
            margin: 8px auto 2px auto;
            height: 10px; /* Reduced visual height */
        }
        .footer {
            position: fixed;
            bottom: 0px;
            left: 0;
            width: 100%;
            font-size: 8px;
            color: gray;
            line-height: normal;
        }
        .footer-text {
            text-align: right;
            padding-right: 10px;
        }
        .remarks {
            margin-top: 2px;
            font-size: 8px;
            width: 100%;
            white-space: nowrap;
            overflow: hidden;
            page-break-inside: avoid;
            line-height: normal;
        }
    </style>
</head>
<body>
    @php
        $i = 1;
        $j = 1;
        $m = 1;
        $minRows = 25;
    @endphp

    @if(empty($employeeChunks) && empty($areaMachineChunks))
        @include('reports.pdf._header')
        <div style="text-align: center; padding: 20px;">
            ไม่พบข้อมูลการตรวจสอบสำหรับเงื่อนไขที่เลือก
        </div>
    @else
        {{-- EMPLOYEES --}}
        @if(count($employeeChunks) > 0)
            <div class="footer">
                <div class="footer-text">FM-QA-03/01 Rev.03 Effective 26 Nov. 2021</div>
            </div>
        @endif
        
        @foreach($employeeChunks as $chunkIndex => $chunk)
            @include('reports.pdf._header')
            
            <div class="section-title">1. สุขลักษณะส่วนบุคคล (Personal Hygiene)</div>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 3.5%">ลำดับ</th>
                        {{-- ความกว้างแบ่งตามความยาวจริงของชื่อและชื่อแผนกในรายงานนี้
                             ผลรวมคงที่ คอลัมน์จุดตรวจจึงไม่ขยับตามแผนก --}}
                        <th rowspan="2" style="width: {{ $personColumnWidths['name'] ?? 22 }}%">ชื่อ - สกุล</th>
                        <th rowspan="2" style="width: {{ $personColumnWidths['department'] ?? 6.5 }}%">แผนก</th>
                        <th rowspan="2" style="width: 12%">กะ</th>
                        <th colspan="{{ count($personCheckpoints) }}">รายการตรวจ (Checkpoints)</th>
                        <th rowspan="2" style="width: 8%">หมายเหตุ</th>
                    </tr>
                    <tr>
                        @foreach($personCheckpoints as $checkpoint)
                            <th>{{ $checkpoint->title }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($chunk as $empId => $data)
                        <tr>
                            <td>{{ $i++ }}</td>
                            <td class="text-left">{{ $data['info']->fullname ?? $data['info']->fname }}</td>
                            <td>{{ $data['session']->department->dept_name ?? $data['info']->department->dept_name ?? '-' }}</td>
                            @php
                                $thaiShift = $data['roster_shift'] ?? ($data['session'] ? $data['session']->shift_label : '-');
                            @endphp
                            <td style="white-space: nowrap; font-size: 8.5px;">{{ $thaiShift }}</td>
                            
                            @php
                                // จุดตรวจที่ "กำหนดให้คนนี้" — ถ้าไม่มีการกำหนดไว้เลย (ข้อมูลเก่า)
                                // ให้ถือว่าทุกจุดเกี่ยวข้อง จะได้ไม่กลายเป็น N/A ทั้งแถว
                                $assignedToThisPerson = ($assignedCheckpointsByEmployee ?? collect())->get($empId);
                            @endphp
                            @foreach($personCheckpoints as $checkpoint)
                                @php
                                    $applies = $assignedToThisPerson === null
                                        || in_array($checkpoint->id, $assignedToThisPerson);
                                @endphp
                                <td @unless($applies) style="background-color: #e9ecef;" @endunless>
                                    @if(isset($data['results'][$checkpoint->id]))
                                        @php $log = $data['results'][$checkpoint->id]; @endphp
                                        @if($log->result === 'pass' || ($log->result === 'fail' && $log->isResolved()))
                                            <span class="text-success">/</span>
                                        @elseif($log->result === 'fail')
                                            <span class="text-danger">X</span>
                                        @else
                                            -
                                        @endif
                                    @elseif(! $applies)
                                        {{-- ไม่ได้กำหนดให้พนักงานคนนี้ เช่น ห้องแคะที่ไม่ต้องผ่านตู้เป่าลม --}}
                                        <span style="color: #6c757d; font-size: 7.5px;">N/A</span>
                                    @else
                                        {{-- กำหนดไว้แต่ไม่มีผลบันทึก — ช่องว่างจริง ต้องเห็น --}}
                                        <span style="color: #333; font-weight: bold;">-</span>
                                    @endif
                                </td>
                            @endforeach

                            <td class="text-left">
                                @php
                                    $issues = [];
                                    foreach($data['results'] as $log) {
                                        if($log->result === 'fail') {
                                            $desc = $log->checkpoint->title ?? '';
                                            if($log->note) {
                                                $desc .= ': ' . $log->note;
                                            }
                                            if($log->correctiveAction && $log->correctiveAction->preventive_action) {
                                                $desc .= ' [ป้องกัน: ' . $log->correctiveAction->preventive_action . ']';
                                            } elseif($log->correctiveAction && $log->correctiveAction->action_taken) {
                                                $desc .= ' [แก้ไข: ' . $log->correctiveAction->action_taken . ']';
                                            }
                                            if ($desc) {
                                                $issues[] = $desc;
                                            }
                                        }
                                    }
                                @endphp
                                @if(count($issues) > 0)
                                    <small>{{ implode(', ', $issues) }}</small>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    
                    {{-- Fill Rows for LAST chunk if needed, or if user wants always full pages? 
                         User said "List to end at 25". This means < 25 is fine, but > 25 needs break.
                         The previous code filled rows. I'll include filling rows logic ONLY if it's strictly required for layout consistency.
                    --}}
                </tbody>
            </table>

            {{-- คำอธิบายสัญลักษณ์ — ฟอร์มต้องอ่านเข้าใจได้เองตอนถูกตรวจ audit
                 โดยไม่ต้องมีคนคอยอธิบายว่าช่องว่างแปลว่าอะไร --}}
            <div style="font-size: 8px; color: #333; margin-top: 4px;">
                <strong>สัญลักษณ์:</strong>
                <span class="text-success">/</span> = ผ่าน &nbsp;&bull;&nbsp;
                <span class="text-danger">X</span> = ไม่ผ่าน &nbsp;&bull;&nbsp;
                <span style="color: #6c757d;">N/A</span> (ช่องเทา) = ไม่เกี่ยวข้องกับลักษณะงานของพนักงานคนนั้น เช่น ไลน์ที่ไม่ต้องผ่านตู้เป่าลม &nbsp;&bull;&nbsp;
                <strong>-</strong> = กำหนดให้ตรวจแต่ไม่มีผลบันทึก
            </div>

            @include('reports.pdf._signatures')

            @if(!($loop->last && empty($areaMachineChunks)))
                <div style="page-break-after: always;"></div>
            @endif
        @endforeach

        {{-- AREA AND MACHINES (UNIFIED) --}}
        @if(count($areaMachineChunks) > 0)
            <div class="footer">
                <div class="footer-text">FM-QA-22 Rev.01 Effective 1 Sep.2025</div>
            </div>
        @endif
        
        @foreach($areaMachineChunks as $chunkIndex => $chunk)
            @include('reports.pdf._header')
            
            {{-- Numbered by what is actually on the form. When only this
                 section is printed - the เครื่องจักร/พื้นที่ report - a
                 hardcoded "2." read as though section 1 had gone missing. --}}
            <div class="section-title">{{ count($employeeChunks) > 0 ? '2' : '1' }}. รายงานการทวนสอบการทำความสะอาดประจำวันของพื้นที่และเครื่องจักร (Area &amp; Equipment Verification)</div>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 5%">ลำดับ</th>
                        <th rowspan="2" style="width: 40%">พื้นที่ / เครื่องจักร (Location/Machine)</th>
                        <th rowspan="2" style="width: 8%">แผนก</th>
                        <th colspan="{{ count($areaMachineCheckpoints) }}">รายการตรวจ (Checkpoints)</th>
                        <th rowspan="2" style="width: 18%">ผลการแก้ไข</th>
                        <th rowspan="2" style="width: 12%">หมายเหตุ</th>
                    </tr>
                    <tr>
                        @php $cleanCount = 0; @endphp
                        @foreach($areaMachineCheckpoints as $checkpoint)
                            @php 
                                $displayTitle = $checkpoint->title;
                                $catName = $checkpoint->category ? $checkpoint->category->name : '';
                                if ($catName) {
                                    $displayTitle .= '<br><span style="font-size: 9px; color: #0056b3;">(' . htmlspecialchars($catName) . ')</span>';
                                }
                                if ($checkpoint->description) {
                                    $displayTitle .= '<br><span style="font-size: 8px; color: #666;">(' . htmlspecialchars($checkpoint->description) . ')</span>';
                                }
                            @endphp
                            <th>{!! $displayTitle !!}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($chunk as $data)
                        <tr style="{{ $data['type'] === 'area_header' || $data['type'] === 'area' ? 'background-color: #f9fafb; font-weight: bold;' : '' }}">
                            <td>{{ $data['type'] === 'machine' ? '' : $j++ }}</td>
                            <td class="text-left" style="padding-left: {{ $data['type'] === 'machine' ? '15px' : '2px' }}">
                                @if($data['type'] === 'machine')
                                    - {{ $data['info']->name }}
                                @else
                                    {{ $data['info']->location_name }}
                                @endif
                            </td>
                            <td>{{ $data['session']->department->dept_name ?? '-' }}</td>
                            
                            @foreach($areaMachineCheckpoints as $checkpoint)
                                <td>
                                    @if(isset($data['results'][$checkpoint->id]))
                                        @php $log = $data['results'][$checkpoint->id]; @endphp
                                        @if($log->result === 'pass' || ($log->result === 'fail' && $log->isResolved()))
                                            <span class="text-success">/</span>
                                        @elseif($log->result === 'fail')
                                            <span class="text-danger">X</span>
                                        @else
                                            -
                                        @endif
                                    @else
                                        <span style="color: #ccc;">-</span>
                                    @endif
                                </td>
                            @endforeach

                            @php
                                $corrections = [];
                                $issues = [];
                                foreach($data['results'] as $log) {
                                    $cpTitle = $log->checkpoint->title ?? ($log->checkpoint_title_snapshot ?? 'ไม่ระบุ');
                                    // Shorten title for display in notes
                                    if(str_contains($cpTitle, 'สมบูรณ์')) $cpTitle = 'ความสมบูรณ์';
                                    if(str_contains($cpTitle, 'สะอาด')) $cpTitle = 'ความสะอาด';
                                    
                                    if($log->result === 'fail' || $log->note) {
                                        $issues[] = $cpTitle . ': ' . ($log->note ?: 'ไม่ผ่าน');
                                    }
                                    
                                    // ✅ ใช้ correctiveAction relationship (ตาราง corrective_actions)
                                    if($log->correctiveAction) {
                                        $ca = $log->correctiveAction;
                                        $statusLabel = match($ca->status) {
                                            'resolved', 'closed', 'verified' => 'แก้ไขแล้ว',
                                            'in_progress' => 'กำลังแก้ไข',
                                            default => ucfirst($ca->status)
                                        };
                                        $corrText = $cpTitle . ': ';
                                        if($ca->action_taken) $corrText .= $ca->action_taken;
                                        $corrText .= ' (' . $statusLabel . ')';
                                        $corrections[] = $corrText;
                                    } elseif($log->correction_action) {
                                        // fallback: field เก่าในตาราง inspection_logs
                                        $corrections[] = $cpTitle . ': ' . $log->correction_action . ' (แก้ไขเรียบร้อย)';
                                    }
                                }
                            @endphp
                            
                            <td class="text-left" style="font-size: 8px;">
                                @if(count($corrections) > 0)
                                    {!! implode('<br>', $corrections) !!}
                                @else
                                    <span style="color: #ccc;">-</span>
                                @endif
                            </td>
                            <td class="text-left" style="font-size: 8px;">
                                @if(count($issues) > 0)
                                    {!! implode('<br>', $issues) !!}
                                @else
                                    <span style="color: #ccc;">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @include('reports.pdf._signatures')
            
            @if(!$loop->last)
                <div style="page-break-after: always;"></div>
            @endif
        @endforeach
    @endif
</body>
</html>
