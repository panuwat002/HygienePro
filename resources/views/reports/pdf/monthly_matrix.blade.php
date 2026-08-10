<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { margin: 10px 15px; }
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
            font-size: 12px;
            line-height: 1;
        }
        .header { text-align: center; margin-bottom: 10px; }
        .header h1 { font-size: 18px; font-weight: bold; margin: 0; }
        .header p { margin: 2px 0 10px 0; font-size: 14px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #000; padding: 2px; text-align: center; vertical-align: middle; height: 16px; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .col-name { width: 120px; text-align: left; padding-left: 5px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }
        .col-day { width: 20px; }
        
        .pass { color: #16a34a; font-weight: bold; }
        .fail { color: #dc2626; font-weight: bold; }
        .empty { color: #ccc; }
        
        .footer { position: fixed; bottom: -5px; left: 0; width: 100%; font-size: 10px; color: gray; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ตารางรายงานผลการตรวจสอบประจำเดือน (Monthly Inspection Matrix)</h1>
        <p>ประจำเดือน: {{ \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y') }} | เป้าหมาย: {{ $targetType == 'person' ? 'บุคลากร' : ($targetType == 'machine' ? 'เครื่องจักร' : 'พื้นที่') }}</p>
    </div>

    @if(count($matrix) > 0)
        <table>
            <thead>
                <tr>
                    <th class="col-name" rowspan="2">ชื่อเป้าหมาย</th>
                    <th colspan="{{ $daysInMonth }}">วันที่ตรวจสอบ</th>
                </tr>
                <tr>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        <th class="col-day">{{ $d }}</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @foreach($matrix as $row)
                <tr>
                    <td class="col-name">{{ $row['name'] }}</td>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        @php $val = $row['days'][$d]; @endphp
                        <td>
                            @if($val === 'pass')
                                <span class="pass">/</span>
                            @elseif($val === 'fail')
                                <span class="fail">X</span>
                            @else
                                <span class="empty">-</span>
                            @endif
                        </td>
                    @endfor
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="text-align: center; padding: 20px; font-size: 16px;">ไม่พบข้อมูลการตรวจสอบในเดือนนี้</p>
    @endif

    <div class="footer">
        พิมพ์เมื่อ: {{ now()->format('d/m/Y H:i') }} | HygienePro System
    </div>
</body>
</html>
