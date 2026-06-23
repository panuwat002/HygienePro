<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inspection Verified</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #8b5cf6;">ทวนสอบผลเสร็จสิ้น รออนุมัติ (Pending Manager Approval)</h2>
    
    <p>เรียน QA Manager,</p>
    
    <p>ขณะนี้ <strong>{{ $supervisor->name }}</strong> (QA Supervisor) ได้ทำการทวนสอบ (Verify) ผลการตรวจหน้างานเรียบร้อยแล้ว และกำลังรอให้ท่านเข้าตรวจสอบภาพรวมและกดอนุมัติ (Approve) เพื่อล็อกเซสชันครับ</p>
    
    <div style="background-color: #f3f4f6; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h3 style="margin-top: 0; color: #374151;">ข้อมูลเซสชัน</h3>
        <ul style="list-style: none; padding: 0; margin: 0;">
            <li style="margin-bottom: 8px;"><strong>ประเภทการตรวจ:</strong> {{ ucfirst($session->type) }}</li>
            <li style="margin-bottom: 8px;"><strong>แผนกที่ไปตรวจ:</strong> {{ $session->department->dept_name ?? 'รวมทุกแผนก' }}</li>
            <li style="margin-bottom: 8px;"><strong>รอบการตรวจ:</strong> {{ ucfirst($session->shift) }} (Round {{ $session->round }})</li>
            <li><strong>วันที่ตรวจ:</strong> {{ \Carbon\Carbon::parse($session->inspection_date)->format('d/m/Y') }}</li>
        </ul>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ url('/verification?date=' . $session->inspection_date) }}" 
           style="background-color: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
           เข้าสู่ระบบเพื่ออนุมัติ (Approve)
        </a>
    </div>
    
    <br>
    <p style="font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 10px;">
        นี่คืออีเมลอัตโนมัติจากระบบ HygienePro กรุณาอย่าตอบกลับอีเมลนี้
    </p>
</body>
</html>
