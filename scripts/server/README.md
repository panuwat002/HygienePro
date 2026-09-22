# การตั้งค่าฝั่ง Server สำหรับระบบแจ้งเตือน

เอกสารนี้สำหรับผู้ดูแลเครื่อง **192.168.1.201** (เครื่องที่รัน HygienePro บน IIS)

## สภาพแวดล้อมจริงบนเครื่องนี้

| รายการ | ค่า |
|---|---|
| Web server | IIS — site `hygiene-checklist` |
| Binding | `https://192.168.1.201:8443` |
| Document root | `C:\Users\azure.ad\Documents\project\hygiene-checklist\public` |
| PHP ที่ใช้จริง | `C:\PHP\php-cgi.exe` → อ่าน **`C:\PHP\php.ini`** |
| PHP ที่ **ไม่ได้** ใช้ | `C:\xampp\php\php.ini` (XAMPP Apache listen แค่ 80/443 ไม่มี vhost ของแอปนี้) |

---

## ส่วนที่ 1 — Email (ทำก่อน)

### ปัญหา

SMTP เองไม่มีปัญหาครับ ทดสอบแล้ว Office 365 ตอบ `235 2.7.0 Authentication successful`
และ STARTTLS handshake ผ่าน แม้ `C:\PHP\php.ini` จะไม่ได้ตั้ง CA bundle ไว้
(PHP ฝั่ง openssl stream ดึง CA จาก Windows certificate store ได้เอง — ต่างจาก cURL)

ปัญหาจริงคือ **โปรเจกต์นี้ไม่เคยมี scheduled task ของตัวเอง**:

1. **ไม่มี queue worker** — `QUEUE_CONNECTION=database` แต่ไม่มีใครกิน job
   → เมลที่เป็น `ShouldQueue` ค้างใน table `jobs` ตลอดกาล
   (`PendingVerificationDigestNotification`, `RandomAuditEscalationMail`)
2. **ไม่มี `schedule:run`** — ทุกอย่างใน `routes/console.php` ไม่เคยทำงาน
   → ไม่มี smart digest ทุก 30 นาที, ไม่มีรายงาน CAR เกินกำหนด

Task Scheduler มี `\Laravel Queue Worker` กับ `\Laravel Asset Control Scheduler` อยู่แล้ว
แต่ทั้งคู่เป็นของโปรเจกต์อื่น (e-contract / asset-control) **ไม่ใช่ของ HygienePro**
และตัว asset-control ยังตั้ง path ผิดเป็น `C:\PHP\php.ini` (ต้องเป็น `php.exe`) จน `Last Result` เป็น error

### วิธีติดตั้ง

ต้องรันแบบ **Run as administrator** เสมอ (คลิกขวาที่ cmd.exe หรือ PowerShell → Run as administrator)

**ถ้าใช้ cmd.exe** — เรียกผ่านตัว launcher `.bat` (cmd รัน `.ps1` ตรง ๆ ไม่ได้):

```bat
cd /d C:\Users\azure.ad\Documents\project\hygiene-checklist\scripts\server

setup-server.bat -WhatIf
setup-server.bat
```

**ถ้าใช้ PowerShell**:

```powershell
cd C:\Users\azure.ad\Documents\project\hygiene-checklist\scripts\server

powershell -ExecutionPolicy Bypass -File .\setup-server.ps1 -WhatIf
powershell -ExecutionPolicy Bypass -File .\setup-server.ps1
```

> `-WhatIf` = ลองดูว่าจะทำอะไรบ้าง โดยยังไม่แก้อะไรจริง แนะนำให้รันดูก่อนทุกครั้ง

สร้าง scheduled task 2 ตัว:

| Task | ทำงานเมื่อ | คำสั่ง |
|---|---|---|
| `HygienePro Scheduler` | ทุก 1 นาที | `C:\PHP\php.exe artisan schedule:run` |
| `HygienePro Queue Worker` | ตอน system startup | `scripts\server\start-queue.bat` (มี loop restart ในตัว) |

### ตรวจสอบหลังติดตั้ง

```powershell
cd C:\Users\azure.ad\Documents\project\hygiene-checklist

C:\PHP\php.exe artisan schedule:list     # ต้องเห็น car:check-overdue ด้วย
C:\PHP\php.exe artisan queue:work --once # ลองเคลียร์ job ค้างทีละตัว
C:\PHP\php.exe artisan queue:failed      # ดูว่ามีอะไรพังไหม
```

