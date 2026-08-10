<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { margin: 20px 30px; }
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
            font-size: 16px;
            color: #333;
        }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h1 { font-size: 24px; font-weight: bold; margin: 0; }
        .header p { font-size: 16px; margin: 5px 0 0 0; color: #555; }
        
        .score-box {
            text-align: center;
            padding: 15px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .score-box h2 { margin: 0; font-size: 18px; color: #475569; }
        .score-box .score { font-size: 36px; font-weight: bold; color: {{ $healthScore >= 90 ? '#16a34a' : ($healthScore >= 75 ? '#ca8a04' : '#dc2626') }}; margin: 5px 0; }
        .score-box p { margin: 0; font-size: 14px; color: #64748b; }
        
        .section { margin-bottom: 25px; }
        .section-title { font-size: 18px; font-weight: bold; color: #0f172a; border-left: 4px solid #3b82f6; padding-left: 8px; margin-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background-color: #f1f5f9; font-weight: bold; color: #334155; }
        
        .bar-container { width: 100%; background-color: #e2e8f0; border-radius: 4px; height: 12px; overflow: hidden; margin-top: 4px; }
        .bar { height: 100%; background-color: #ef4444; }
        
        .footer { position: fixed; bottom: -10px; left: 0; width: 100%; font-size: 12px; color: #94a3b8; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>รายงานสรุปสถิติการตรวจสอบสุขลักษณะ (Monthly Summary Report)</h1>
        <p>ประจำเดือน: {{ \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y') }} | เป้าหมาย: {{ $targetType == 'person' ? 'พนักงาน' : 'เครื่องจักร / พื้นที่' }}</p>
    </div>

    <div class="score-box">
        <h2>คะแนนความสมบูรณ์โดยรวม (Health Score)</h2>
        <div class="score">{{ $healthScore }}%</div>
        <p>ตรวจสอบทั้งหมด {{ number_format($totalInspections) }} จุด | พบข้อบกพร่อง {{ number_format($totalFails) }} จุด</p>
    </div>

    <div class="section">
        <div class="section-title">Top 5 ปัญหาที่พบมากที่สุด (Top Defects)</div>
        @if(count($topDefects) > 0)
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">ลำดับ</th>
                        <th style="width: 50%;">รายการข้อบกพร่อง</th>
                        <th style="width: 15%; text-align: center;">จำนวน (ครั้ง)</th>
                        <th style="width: 30%;">สัดส่วนความถี่</th>
                    </tr>
                </thead>
                <tbody>
                    @php 
                        $i = 1; 
                        $maxDefect = max($topDefects);
                    @endphp
                    @foreach($topDefects as $title => $count)
                    <tr>
                        <td style="text-align: center;">{{ $i++ }}</td>
                        <td>{{ $title }}</td>
                        <td style="text-align: center; font-weight: bold; color: #dc2626;">{{ $count }}</td>
                        <td>
                            <div class="bar-container">
                                <div class="bar" style="width: {{ ($count / $maxDefect) * 100 }}%;"></div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="text-align: center; color: #64748b; padding: 20px;">ไม่พบข้อมูลข้อบกพร่องในเดือนนี้ 🎉</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">Top 5 ผู้ที่ต้องเฝ้าระวัง (Top Offenders)</div>
        @if(count($topOffenders) > 0)
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">ลำดับ</th>
                        <th style="width: 50%;">ชื่อเป้าหมาย ({{ $targetType == 'person' ? 'พนักงาน' : 'เครื่องจักร / พื้นที่' }})</th>
                        <th style="width: 15%; text-align: center;">จำนวนไม่ผ่าน (ครั้ง)</th>
                        <th style="width: 30%;">สัดส่วนความถี่</th>
                    </tr>
                </thead>
                <tbody>
                    @php 
                        $i = 1; 
                        $maxOffender = max($topOffenders);
                    @endphp
                    @foreach($topOffenders as $name => $count)
                    <tr>
                        <td style="text-align: center;">{{ $i++ }}</td>
                        <td>{{ $name }}</td>
                        <td style="text-align: center; font-weight: bold; color: #dc2626;">{{ $count }}</td>
                        <td>
                            <div class="bar-container">
                                <div class="bar" style="background-color: #f59e0b; width: {{ ($count / $maxOffender) * 100 }}%;"></div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="text-align: center; color: #64748b; padding: 20px;">ไม่พบผู้ที่กระทำผิดในเดือนนี้ 🎉</p>
        @endif
    </div>

    <div class="footer">
        พิมพ์เมื่อ: {{ now()->format('d/m/Y H:i') }} | HygienePro System
    </div>
</body>
</html>
