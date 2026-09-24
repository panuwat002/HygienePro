@extends('emails.layouts.master')

@section('title', 'Awaiting Approval Digest')
@section('header_title', 'งานรออนุมัติ')
@section('header_subtitle', '(Awaiting Your Approval)')

@section('content')
@php
    $remaining = $total - count($rows);
    $oldestDays = collect($rows)->max('waiting_days') ?? 0;
@endphp

<p style="margin-top: 0; font-size: 16px;">
    QA ทวนสอบเสร็จแล้ว และมีรอบตรวจ <strong>{{ $total }}</strong> รอบ รอการอนุมัติจากคุณ
</p>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 16px;">
    <tr>
        <td width="50%" style="padding-right: 6px;">
            <div style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 14px; text-align: center;">
                <div style="font-size: 13px; color: #1e40af;">ตรวจพนักงาน</div>
                <div style="font-size: 24px; font-weight: bold; color: #1e3a8a;">{{ $personCount }}</div>
            </div>
        </td>
        <td width="50%" style="padding-left: 6px;">
            <div style="background-color: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 6px; padding: 14px; text-align: center;">
                <div style="font-size: 13px; color: #5b21b6;">ตรวจพื้นที่ / เครื่องจักร</div>
                <div style="font-size: 24px; font-weight: bold; color: #4c1d95;">{{ $areaCount }}</div>
            </div>
        </td>
    </tr>
</table>

@if($oldestDays >= 1)
<p style="margin-top: 20px; padding: 12px 14px; background-color: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; font-size: 14px;">
    รอบที่เก่าที่สุดค้างมาแล้ว <strong>{{ $oldestDays }}</strong> วัน — เซสชันจะยังไม่ถูกล็อคจนกว่าจะอนุมัติครบ
</p>
@endif

<p style="margin-top: 24px; font-size: 15px; color: #374151;">
    เรียงจากรอบที่ค้างนานที่สุด@if($remaining > 0) (แสดง {{ count($rows) }} จาก {{ $total }} รอบ)@endif:
</p>

<table width="100%" cellpadding="12" cellspacing="0" border="0" style="border-collapse: collapse; text-align: left; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; margin-top: 12px;">
    <thead>
        <tr style="background-color: #f9fafb; border-bottom: 2px solid #e5e7eb;">
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">รายการตรวจ</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">กะ (Shift)</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">แผนก</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">ผู้ทวนสอบ</th>
            <th style="font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">ค้างมา</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
        <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="font-weight: 500; color: #1f2937;">
                {{ $row['kind'] === 'person' ? '👤' : '⚙️' }} {{ $row['label'] }}
            </td>
            <td style="color: #4b5563;">{{ $row['shift'] }}</td>
            <td style="color: #4b5563;">{{ $row['department'] }}</td>
            <td style="color: #4b5563;">{{ $row['inspector'] }}</td>
            <td style="color: {{ $row['waiting_days'] >= 3 ? '#dc2626' : '#4b5563' }}; font-weight: {{ $row['waiting_days'] >= 3 ? 'bold' : 'normal' }};">
                {{ $row['waiting_days'] === 0 ? 'วันนี้' : $row['waiting_days'] . ' วัน' }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

@if($remaining > 0)
<p style="margin-top: 16px; color: #6b7280; font-size: 14px;">และอีก <strong>{{ $remaining }}</strong> รอบ — ดูทั้งหมดได้ที่หน้าทวนสอบ</p>
@endif

<div style="text-align: center; margin-top: 30px;">
    <a href="{{ route('inspection.verification', ['tab' => 'awaiting_approval']) }}" style="display: inline-block; padding: 12px 24px; background-color: #059669; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px;">ไปที่หน้าอนุมัติ</a>
</div>
@endsection
