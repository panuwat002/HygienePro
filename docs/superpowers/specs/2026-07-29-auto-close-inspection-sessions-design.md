# Design: Auto-close forgotten inspection sessions

- **Date:** 2026-07-29
- **Status:** Draft (pending user review)
- **Author:** Brainstorming session (Claude + panuwat)

## 1. Problem / context

เมื่อผู้ตรวจ (inspector) เปิดรอบการตรวจ (`InspectionSession`) แล้ว **ลืมกด "จบงาน/ปิดรอบ" (Finish)** หลังเลยเวลากะ รอบนั้นจะค้างสถานะ `in_progress`/`paused` ค้างอยู่ ทำให้ **Admin ต้องเข้ามากด "บังคับปิดรอบ" ให้เองทุกครั้ง** และต้องคอยไล่ตรวจว่ามีรอบไหนค้างบ้าง

เป้าหมาย: ให้ระบบ **ปิดรอบที่ถูกลืมให้อัตโนมัติ** อย่างปลอดภัย โดยไม่ไปตัดคนที่ยังตรวจอยู่จริง

### Historical note (สำคัญ)
เคยมี auto-complete logic แต่ถูก **ถอดออกโดยตั้งใจ** — ดูคอมเมนต์ที่ `app/Http/Controllers/InspectionController.php:311`:
> "Removed auto-complete logic here so it doesn't force close sessions if an inspector wants to inspect outside their shift."

ดีไซน์นี้แก้ปัญหาเดิมด้วย **grace period + idle-guard** เพื่อไม่ให้ปิดทับคนที่ยังทำงานอยู่

## 2. Goals / Non-goals

**Goals**
- ปิดรอบที่ค้าง (`in_progress`/`paused`) อัตโนมัติเมื่อเลยกะไปนานพอและไม่มีความเคลื่อนไหว
- ไม่ตัดคนที่ยังตรวจอยู่จริง (idle-guard)
- Admin/QA รับรู้ได้ว่ารอบไหนถูกปิดโดยระบบ (activity log + notification)
- ทำงานได้จริงบนเครื่องนี้ **โดยไม่ต้องพึ่ง Windows Task Scheduler** (เพราะยังไม่มีหลักฐานว่า `schedule:run` รันอยู่)

**Non-goals**
- ไม่แก้พฤติกรรมปุ่ม "บังคับปิดรอบ" ของ Admin เดิม
- ไม่ bulk-pass และไม่ mark เป้าหมายที่เหลือเป็น missed — ปล่อยตามสภาพจริง
- ไม่ทำหน้า UI ตั้งค่า (ใช้ `.env`/config ไปก่อน)
- ไม่แก้ปัญหา 500 ตอน verify (คนละเรื่อง แยก track)

## 3. The rule — เมื่อไหร่ถือว่า "รอบค้างที่ต้องปิด" (stale)

รอบจะถูกปิดอัตโนมัติเมื่อ **เป็นจริงทุกข้อ**:

1. `is_locked = false` **และ** `status ∈ {in_progress, paused}`
2. `now() >= shiftEndAt(session) + GRACE_HOURS`
3. `now() - lastActivityAt(session) >= IDLE_MINUTES` (idle-guard)
4. คำนวณ `shiftEndAt` ได้ (กะมี `end_time`) — ถ้าคำนวณไม่ได้ ⇒ **ข้าม ไม่ปิด** (ปลอดภัยไว้ก่อน)

### 3.1 `shiftEndAt(session)`
- หา `Shift` ที่ตรงกับ `session.shift` (map ชื่อกะแบบเดียวกับที่ใช้ในระบบ: `morning => ['morning','กะเช้า']`, `afternoon => ['afternoon','กะบ่าย']`, `night => ['night','กะดึก']`) แล้วอ่าน `end_time`
- `shiftEndAt = session.inspection_date + end_time`
- **กะข้ามเที่ยงคืน:** ถ้า `end_time <= start_time` (เช่น กะดึก 22:00–06:00) ⇒ `shiftEndAt = inspection_date + 1 day + end_time`
- ถ้าไม่พบ `Shift` หรือ `end_time` เป็น null ⇒ คืนค่า null ⇒ ตามข้อ 4 ข้ามรอบนั้น

### 3.2 `lastActivityAt(session)`
- `max(InspectionLog.created_at ของ session นี้)` ; ถ้าไม่มี log เลย ⇒ ใช้ `session.updated_at` (fallback `created_at`)
- ใช้กันเคส "ตรวจยาวเลยกะ" — ถ้าเพิ่งบันทึกผลไม่กี่นาทีที่แล้ว จะยังไม่ปิด

## 4. Behavior on close — ทำอะไรตอนปิด

ต่อรอบที่เข้าเงื่อนไข:
1. เรียก `InspectionService::finishSession($session)` เดิม → set `status = completed` + แจ้ง QA supervisors (idempotent: ถ้า completed แล้ว return ทันที)
2. เป้าหมายที่ยังไม่ตรวจ → **ไม่แตะข้อมูล** (เหมือนกด Finish เอง)
3. เขียน **activity log** (system actor):
   ```php
   ActivityLog::create([
       'user_id'     => null,               // system
       'action'      => 'auto_close',
       'model_type'  => InspectionSession::class,
       'model_id'    => $session->id,
       'description' => "ปิดรอบอัตโนมัติโดยระบบ (เลยกะ {$session->shift} + {$grace} ชม. และไม่มีการตรวจใน {$idle} นาที)",
   ]);
   ```
