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
            border-collapse: collapse;
            font-size: 9px; /* Reduced to 9px */
            margin-bottom: 2px;
            page-break-inside: auto;
        }
        th, td {
            border: 1px solid #000;
            padding: 0px 2px; /* Zero vertical padding */
            text-align: center;
            vertical-align: middle;
            height: 14px; /* Reduced to 14px */
            overflow: hidden; /* visual fix */
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
            height: 18px; /* Reduced header height */
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

    @if(empty($employeeChunks) && empty($machineChunks) && empty($areaChunks))
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
                        <th rowspan="2" style="width: 5%">ลำดับ</th>
                        <th rowspan="2" style="width: 15%">ชื่อ - สกุล</th>
                        <th rowspan="2" style="width: 8%">แผนก</th>
                        <th rowspan="2" style="width: 7%">กะ</th>
                        <th colspan="{{ count($personCheckpoints) }}">รายการตรวจ (Checkpoints)</th>
                        <th rowspan="2" style="width: 10%">หมายเหตุ</th>
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
                                $shiftName = strtolower($data['session']->shift ?? $data['info']->shift->shift_name ?? '-');
                                $thaiShift = match($shiftName) {
                                    'morning' => 'กะเช้า',
                                    'afternoon' => 'กะบ่าย',
                                    'night' => 'กะดึก',
                                    default => $shiftName,
                                };
                            @endphp
                            <td>{{ $thaiShift }}</td>
                            
                            @foreach($personCheckpoints as $checkpoint)
                                <td>
                                    @if(isset($data['results'][$checkpoint->id]))
                                        @php $log = $data['results'][$checkpoint->id]; @endphp
                                        @if($log->result === 'pass')
                                            <span class="text-success">/</span>
                                        @elseif($log->result === 'fail')
                                            <span class="text-danger">X</span>
                                        @else
                                            -
                                        @endif
                                    @else
                                        <span style="color: #eee;">-</span>
                                    @endif
                                </td>
                            @endforeach

                            <td class="text-left">
                                @php
                                    $issues = [];
                                    foreach($data['results'] as $log) {
                                        if($log->result === 'fail' && $log->note) {
                                            $issues[] = $log->checkpoint->title . ': ' . $log->note;
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
                         But usually simple list is fine. I'll skip filling rows to simplify, unless visually requested.
                         Original had fill rows. I'll add them if it's the last page? 
                         Let's keep it simple first.
                    --}}
                </tbody>
            </table>

            @include('reports.pdf._signatures')

            @if(!($loop->last && empty($machineChunks) && empty($areaChunks)))
                <div style="page-break-after: always;"></div>
            @endif
        @endforeach

        {{-- MACHINES --}}
        @if(count($machineChunks) > 0)
            <div class="footer">
                <div class="footer-text">FM-QA-22 Rev.01 Effective 1 Sep.2025</div>
            </div>
        @endif
        
        @foreach($machineChunks as $chunkIndex => $chunk)
            @include('reports.pdf._header')
            
            <div class="section-title">2. รายงานการทวนสอบการทำความสะอาดประจำวันของเครื่องมือ เครื่องจักร อุปกรณ์ในไลน์ผลิต/ Daily Visual check cleaning equipment verification</div>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 5%">ลำดับ</th>
                        <th rowspan="2" style="width: 40%">เครื่องจักร</th>
                        <th rowspan="2" style="width: 8%">แผนก</th>
                        <th colspan="{{ count($machineCheckpoints) }}">รายการตรวจ (Checkpoints)</th>
                        <th rowspan="2" style="width: 10%">การแก้ไข</th>
                        <th rowspan="2" style="width: 10%">ผลการแก้ไข</th>
                        <th rowspan="2" style="width: 10%">หมายเหตุ</th>
                    </tr>
                    <tr>
                        @foreach($machineCheckpoints as $checkpoint)
                            <th>{{ $checkpoint->title }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($chunk as $machineId => $data)
                        <tr>
                            <td>{{ $j++ }}</td>
                            <td class="text-left">{{ $data['info']->name }}</td>
                            <td>{{ $data['session']->department->dept_name ?? '-' }}</td>
                            
                            @foreach($machineCheckpoints as $checkpoint)
                                <td>
                                    @if(isset($data['results'][$checkpoint->id]))
                                        @php $log = $data['results'][$checkpoint->id]; @endphp
                                        @if($log->result === 'pass')
                                            <span class="text-success">/</span>
                                        @elseif($log->result === 'fail')
                                            <span class="text-danger">X</span>
                                        @else
                                            -
                                        @endif
                                    @else
                                        <span style="color: #eee;">-</span>
                                    @endif
                                </td>
                            @endforeach

                            <td>-</td>
                            <td>-</td>
                            <td class="text-left">
                                @php
                                    $issues = [];
                                    foreach($data['results'] as $log) {
                                        if($log->result === 'fail' && $log->note) {
                                            $issues[] = $log->checkpoint->title . ': ' . $log->note;
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
                </tbody>
            </table>

            @include('reports.pdf._signatures')
            
            @if(!($loop->last && empty($areaChunks)))
                <div style="page-break-after: always;"></div>
            @endif
        @endforeach

        {{-- AREAS --}}
        @if(count($areaChunks) > 0)
            <div class="footer">
                <div class="footer-text">FM-QA-22 Rev.01 Effective 1 Sep.2025</div>
            </div>
        @endif
        
        @foreach($areaChunks as $chunkIndex => $chunk)
            @include('reports.pdf._header')
            
            <div class="section-title">3. สุขลักษณะพื้นที่ทั่วไป (General Area Hygiene)</div>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 5%">ลำดับ</th>
                        <th rowspan="2" style="width: 40%">พื้นที่ (Location)</th>
                        <th rowspan="2" style="width: 8%">แผนก</th>
                        <th colspan="{{ count($areaCheckpoints) }}">รายการตรวจ (Checkpoints)</th>
                        <th rowspan="2" style="width: 10%">การแก้ไข</th>
                        <th rowspan="2" style="width: 10%">ผลการแก้ไข</th>
                        <th rowspan="2" style="width: 10%">หมายเหตุ</th>
                    </tr>
                    <tr>
                        @foreach($areaCheckpoints as $checkpoint)
                            <th>{{ $checkpoint->title }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($chunk as $locationId => $data)
                        <tr>
                            <td>{{ $m++ }}</td>
                            <td class="text-left">{{ $data['info']->location_name }}</td>
                            <td>{{ $data['session']->department->dept_name ?? '-' }}</td>
                            
                            @foreach($areaCheckpoints as $checkpoint)
                                <td>
                                    @if(isset($data['results'][$checkpoint->id]))
                                        @php $log = $data['results'][$checkpoint->id]; @endphp
                                        @if($log->result === 'pass')
                                            <span class="text-success">/</span>
                                        @elseif($log->result === 'fail')
                                            <span class="text-danger">X</span>
                                        @else
                                            -
                                        @endif
                                    @else
                                        <span style="color: #eee;">-</span>
                                    @endif
                                </td>
                            @endforeach

                            <td>-</td>
                            <td>-</td>
                            <td class="text-left">
                                @php
                                    $issues = [];
                                    foreach($data['results'] as $log) {
                                        if($log->result === 'fail' && $log->note) {
                                            $issues[] = $log->checkpoint->title . ': ' . $log->note;
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
