@extends('emails.layouts.master')

@section('title', 'New Corrective Action Required')
@section('header_title', 'สั่งแก้ไขด้วยตนเอง')
@section('header_subtitle', '(New Corrective Action Required)')

@section('content')
<p style="margin-top: 0; font-size: 16px;">เรียน <strong>ผู้รับผิดชอบ (Assignee)</strong>,</p>
<p>ระบบแจ้งเตือนว่ามีการ <strong>"สั่งแก้ไขปัญหา"</strong> (Corrective Action) จากผู้ตรวจสอบ กรุณาดำเนินการแก้ไขโดยด่วนครับ</p>

<div style="background-color: #fff1f2; border-left: 4px solid #ef4444; padding: 25px; border-radius: 6px; margin: 30px 0;">
    <h3 style="margin-top: 0; color: #111827; font-size: 16px;">รายละเอียดปัญหา</h3>
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="padding-bottom: 12px; width: 30%;"><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">จุดตรวจ (Issue):</strong></td>
            <td style="padding-bottom: 12px; color: #1f2937; font-weight: 500;">{{ $action->log->checkpoint->title ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding-bottom: 12px;"><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">พื้นที่ (Location):</strong></td>
            <td style="padding-bottom: 12px; color: #1f2937; font-weight: 500;">{{ $action->log->session->department->dept_name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding-bottom: 12px;"><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">ผู้รายงาน (Reported By):</strong></td>
            <td style="padding-bottom: 12px; color: #1f2937; font-weight: 500;">{{ $action->escalator->name ?? 'QA Team' }}</td>
        </tr>
        <tr>
            <td style="padding-bottom: 12px;"><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">รายละเอียด (Details):</strong></td>
            <td style="padding-bottom: 12px; color: #1f2937; font-weight: 500;">{{ $action->root_cause ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">กำหนดเสร็จ (Due By):</strong></td>
            <td style="color: #dc2626; font-weight: bold; font-size: 16px;">{{ $action->due_date ? $action->due_date->format('d/m/Y H:i') : 'ASAP' }}</td>
        </tr>
    </table>
</div>

@if($action->log && $action->log->photo_path)
<div style="text-align: center; margin: 30px 0; padding: 15px; border: 1px dashed #d1d5db; border-radius: 8px;">
    <p style="margin-top: 0; color: #6b7280; font-size: 13px; text-transform: uppercase; margin-bottom: 10px;">ภาพประกอบปัญหา</p>
    <img src="{{ asset('storage/' . $action->log->photo_path) }}" alt="Evidence" style="max-width: 100%; max-height: 300px; border-radius: 4px;">
</div>
@endif

<div style="text-align: center; margin-top: 40px;">
    <a href="{{ route('corrective.index') }}" style="display: inline-block; padding: 12px 24px; background-color: #ef4444; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px;">เข้าสู่ระบบเพื่อดำเนินการ (View & Resolve)</a>
</div>
@endsection
