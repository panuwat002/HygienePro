<div class="header">
    <h1>รายงานการตรวจสอบสุขลักษณะประจำวัน (Daily Hygiene Checklist)</h1>
    <p>วันที่: {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} | กะ: {{ $shift ? ucfirst($shift) : 'ทั้งหมด (All)' }}</p>

    {{-- A round entered on a day other than the one it covers says so here.
         Backdating exists because a record has to survive the day it could not
         be entered - QA had no shift roster from Production on 5 Oct 2026 and
         the day went unrecorded. What separates that from fabrication is that
         the paper declares it, names who authorised it and gives the reason. --}}
    @php
        $backdatedSessions = isset($sessions)
            ? collect($sessions)->filter(fn ($s) => $s->isBackdated())
            : collect();
    @endphp

    @if($backdatedSessions->isNotEmpty())
        <div style="border: 1px solid #000; padding: 3px 6px; margin: 2px 0 4px 0; font-size: 8.5px; text-align: left; background-color: #f5f5f5;">
            <strong>บันทึกย้อนหลัง (Backdated Entry)</strong> —
            การตรวจของวันที่ {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} นี้
            ถูกบันทึกเข้าระบบเมื่อวันที่
            {{ $backdatedSessions->map(fn ($s) => $s->created_at->format('d/m/Y'))->unique()->implode(', ') }}
            @php $authorisers = $backdatedSessions->map(fn ($s) => $s->backdatedBy?->name)->filter()->unique(); @endphp
            @if($authorisers->isNotEmpty())
                โดย {{ $authorisers->implode(', ') }}
            @endif
            <br>
            เหตุผล: {{ $backdatedSessions->map(fn ($s) => $s->backdated_reason)->filter()->unique()->implode(' / ') ?: '-' }}
        </div>
    @endif
</div>
