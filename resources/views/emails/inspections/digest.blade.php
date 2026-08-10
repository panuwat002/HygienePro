@extends('emails.layouts.master')

@section('title', 'Pending Verification Digest')
@section('header_title', 'สรุปรวมงานค้างทวนสอบ')
@section('header_subtitle', '(Pending Verification Digest)')

@section('content')
<p style="margin-top: 0; font-size: 16px;">คุณมีเซสชันการตรวจสอบความสะอาดที่รอการยืนยันผลใหม่ จำนวน <strong>{{ $sessions->count() }}</strong> รายการ ดังต่อไปนี้:</p>

<table width="100%" cellpadding="12" cellspacing="0" border="0" style="border-collapse: collapse; text-align: left; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; margin-top: 20px;">
    <thead>
        <tr style="background-color: #f9fafb; border-bottom: 2px solid #e5e7eb;">
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">กะ (Shift)</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">แผนก</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">ผู้ส่งตรวจ</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">ต้องตรวจเอง 🔍</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">ผ่านออโต้ 🤖</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sessions as $session)
        <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="font-weight: 500; color: #1f2937;">{{ $session->shift }}</td>
            <td style="color: #4b5563;">{{ $session->department->dept_name ?? 'N/A' }}</td>
            <td style="color: #4b5563;">{{ $session->inspector->name ?? 'N/A' }}</td>
            <td style="color: #f59e0b; font-weight: bold;">{{ $session->logs->whereNull('verification_status')->count() }}</td>
            <td style="color: #10b981; font-weight: bold;">{{ $session->logs->where('verification_status', 'auto_verified')->count() }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<p style="margin-top: 30px; color: #4b5563;">กรุณาเข้าสู่ระบบเพื่อตรวจสอบและยืนยันผลการตรวจสอบ</p>

<div style="text-align: center; margin-top: 30px;">
    <a href="{{ route('inspection.verification.dashboard', 'daily') }}" style="display: inline-block; padding: 12px 24px; background-color: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px;">ไปที่หน้าตรวจสอบ (Dashboard)</a>
</div>
@endsection
