@component('mail::message')
# 🧹 Order Re-clean / สั่งแก้ไขความสะอาด

**เรียน ผู้จัดการแผนก {{ $session->department->dept_name ?? '-' }},**

มีการตรวจสอบพบข้อบกพร่องและ **"สั่งแก้ไขใหม่ (Re-clean)"** โดยผู้ตรวจสอบ
รายละเอียดดังนี้:

**วันที่:** {{ $session->inspection_date }}  
**กะ/รอบ:** {{ $session->shift }} (Round {{ $session->round }})  
**ผู้ตรวจสอบ:** {{ Auth::user()->name }}  
**บันทึกเพิ่มเติม:** {{ $comment ?? '-' }}

@component('mail::table')
| จุดที่ต้องแก้ไข (Checkpoint) | ผู้รับผิดชอบ/พื้นที่ | ปัญหาที่พบ |
|:--- |:--- |:--- |
@foreach($logs as $log)
| **{{ $log->checkpoint->title ?? 'N/A' }}** | {{ $log->employee->fullname ?? ($log->location->location_name ?? '-') }} | {{ $log->correction_action ?? '-' }} |
@endforeach
@endcomponent

กรุณาดำเนินการแก้ไขและแจ้งให้ผู้ตรวจสอบทำการทวนสอบซ้ำอีกครั้ง

@component('mail::button', ['url' => route('inspection.dashboard', $session->type)])
ดูรายละเอียด (View Dashboard)
@endcomponent

ขอบคุณครับ,<br>
{{ config('app.name') }}
@endcomponent