4. Notification: ใช้ `notifySupervisorsFinished()` ที่ `finishSession` เรียกอยู่แล้ว (แจ้ง QA supervisors) — Admin เห็นผ่าน activity log

## 5. Trigger — A+B (heartbeat หลัก + schedule เสริม)

Logic หลักอยู่ใน service เดียว เรียกได้จาก 2 ทาง (idempotent จึงเรียกซ้ำได้ปลอดภัย):

### A. Scheduled command (เสริม — ถ้ามี cron)
- `app/Console/Commands/AutoCloseStaleSessions.php` — signature `inspections:auto-close-stale`
- `routes/console.php`: `Schedule::command('inspections:auto-close-stale')->everyFifteenMinutes();`
- ทำงานเองถ้ามีการตั้ง Task Scheduler ให้รัน `php artisan schedule:run` ทุกนาที (ยังไม่มีบนเครื่องนี้ → เป็นของแถม)

### B. Heartbeat ตอนโหลด dashboard (หลัก — ใช้ได้ทันที)
- ใน `InspectionController::home()` เรียก service แบบ **throttle ผ่าน cache** (รันจริงไม่เกิน 1 ครั้ง/`HEARTBEAT_MINUTES`)
- **ต้อง wrap ทั้งก้อน (รวม `Cache::add`) ใน try/catch** — ถ้า cache/DB หรือ auto-close พัง ห้ามทำให้ dashboard ล่ม:
  ```php
  try {
      if (config('inspection.auto_close.enabled')
          && Cache::add('auto_close_heartbeat', 1, now()->addMinutes($heartbeatMinutes))) {
          $this->inspectionService->autoCloseStaleSessions();
      }
  } catch (\Throwable $e) {
      \Log::error('auto-close heartbeat failed: '.$e->getMessage());
  }
  ```

## 6. Configuration — `config/inspection.php` (ใหม่)
```php
return [
    'auto_close' => [
        'enabled'          => env('AUTO_CLOSE_ENABLED', true),      // kill-switch
        'grace_hours'      => env('AUTO_CLOSE_GRACE_HOURS', 2),     // หลังหมดกะ
        'idle_minutes'     => env('AUTO_CLOSE_IDLE_MINUTES', 30),   // idle-guard
        'heartbeat_minutes'=> env('AUTO_CLOSE_HEARTBEAT_MINUTES', 10),
    ],
];
```
ค่าเริ่มต้น: **Grace = 2 ชม., Idle = 30 นาที** (ปรับได้ทาง `.env`) ถ้า `enabled=false` ระบบจะไม่ปิดอะไรเลย (ปุ่มฉุกเฉิน)

## 7. Components to touch

| ไฟล์ | การเปลี่ยนแปลง |
|------|----------------|
| `config/inspection.php` | **ใหม่** — ค่า config auto_close |
| `app/Services/InspectionService.php` | เพิ่ม `autoCloseStaleSessions(): int` และ helper `shiftEndAt()`, `lastActivityAt()`, `isStale()` (ใช้ `finishSession()` เดิม + เขียน activity log) |
| `app/Console/Commands/AutoCloseStaleSessions.php` | **ใหม่** — command `inspections:auto-close-stale` เรียก service, echo จำนวนที่ปิด |
| `routes/console.php` | เพิ่ม `Schedule::command(...)->everyFifteenMinutes()` |
| `app/Http/Controllers/InspectionController.php` (`home`) | เพิ่ม heartbeat throttle (try/catch) |

**ไม่มี migration** — ไม่แก้ schema (ใช้ `activity_logs` เดิม, ไม่เพิ่มคอลัมน์)

## 8. Edge cases

- **กะข้ามเที่ยงคืน** (night, `end_time < start_time`): shiftEndAt = วันถัดไป (ดู 3.1)
- **กะไม่มี `end_time` / หา Shift ไม่เจอ**: ข้าม ไม่ปิด (fail-safe)
- **รอบที่เปิดแต่ไม่มี log เลย**: idle ใช้ `session.updated_at` — ยังปิดได้ถ้าเลย grace + idle
- **เรียกซ้ำ (heartbeat + schedule พร้อมกัน)**: `finishSession` idempotent + throttle cache → ไม่ปิดซ้ำ/ไม่แจ้งซ้ำ
- **paused sessions**: เข้าข่ายปิดได้ (ถือว่าลืมเหมือนกัน)
- **DB/notify ล่ม**: notification อยู่ใน try/catch เดิมอยู่แล้ว; heartbeat ก็ห่อ try/catch — ไม่ทำให้หน้าเว็บล่ม

## 9. Testing strategy

- **Unit — `isStale()`**: 
  - เลย grace + idle จริง ⇒ stale
  - เพิ่งมี log ใน idle window ⇒ ไม่ stale (แม้เลย grace)
  - ยังไม่ถึง grace ⇒ ไม่ stale
  - completed/locked ⇒ ไม่ stale
  - night shift wrap ⇒ คำนวณ end ถูกเป็นวันถัดไป
  - กะไม่มี end_time ⇒ ไม่ stale
- **Feature — command/service**: 
  - ปิดเฉพาะรอบ stale, ปล่อยรอบ active
  - เรียกซ้ำไม่ปิดซ้ำ/ไม่แจ้งซ้ำ (idempotent)
  - เขียน `activity_logs` (action=`auto_close`, user_id=null) ครบ
  - เป้าหมายที่ยังไม่ตรวจไม่ถูกแก้

## 10. Open items (ค่าเริ่มต้นที่เสนอ)
- Grace = 2 ชม., Idle = 30 นาที, Heartbeat throttle = 10 นาที — ปรับได้ผ่าน `.env` ภายหลัง
