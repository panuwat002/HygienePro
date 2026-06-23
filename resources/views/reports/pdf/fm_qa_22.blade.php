<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>FM-QA-22 Daily Visual Check Cleaning Equipment Verification</title>
    <style>
        @font-face {
            font-family: 'THSarabunNew';
            font-style: normal;
            font-weight: normal;
            src: url("{{ storage_path('fonts/THSarabunNew.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'THSarabunNew';
            font-style: normal;
            font-weight: bold;
            src: url("{{ storage_path('fonts/THSarabunNew Bold.ttf') }}") format('truetype');
        }
        body {
            font-family: 'THSarabunNew', sans-serif;
            font-size: 14pt;
            line-height: 1.2;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: middle;
        }
        th {
            text-align: center;
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .header {
            text-align: right;
            font-size: 10pt;
            margin-bottom: 10px;
        }
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 18pt;
            margin-bottom: 5px;
        }
        .subtitle {
            text-align: center;
            font-size: 16pt;
            margin-bottom: 15px;
        }
        .info-row {
            margin-bottom: 10px;
        }
        .footer {
            margin-top: 20px;
            font-size: 12pt;
        }
        .page-break {
            page-break-after: always;
        }
        .logo {
            position: absolute;
            top: 0;
            left: 0;
            width: 80px;
        }
        .signature-box {
            display: inline-block;
            width: 30%;
            text-align: center;
            vertical-align: top;
        }
    </style>
</head>
<body>
    @foreach($locationChunks as $chunkIndex => $chunk)
        @foreach($chunk as $locationId => $data)
            <div style="position: relative; min-height: 100px;">
                <!-- Logo (Placeholder if not available) -->
                <!-- <img src="{{ public_path('images/logo.png') }}" class="logo"> -->
                
                <div class="header">
                    FM-QA-22 Rev.01 Effective 1 Sep.2025<br>
                    หน้าที่ (Page) {{ $loop->parent->iteration }}/{{ $loop->parent->count }}
                </div>
                
                <div class="title">รายงานการทวนสอบการทำความสะอาดประจำวันของเครื่องมือ เครื่องจักร อุปกรณ์ในไลน์ผลิต</div>
                <div class="subtitle">Daily Visual check cleaning equipment verification</div>
                
                <div class="info-row">
                    <strong>วันที่ตรวจสอบ (Date) :</strong> {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th width="5%">ลำดับ<br>(No.)</th>
                        <th width="25%">Lists/รายการ</th>
                        <th width="15%">ความสมบูรณ์ของเครื่องมือ<br>เครื่องจักรอุปกรณ์</th>
                        <th width="15%">ความสะอาดของเครื่องมือ<br>เครื่องจักรอุปกรณ์</th>
                        <th width="20%">การแก้ไข</th>
                        <th width="20%">ผลการแก้ไข</th>
                    </tr>
                    <tr style="background-color: #fff;">
                        <td colspan="6" style="font-weight: bold; text-align: left; background-color: #e0e0e0;">
                            พื้นที่ตรวจสอบ (Location) : {{ $data['info']->location_name }}
                        </td>
                    </tr>
                </thead>
                <tbody>
                    @php $i = 1; @endphp
                    @foreach($data['machines'] as $machineId => $machineData)
                        <tr>
                            <td class="text-center">{{ $i++ }}</td>
                            <td>{{ $machineData['info']->name }}</td>
                            
                            <!-- Checkpoint 1: Completeness -->
                            <td class="text-center">
                                @if(isset($machineData['results'][1]))
                                    @if($machineData['results'][1]['result'] === 'pass')
                                        <span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>
                                    @elseif($machineData['results'][1]['result'] === 'fail')
                                        X
                                    @else
                                        -
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                            
                            <!-- Checkpoint 2: Cleanliness -->
                            <td class="text-center">
                                @if(isset($machineData['results'][2]))
                                    @if($machineData['results'][2]['result'] === 'pass')
                                        <span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>
                                    @elseif($machineData['results'][2]['result'] === 'fail')
                                        X
                                    @else
                                        -
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                            
                            <!-- Correction -->
                            <td>
                                @php
                                    $notes = [];
                                    if(isset($machineData['results'][1]) && $machineData['results'][1]['note']) $notes[] = $machineData['results'][1]['note'];
                                    if(isset($machineData['results'][2]) && $machineData['results'][2]['note']) $notes[] = $machineData['results'][2]['note'];
                                @endphp
                                {{ implode(', ', $notes) }}
                            </td>
                            
                            <!-- Correction Result (Assuming resolved if exists or blank) -->
                            <td></td>
                        </tr>
                    @endforeach
                    
                    <!-- Allow empty rows to fill page if needed, or just standard rows -->
                    @for($k = 0; $k < (15 - count($data['machines'])); $k++)
                         <tr>
                            <td class="text-center">{{ $i++ }}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    @endfor
                </tbody>
            </table>

            <div class="footer">
                <div><strong>หมายเหตุ :</strong> 1. ทำการทวนสอบหลังจากการทำความสะอาดแล้ว โดยตรวจสอบทุกวันที่มีการใช้เครื่องมือ เครื่องจักรอุปกรณ์ ที่ระบุในการผลิตสินค้า</div>
                <div style="margin-left: 60px;">2. <span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span> หมายถึง เครื่องมือเครื่องจักรอุปกรณ์สมบูรณ์ สะอาด X หมายถึงไม่สมบูรณ์ ไม่สะอาด กรณีที่พบเครื่องมือ เครื่องจักรอุปกรณ์ไม่สมบูรณ์หรือไม่สะอาด ให้แจ้งหน่วยงานผลิตให้แก้ไขทันที พร้อมทั้งให้พนักงาน QA ระบุวิธีการแก้ไขและผลการตรวจสอบซ้ำลงในช่องการแก้ไขและช่องผลการแก้ไข</div>
                
                <br>
                
                <div style="width: 100%; text-align: center;">
                    <div class="signature-box">
                        Record By : .............................................. (QA Staff)<br>
                        วันที่ (Date) : ..............................................
                    </div>
                    <div class="signature-box">
                        Verified By : .............................................. (QA Supervisor)<br>
                        วันที่ (Date) : ..............................................
                    </div>
                    <div class="signature-box">
                        Approved By : .............................................. (QA Manager)<br>
                        วันที่ (Date) : ..............................................
                    </div>
                </div>
            </div>

            @if(!$loop->last || !$loop->parent->last)
                <div class="page-break"></div>
            @endif
        @endforeach
    @endforeach
</body>
</html>
