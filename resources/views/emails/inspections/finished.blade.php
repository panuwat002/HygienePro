<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inspection Session Finished</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #10b981;">ตรวจเสร็จสิ้นรอทวนสอบ (Inspection Finished)</h2>
    
    <p>เรียน QA Supervisor,</p>
    
    <p>ขณะนี้เจ้าหน้าที่ได้ทำการตรวจสอบหน้างานเสร็จสิ้นแล้ว และระบบได้บันทึกสถานะเป็น <strong>รอทวนสอบ (Pending Verification)</strong> รายละเอียดการตรวจมีดังนี้:</p>
    
    <div style="background-color: #f3f4f6; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h3 style="margin-top: 0; color: #374151;">ข้อมูลการตรวจสอบ</h3>
        <ul style="list-style: none; padding: 0; margin: 0;">
            <li style="margin-bottom: 8px;"><strong>ประเภทการตรวจ:</strong> {{ ucfirst($session->type) }}</li>
            <li style="margin-bottom: 8px;"><strong>แผนกที่ไปตรวจ:</strong> {{ $session->department->dept_name ?? 'รวมทุกแผนก' }}</li>
            <li style="margin-bottom: 8px;"><strong>ผู้ตรวจ:</strong> {{ $session->inspector->name ?? 'Unknown' }}</li>
            <li style="margin-bottom: 8px;"><strong>เวลาที่เริ่ม:</strong> {{ $session->created_at->format('d/m/Y H:i:s') }}</li>
            <li><strong>เวลาที่จบ:</strong> {{ $session->updated_at->format('d/m/Y H:i:s') }}</li>
        </ul>
    </div>

    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h3 style="margin-top: 0; color: #374151;">สรุปผลการตรวจ (Preliminary Stats)</h3>
        <ul style="list-style: none; padding: 0; margin: 0;">
            <li style="margin-bottom: 8px;"><strong>จำนวนจุดที่ตรวจทั้งหมด:</strong> {{ $stats['total'] }} จุด</li>
            <li style="margin-bottom: 8px; color: #059669;"><strong>ผ่าน (Pass):</strong> {{ $stats['pass'] }} จุด</li>
            <li style="color: #dc2626;"><strong>ไม่ผ่าน (Fail):</strong> {{ $stats['fail'] }} จุด</li>
        </ul>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ url('/verification?date=' . $session->inspection_date) }}" 
           style="background-color: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
           เข้าสู่ระบบเพื่อทวนสอบผล (Verify)
        </a>
    </div>
    
    <br>
    <p style="font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 10px;">
        นี่คืออีเมลอัตโนมัติจากระบบ HygienePro กรุณาอย่าตอบกลับอีเมลนี้
    </p>
</body>
</html>
