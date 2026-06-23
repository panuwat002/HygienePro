<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inspection Session Started</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #2563eb;">เริ่มการตรวจสอบ (Inspection Started)</h2>
    
    <p>เรียน QA Supervisor,</p>
    
    <p>ระบบขอแจ้งให้ทราบว่าขณะนี้มีการเริ่มเปิดเซสชันการตรวจสอบหน้างานในระบบ HygienePro รายละเอียดดังนี้:</p>
    
    <div style="background-color: #f3f4f6; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <ul style="list-style: none; padding: 0; margin: 0;">
            <li style="margin-bottom: 8px;"><strong>ประเภทการตรวจ:</strong> {{ ucfirst($session->type) }}</li>
            <li style="margin-bottom: 8px;"><strong>แผนกเป้าหมาย:</strong> {{ $session->department->dept_name ?? 'รวมทุกแผนก' }}</li>
            <li style="margin-bottom: 8px;"><strong>รอบการตรวจ (กะ):</strong> {{ ucfirst($session->shift) }} (Round {{ $session->round }})</li>
            <li style="margin-bottom: 8px;"><strong>ผู้ตรวจ (Inspector):</strong> {{ $session->inspector->name ?? 'Unknown' }}</li>
            <li><strong>เวลาเริ่มตรวจ:</strong> {{ $session->created_at->format('d/m/Y H:i:s') }}</li>
        </ul>
    </div>
    
    <p>ระบบจะแจ้งเตือนให้ท่านทราบอีกครั้งเมื่อเจ้าหน้าที่ทำการตรวจเสร็จสิ้น เพื่อให้ท่านเข้าไปทวนสอบ (Verify) ผลการตรวจต่อไป</p>
    
    <br>
    <p style="font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 10px;">
        นี่คืออีเมลอัตโนมัติจากระบบ HygienePro กรุณาอย่าตอบกลับอีเมลนี้
    </p>
</body>
</html>
