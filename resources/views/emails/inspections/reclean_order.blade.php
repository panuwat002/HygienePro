@extends('emails.layouts.master')

@section('title', 'Order Re-clean')
@section('header_title', 'สั่งแก้ไขความสะอาด')
@section('header_subtitle', '(Order Re-clean)')

@section('content')
<p style="margin-top: 0; font-size: 16px;">เรียน <strong>ผู้จัดการแผนก {{ $session->department->dept_name ?? 'รวมทุกแผนก' }}</strong>,</p>
<p>มีการตรวจสอบพบข้อบกพร่องและ <strong>"สั่งแก้ไขใหม่ (Re-clean)"</strong> โดยผู้ตรวจสอบ มีรายละเอียดดังนี้:</p>

<div style="background-color: #fff1f2; border-left: 4px solid #ef4444; padding: 25px; border-radius: 6px; margin: 30px 0;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="padding-bottom: 12px; width: 40%;"><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">วันที่:</strong></td>
            <td style="padding-bottom: 12px; color: #1f2937; font-weight: 500;">{{ \Carbon\Carbon::parse($session->inspection_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td style="padding-bottom: 12px;"><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">กะ/รอบ:</strong></td>
            <td style="padding-bottom: 12px; color: #1f2937; font-weight: 500;">{{ ucfirst($session->shift) }} (Round {{ $session->round }})</td>
        </tr>
        <tr>
            <td style="padding-bottom: 12px;"><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">ผู้ตรวจสอบ:</strong></td>
            <td style="padding-bottom: 12px; color: #1f2937; font-weight: 500;">{{ Auth::check() ? Auth::user()->name : 'System' }}</td>
        </tr>
        <tr>
            <td><strong style="color: #6b7280; font-size: 14px; text-transform: uppercase;">บันทึกเพิ่มเติม:</strong></td>
            <td style="color: #1f2937; font-weight: 500;">{{ $comment ?? '-' }}</td>
        </tr>
    </table>
</div>

<h3 style="color: #111827; margin-top: 30px; margin-bottom: 15px; font-size: 16px;">รายการที่ต้องแก้ไข:</h3>
<table width="100%" cellpadding="12" cellspacing="0" border="0" style="border-collapse: collapse; text-align: left; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden;">
    <thead>
        <tr style="background-color: #f9fafb; border-bottom: 2px solid #e5e7eb;">
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">จุดที่ต้องแก้ไข</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">ผู้รับผิดชอบ/พื้นที่</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">ปัญหาที่พบ</th>
        </tr>
    </thead>
    <tbody>
        @foreach($logs as $log)
        <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="font-weight: 500; color: #1f2937;">{{ $log->checkpoint->title ?? 'N/A' }}</td>
            <td style="color: #4b5563;">{{ $log->employee->fullname ?? ($log->location->location_name ?? '-') }}</td>
            <td style="color: #ef4444;">{{ $log->correction_action ?? '-' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<p style="margin-top: 30px; color: #4b5563;">กรุณาดำเนินการแก้ไขและแจ้งให้ผู้ตรวจสอบทำการทวนสอบซ้ำอีกครั้ง</p>

<div style="text-align: center; margin-top: 40px;">
    <a href="{{ route('inspection.dashboard', $session->type) }}" style="display: inline-block; padding: 12px 24px; background-color: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px;">ดูรายละเอียด (View Dashboard)</a>
</div>
@endsection
