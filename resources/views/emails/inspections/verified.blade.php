@extends('emails.layouts.master')

@section('title', 'Inspection Verified')
@section('header_title', 'ทวนสอบผลเสร็จสิ้น')
@section('header_subtitle', '(Pending Manager Approval)')

@section('content')
<p style="margin-top: 0; font-size: 16px;">เรียน <strong>QA Manager</strong>,</p>
<p>ขณะนี้ <strong>{{ $supervisor->name ?? 'QA Supervisor' }}</strong> ได้ทำการทวนสอบ (Verify) ผลการตรวจหน้างานเรียบร้อยแล้ว และกำลังรอให้ท่านเข้าตรวจสอบภาพรวมและกดอนุมัติ (Approve) เพื่อล็อกเซสชันครับ</p>

<div style="background-color: #f8fafc; border-left: 4px solid #8b5cf6; padding: 25px; border-radius: 6px; margin: 30px 0;">
    <h3 style="margin-top: 0; color: #111827; font-size: 16px;">ข้อมูลเซสชัน</h3>
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
            <td style="padding-bottom: 12px;"><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">รอบการตรวจ:</strong></td>
            <td style="padding-bottom: 12px; color: #1f2937; font-weight: 500;">{{ $session->shift_label }} (Round {{ $session->round }})</td>
        </tr>
        <tr>
            <td><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">วันที่ตรวจ:</strong></td>
            <td style="color: #1f2937; font-weight: 500;">{{ \Carbon\Carbon::parse($session->inspection_date)->format('d/m/Y') }}</td>
        </tr>
    </table>
</div>

<div style="text-align: center; margin-top: 40px;">
    <a href="{{ url('/verification?date=' . \Carbon\Carbon::parse($session->inspection_date)->format('Y-m-d')) }}" style="display: inline-block; padding: 12px 24px; background-color: #8b5cf6; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px;">เข้าสู่ระบบเพื่ออนุมัติ (Approve)</a>
</div>
@endsection