queue worker รันเป็น SYSTEM แบบไม่มีหน้าจอ ทุกอย่างจึงถูกเขียนลง
`storage\logs\queue-worker.log` — **เวลาเมลไม่มา ให้ดูไฟล์นี้ก่อน**

```powershell
Get-Content storage\logs\queue-worker.log -Tail 30
```

ถ้าเห็น `queue:work exited with code ...` ซ้ำ ๆ ทุก 5 วินาที แปลว่า worker พังแล้ววน restart
ให้ดูข้อความ error เหนือบรรทัดนั้น

เช็ค job ที่ค้างสะสมไว้:

```sql
SELECT COUNT(*) FROM jobs;         -- งานที่รอ worker
SELECT COUNT(*) FROM failed_jobs;  -- งานที่ล้มเหลว
```

### อ่าน log ให้เป็น

เดิม log เขียนว่า "emails sent" ทุกครั้งแม้ไม่มีผู้รับเลย ตอนนี้แยกสองกรณีชัดแล้ว:

```
INFO  Session 123 finished: queued 'email_session_finished_fail' email to 2 QA supervisor(s).
        └─ ส่งเข้า SMTP จริงแล้ว

WARN  Session 123 finished: NO email sent - 3 QA supervisor(s) found, none opted in to 'email_session_finished_pass'.
        └─ ระบบปกติ แต่ผู้รับปิดการแจ้งเตือนเหตุการณ์นี้ไว้ ไม่ใช่เมลพัง
```

ถ้าเจอบรรทัด `WARN` แปลว่าผู้ใช้คนนั้นเข้าไป**ปิดเอง**ในหน้า notification preferences
เพราะตอนนี้ค่า default เปิดหมดทุกเหตุการณ์แล้ว

### ⚠️ ค่า default เปิดหมดไว้ชั่วคราวสำหรับ UAT

`app/Models/User.php` → `wantsEmailFor()` เดิมปิด 4 เหตุการณ์ไว้กันสแปม
(`email_session_started`, `email_session_finished_pass`, `email_session_verified`,
`email_order_reclean`) ตอนนี้เปิดหมดเพื่อให้ UAT ทดสอบได้ครบทุก flow

**ก่อนขึ้น production ให้ทบทวนว่าจะปิดตัวไหนกลับ** — ในโค้ดมี comment `// opt-in before UAT`
กำกับไว้ทั้ง 4 บรรทัดแล้ว ส่วนผู้ใช้ที่ตั้งค่าเองไว้จะไม่ถูกกระทบไม่ว่ากรณีใด

---

## ส่วนที่ 2 — LINE (ยังไม่ทำ)

เก็บไว้ทำทีหลัง บันทึกสาเหตุไว้ก่อนกันลืม — มี 2 ชั้นซ้อนกัน:

**(ก) cURL ไม่มี CA bundle** — `C:\PHP\php.ini` comment `curl.cainfo` / `openssl.cafile`
ไว้ และไม่มีไฟล์ CA บนเครื่อง → ทุก LINE push ตายด้วย
`cURL error 60: unable to get local issuer certificate`

โผล่มาตั้งแต่ commit `1ed42b8` (16 ก.ย.) ที่ถอด `withoutVerifying()` ออกด้วยเหตุผลด้าน security
— **ห้ามใส่กลับ** เพราะไฟล์นั้นส่ง channel access token ไปใน header

แก้ด้วย:
```powershell
.\setup-server.ps1 -IncludeLineFix
```

**(ข) โควต้ารายเดือนหมด** — LINE ตอบ `{"message":"You have reached your monthly limit."}`
มาตั้งแต่ **3 ก.ย. 2026** (วันเดียวยิง 253 ครั้งจนโควต้า free plan หมด)

**ถึงแก้ (ก) เสร็จ LINE ก็ยังส่งไม่ออก** จนกว่าจะรอโควต้ารีเซ็ตหรืออัปเกรดแพ็กเกจ
LINE Official Account — และระวังอย่ารันสคริปต์ resend เป็นชุด (เช่น `resend_notifications.php`
ที่รากโปรเจกต์) เพราะเป็นสาเหตุที่ทำให้โควต้าหมดในวันเดียว
