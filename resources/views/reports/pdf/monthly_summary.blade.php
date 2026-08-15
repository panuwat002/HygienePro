<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>รายงานประจำเดือน</title>
    <style>
        @page { margin: 10px 20px; }
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
            font-size: 11px;
            color: #1e293b;
            background-color: #ffffff;
            line-height: 1.1;
            margin: 0;
            padding: 0;
        }
        
        .header { 
            text-align: center; 
            margin-bottom: 8px; 
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0; 
        }
        .header h1 { 
            font-size: 16px; 
            font-weight: bold; 
            margin: 0;
            color: #0f172a;
        }
        .header p { 
            font-size: 11px; 
            margin: 0; 
            color: #64748b; 
        }
        
        /* Banner Style Score Box */
        .score-banner {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 4px 10px;
            margin-bottom: 6px;
            text-align: center;
        }
        .score-banner .score-title { 
            font-size: 14px; 
            color: #475569; 
            font-weight: bold;
            margin-right: 8px;
        }
        .score-banner .score { 
            font-size: 24px; 
            font-weight: bold; 
            color: {{ $healthScore >= 90 ? '#10b981' : ($healthScore >= 75 ? '#f59e0b' : '#ef4444') }}; 
            vertical-align: middle;
        }
        .score-banner p { 
            margin: 2px 0 0 0;
            font-size: 11px; 
            color: #64748b; 
        }
        
        .section-title { 
            font-size: 12px; 
            font-weight: bold; 
            color: #0f172a; 
            border-left: 3px solid #3b82f6; 
            padding-left: 5px; 
            margin-bottom: 2px; 
        }
        
        table.data-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 6px; 
        }
        table.data-table th, table.data-table td { 
            padding: 1px 4px; 
            border-bottom: 1px solid #f1f5f9; 
            text-align: left; 
        }
        table.data-table th { 
            background-color: #f8fafc; 
            font-weight: bold; 
            color: #475569; 
            font-size: 10px;
        }
        table.data-table td {
            font-size: 10px;
        }
        table.data-table tbody tr:nth-child(even) {
            background-color: #fcfcfc;
        }

        .bar-wrapper {
            width: 100%;
            background-color: #f1f5f9;
            border-radius: 2px;
            height: 6px;
            overflow: hidden;
            margin-top: 1px;
        }
        .bar-fill {
            height: 100%;
            background-color: #f59e0b;
            border-radius: 2px;
        }
        
        .footer { 
            position: fixed; 
            bottom: -10px; 
            left: 0; 
            width: 100%; 
            font-size: 9px; 
            color: #94a3b8; 
            text-align: right; 
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>รายงานสรุปสถิติการตรวจสอบสุขลักษณะ (Monthly Summary)</h1>
        <p>ประจำเดือน: {{ \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y') }} &nbsp;|&nbsp; เป้าหมาย: {{ $targetType == 'person' ? 'พนักงาน' : 'เครื่องจักร / พื้นที่' }}</p>
    </div>

    <!-- SCORE BANNER -->
    <div class="score-banner">
        <div>
            <span class="score-title">คะแนนความสมบูรณ์โดยรวม (Health Score):</span>
            <span class="score">{{ $healthScore }}%</span>
        </div>
        <p>ตรวจสอบทั้งหมด <b>{{ number_format($totalInspections) }}</b> รายการประเมิน &nbsp;|&nbsp; พบข้อบกพร่อง <b>{{ number_format($totalFails) }}</b> รายการ</p>
    </div>

    <!-- TOP DEFECTS (TABLE 1) -->
    <div class="section-title">สัดส่วนปัญหาที่พบมากที่สุด (Top Defects)</div>
    @if(count($topDefects) > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 40%;">รายการข้อบกพร่อง</th>
                    <th style="width: 60%;">สัดส่วน</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $maxDefect = count($topDefects) > 0 ? max($topDefects) : 1;
                @endphp
                @foreach($topDefects as $title => $count)
                <tr>
                    <td style="color: #334155;">{{ $title }} <span style="color: #ef4444; font-weight: bold; font-size: 16px;">({{ $count }})</span></td>
                    <td>
                        @php $barWidth = ($maxDefect > 0) ? ($count / $maxDefect) * 100 : 0; @endphp
                        <div class="bar-wrapper">
                            <div class="bar-fill" style="width: {{ $barWidth }}%; background-color: #ef4444;"></div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="text-align: center; color: #64748b; padding: 20px; background-color: #f8fafc; border-radius: 8px; margin-bottom: 25px;">ไม่พบปัญหาในเดือนนี้ 🎉</div>
    @endif

    <!-- TOP OFFENDERS (TABLE 2) -->
    <div class="section-title">Top 5 ผู้ที่ต้องเฝ้าระวัง (Top Offenders)</div>
    @if(!empty($topOffenders) && count($topOffenders) > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">#</th>
                    <th style="width: 35%;">ชื่อเป้าหมาย ({{ $targetType == 'person' ? 'พนักงาน' : 'เครื่องจักร/พื้นที่' }})</th>
                    <th style="width: 15%; text-align: center;">ไม่ผ่าน (ครั้ง)</th>
                    <th style="width: 45%;">สัดส่วนความถี่</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $i = 1; 
                    $maxOffender = count($topOffenders) > 0 ? max($topOffenders) : 1;
                @endphp
                @foreach($topOffenders as $name => $count)
                <tr>
                    <td style="text-align: center; color: #64748b;">{{ $i++ }}</td>
                    <td style="font-weight: bold; color: #334155;">{{ $name }}</td>
                    <td style="text-align: center; font-weight: bold; color: #ef4444;">{{ $count }}</td>
                    <td>
                        @php $barWidth = ($maxOffender > 0) ? ($count / $maxOffender) * 100 : 0; @endphp
                        <div class="bar-wrapper">
                            <div class="bar-fill" style="width: {{ $barWidth }}%;"></div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="text-align: center; color: #64748b; padding: 20px; background-color: #f8fafc; border-radius: 8px;">ไม่พบผู้ที่กระทำผิดในเดือนนี้ 🎉</p>
    @endif

    <div class="footer">
        พิมพ์เมื่อ: {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; HygienePro System
    </div>
</body>
</html>
