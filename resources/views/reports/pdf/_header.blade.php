<div class="header">
    <h1>รายงานการตรวจสอบสุขลักษณะประจำวัน (Daily Hygiene Checklist)</h1>
    <p>วันที่: {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} | กะ: {{ $shift ? ucfirst($shift) : 'ทั้งหมด (All)' }}</p>
</div>
