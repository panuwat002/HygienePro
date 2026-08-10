@extends('emails.layouts.master')

@section('title', 'Inspection Session Started')
@section('header_title', 'เริ่มการตรวจสอบ')
@section('header_subtitle', '(Inspection Started)')

@section('content')
<p style="margin-top: 0; font-size: 16px;">เรียน <strong>QA Supervisor</strong>,</p>
<p>ระบบขอแจ้งให้ทราบว่าขณะนี้มีการเริ่มเปิดเซสชันการตรวจสอบหน้างานในระบบ <strong>HygienePro</strong> โดยมีรายละเอียดดังนี้:</p>

<div style="background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 25px; border-radius: 6px; margin: 30px 0;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="padding-bottom: 12px; width: 40%;"><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">ประเภทการตรวจ:</strong></td>
            <td style="padding-bottom: 12px; color: #1f2937; font-weight: 500;">{{ ucfirst($session->type) }}</td>
        </tr>
        <tr>
            <td style="padding-bottom: 12px;"><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">แผนกเป้าหมาย:</strong></td>
            <td style="padding-bottom: 12px; color: #1f2937; font-weight: 500;">{{ $session->department->dept_name ?? 'รวมทุกแผนก' }}</td>
        </tr>
        <tr>
            <td style="padding-bottom: 12px;"><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">รอบการตรวจ (กะ):</strong></td>
            <td style="padding-bottom: 12px; color: #1f2937; font-weight: 500;">{{ ucfirst($session->shift) }} (Round {{ $session->round }})</td>
        </tr>
        <tr>
            <td style="padding-bottom: 12px;"><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">ผู้ตรวจ (Inspector):</strong></td>
            <td style="padding-bottom: 12px; color: #1f2937; font-weight: 500;">{{ $session->inspector->name ?? 'Unknown' }}</td>
        </tr>
        <tr>
            <td><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">เวลาเริ่มตรวจ:</strong></td>
            <td style="color: #1f2937; font-weight: 500;">{{ $session->created_at->format('d/m/Y H:i:s') }}</td>
        </tr>
    </table>
</div>

<p style="margin-bottom: 0; color: #4b5563;">ระบบจะแจ้งเตือนให้ท่านทราบอีกครั้งเมื่อเจ้าหน้าที่ทำการตรวจเสร็จสิ้น เพื่อให้ท่านเข้าไปทวนสอบ (Verify) ผลการตรวจต่อไป</p>
@endsection
