<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #dc3545;
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .stats-box {
            background-color: #fff3cd;
            border: 1px solid #ffe69c;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .stats-box .rate {
            font-size: 32px;
            font-weight: bold;
            color: #b02a37;
        }
        .footer {
            background-color: #f1f1f1;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        .btn {
            display: inline-block;
            background-color: #0d6efd;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>แจ้งเตือนด่วน: สุ่มตรวจไม่ผ่านเกณฑ์</h1>
        </div>
        
        <div class="content">
            <p>เรียน ผู้จัดการแผนก และ QA Manager,</p>
            
            <p>ระบบตรวจพบว่าผลการสุ่มตรวจ (Random Audit) ประจำสัปดาห์ ของ <strong>แผนก {{ $session->department->dept_name ?? 'N/A' }}</strong> มีอัตราพนักงานไม่ผ่านเกณฑ์สุขลักษณะเกินกำหนด (20%)</p>
            
            <div class="stats-box">
                <div>อัตราไม่ผ่านเกณฑ์ (Fail Rate)</div>
                <div class="rate">{{ number_format($failRate, 1) }}%</div>
                <div style="margin-top: 10px;">พนักงานไม่ผ่าน {{ $failedEmployeesCount }} คน จากที่สุ่มตรวจ {{ $totalInspected }} คน</div>
            </div>
            
            <h3>รายละเอียดการตรวจ</h3>
            <ul>
                <li><strong>ผู้ตรวจ (QA Sup):</strong> {{ $session->inspector->name ?? 'N/A' }}</li>
                <li><strong>กะการทำงาน:</strong> {{ $session->shift_label }}</li>
                <li><strong>วันที่ตรวจ:</strong> {{ $session->inspection_date->format('d/m/Y') }}</li>
            </ul>
            
            <p><strong>การดำเนินการอัตโนมัติ:</strong><br>
            ระบบได้ทำการสร้างรอบตรวจ <strong>Re-check (ตรวจซ้ำทั้งหมด)</strong> สำหรับพนักงานทุกคนในแผนกนี้เรียบร้อยแล้ว กรุณาแจ้งให้ QA เข้าตรวจสอบรอบ Re-check โดยเร็วที่สุด</p>
            
            <div style="text-align: center;">
                <a href="{{ url('/inspection/dashboard/' . $session->type) }}" class="btn">เข้าสู่ระบบเพื่อตรวจสอบ</a>
            </div>
        </div>
        
        <div class="footer">
            ข้อความนี้ถูกส่งโดยระบบอัตโนมัติ (HygienePro System)<br>
            กรุณาอย่าตอบกลับอีเมลนี้
        </div>
    </div>
</body>
</html>
