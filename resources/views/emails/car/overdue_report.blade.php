@extends('emails.layouts.master')

@section('title', 'Daily Overdue CAR Report')
@section('header_title', 'รายงานงานค้างเกินกำหนด')
@section('header_subtitle', '(Daily Overdue CAR Report)')

@section('content')
<p style="margin-top: 0; font-size: 16px;">เรียน <strong>ผู้จัดการที่เกี่ยวข้อง</strong>,</p>
<p>ระบบตรวจสอบพบว่ามีรายการสั่งแก้ไขความสะอาด (Corrective Action) ที่ <strong>เกินกำหนดเวลาแก้ไข</strong> จำนวน {{ $actions->count() }} รายการ ดังนี้:</p>

<table width="100%" cellpadding="12" cellspacing="0" border="0" style="border-collapse: collapse; text-align: left; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; margin-top: 20px;">
    <thead>
        <tr style="background-color: #f9fafb; border-bottom: 2px solid #e5e7eb;">
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">ปัญหา (Issue)</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">แผนก (Dept)</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">ผู้รับผิดชอบ (Assignee)</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">กำหนดเสร็จ (Due)</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">สถานะ (Status)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($actions as $action)
        <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="font-weight: 500; color: #1f2937;">{{ $action->log->checkpoint->title ?? '-' }}</td>
            <td style="color: #4b5563;">{{ $action->log->session->department->dept_name ?? '-' }}</td>
            <td style="color: #4b5563;">{{ $action->assignee->name ?? 'Unassigned' }}</td>
            <td style="color: #1f2937;">{{ $action->due_date ? $action->due_date->format('d/m/Y') : '-' }}</td>
            <td style="color: #dc2626; font-weight: bold;">{{ $action->due_date ? $action->due_date->diffForHumans() : '-' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<p style="margin-top: 30px; color: #4b5563;">กรุณาติดตามความคืบหน้ากับผู้รับผิดชอบในแผนกของท่านโดยด่วนครับ</p>

<div style="text-align: center; margin-top: 30px;">
    <a href="{{ route('corrective.index') }}" style="display: inline-block; padding: 12px 24px; background-color: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px;">ดูรายละเอียด (View Dashboard)</a>
</div>
@endsection
