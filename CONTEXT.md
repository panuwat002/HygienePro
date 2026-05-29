# Hygiene Inspection

ระบบตรวจสอบความสะอาดและ GMP ในโรงงาน ครอบคลุมการตรวจรอบ (Inspection Session) การบันทึกผลรายจุด (Inspection Log) การยืนยัน/อนุมัติ และการจัดการข้อบกพร่องที่แก้ไม่ได้ทันที (CAR)

## Structure

**Department**:
หน่วยงาน/แผนกในโรงงาน เป็นขอบเขตการมองเห็นข้อมูลและสิทธิ์ของ Supervisor
_Avoid_: Division, unit, org

**Location**:
พื้นที่ตรวจในแผนก (เช่น ห้องผลิต, ทางเดิน) ใช้กับ inspection ประเภท area
_Avoid_: Area (ใช้ได้ในภาษาพูด แต่ในโค้ดใช้ Location), zone, room

**Machine**:
เครื่องจักรที่ต้องตรวจ ใช้กับ inspection ประเภท machine
_Avoid_: Equipment, asset, device

**Checkpoint**:
รายการตรวจมาตรฐาน (master data) ว่าต้องตรวจอะไร มีประเภท person หรือ area
_Avoid_: Check item, criterion, checklist item

## People

**User**:
บัญชีผู้ใช้ระบบ (login ได้) มี role กำหนดสิทธิ์
_Avoid_: Account, staff, member

**Employee**:
พนักงานที่ถูกตรวจ (inspectee) ไม่ใช่บัญชี login — ผูกกับ checkpoint ประเภท person
_Avoid_: Worker, personnel, user

**Inspector**:
User ที่ทำการตรวจและบันทึกผลใน session
_Avoid_: Checker, auditor

**Supervisor**:
User ที่ verify ผลตรวจและสามารถขอ re-clean ได้
_Avoid_: Reviewer, lead

**Manager**:
User ที่ approve session ขั้นสุดท้าย ทำให้ session ถูก lock
_Avoid_: Approver (ใช้เป็นคำกริยาได้ แต่ role คือ Manager)

## Inspection

**Inspection Session**:
รอบการตรวจหนึ่งครั้ง ของ Inspector หนึ่งคน ใน Department/Shift/Date/Type ที่กำหนด เป็นหัวข้อของ logs ทั้งหมดในรอบนั้น
_Avoid_: Round (round เป็นฟิลด์ย่อย), inspection, check

**Inspection Log**:
ผลการตรวจหนึ่ง checkpoint ใน session (pass/fail) พร้อมหลักฐานและหมายเหตุ
_Avoid_: Record, entry, result row

**Session Type**:
ประเภท session — `machine` (ตรวจเครื่องจักร) หรือ `area` (ตรวจพื้นที่/location)
_Avoid_: Category, mode, inspection kind

**Shift**:
กะการทำงานที่ session นี้สังกัด (เช่น กะเช้า/กะดึก)
_Avoid_: Work period, schedule slot

**Round**:
ลำดับรอบตรวจในวัน/กะเดียวกัน (เมื่อมีการตรวจซ้ำ)
_Avoid_: Iteration, pass number

## Session Lifecycle

**In Progress**:
Session กำลังเปิดอยู่ Inspector สามารถ scan และบันทึก log ได้
_Avoid_: Active, open, running

**Paused**:
Session หยุดชั่วคราว (เช่น พักเบรก) สามารถ resume กลับเป็น in_progress ได้
_Avoid_: Suspended, on hold

**Completed**:
Inspector บันทึกจบรอบแล้ว ไม่เพิ่มรายการใหม่ได้ (ยกเว้นแก้ re-clean)
_Avoid_: Finished, done, submitted

**Verified**:
Supervisor ตรวจทานและลงนามแล้ว (บันทึกที่ `verified_at` / `verified_by`)
_Avoid_: Reviewed, checked, signed off

**Approved**:
Manager อนุมัติขั้นสุดท้าย session ถูก lock (`is_locked = true`) แก้ไขไม่ได้
_Avoid_: Accepted, finalized, closed

**Session Lock**:
สถานะที่ session ไม่สามารถแก้ไขได้หลัง Manager approve ครบทุก log ใน session — ใช้ `is_locked = true` เป็นตัวบ่งชี้หลัก (ไม่ใช่ `locked_at`)
_Avoid_: Freeze, immutable flag, locked_at

## Defect Handling

**Re-clean**:
การขอให้ Inspector แก้ไข/ตรวจซ้ำรายการที่ Supervisor ไม่ยอมรับ บันทึกที่ `verification_status = reclean` บน log — **ไม่ใช่ CAR** และไม่ lock session
_Avoid_: Reclean (ไม่มี hyphen), rework, redo, ticket

**Rejection**:
การตีกลับรายการโดย Supervisor (ปุ่ม Reject) บันทึกที่ `verification_status = rejected` และเปิด session กลับเป็น in_progress เพื่อให้ Inspector แก้ทั้งชุด — ต่างจาก Re-clean ที่แก้เฉพาะรายการ fail
_Avoid_: Reject, send back, rollback

**CAR (Corrective Action Request)**:
คำขอแก้ไขข้อบกพร่องที่แก้ทันทีไม่ได้ (เช่น กระเบื้องแตก) มี lifecycle แยกจาก session — สร้างเฉพาะเมื่อกด **Escalate** เท่านั้น ไม่ auto-create จาก Re-clean
_Avoid_: Ticket, issue, defect report, CAPA

**Escalate**:
การยกระดับ log ที่ fail ไปเป็น CAR
_Avoid_: Raise, submit, create ticket

**Immediate Correction**:
การแก้ไขทันทีใน session เดียวกันเมื่อ checkpoint fail (ถ่ายรูป/บันทึกการแก้ไข)
_Avoid_: On-the-spot fix, instant fix

## Schedule & Compliance

**Inspection Schedule**:
ตารางกำหนดว่าต้องมีการตรวจ location/machine ใดเมื่อไหร่
_Avoid_: Plan, calendar, roster

**Compliance**:
สถานะการทำตาม schedule — completed, pending, หรือ missed
_Avoid_: Adherence, fulfillment, on-time rate
