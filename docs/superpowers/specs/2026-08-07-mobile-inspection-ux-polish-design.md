# Mobile Inspection UX — Polish Sprint (Design Spec)

- **Date:** 2026-08-07
- **Sub-project:** A of 4 (Mobile Inspection UX)
- **Approach:** Polish Sprint (Approach 1 of 3) — targeted fixes, no architecture change
- **Effort:** ~23 engineer-hours (~3-4 days full-time, ~1.5-2 weeks part-time)

## 1. Goals & Non-goals

### Goals
- Reduce time-per-checkpoint (esp. `fail` path with photo+comment) by at least 30%.
- Eliminate data loss when a mobile session is interrupted (network drop, tab close, accidental navigation).
- Give scan feedback the user can perceive without watching the screen (vibrate + beep).
- Reduce photo upload time 5–10× by compressing on-device before submit.

### Non-goals
- No full offline/service-worker mode (deferred to a later phase; the auto-save foundation from Fix 2 makes it easier to add later).
- No visual redesign — keep the existing design language (glassmorphism cards, iOS-style segmented control).
- No backend business-logic changes. Only one small endpoint tweak (`?partial=1` for Fix 3).
- Verification/Approval, Dashboard, and CAR flows are separate sub-projects (B/C/D).

### Success metrics
- `session.finished_at - started_at` mean drops 20–30% within one week of rollout.
- HTTP 5xx rate at `POST inspection.log.store` drops (baseline vs. one week post-deploy).
- Draft-recovery banner appears and is used at least once in the first week (proves the safety net works).
- Structured feedback from 3–5 QA Staff after one week is net-positive.

## 2. The Six Fixes

Each fix is small enough to ship on its own commit.

### Fix 1 — QR Scanner reliability & feedback
**Problem.** `html5-qrcode` is loaded from `unpkg.com` — external CDN, slow first paint, breaks if the factory Wi-Fi has flaky egress. Errors surface via `alert()`. There is no torch, no vibration, no beep, so the inspector must look at the screen to know a scan landed.
**Change.**
- Self-host `html5-qrcode.min.js` at `public/vendor/html5-qrcode.min.js`.
- Replace `alert(...)` on camera errors with SweetAlert2 (already in the stack) with a "Retry" button.
- Add a torch/flashlight toggle button (feature-detect via `ImageCapture.getPhotoCapabilities()`; hide if unsupported).
- On scan success: `navigator.vibrate([100])` + play `public/audio/beep.mp3`.

**Files.** `resources/views/inspections/scan.blade.php`, `resources/views/inspections/area_checklist.blade.php`.
**Est.** ~4 h.

### Fix 2 — Auto-save draft (data-loss protection)
**Problem.** Closing the tab, losing signal, or navigating away mid-form loses every `fail` entry (photo + correction text). Users have retyped the same data multiple times.
**Change.**
- On every change to `input[name^="logs["]` and `textarea[name^="logs["]`, write a JSON snapshot to `localStorage['inspection_draft_' + session_id + '_' + employee_id]`.
- Photos are saved as `FileReader.readAsDataURL(...)` (base64) inside the same snapshot. Compression from Fix 4 keeps this small.
- On page load, if a draft exists for the current employee+session, show a top banner: "🔄 พบข้อมูลที่ยังไม่ได้บันทึก [กู้คืน] [ทิ้ง]".
- Clear the draft when the server returns a 2xx redirect after `inspection.log.store` succeeds.
- Auto-expire drafts older than 24 hours (checked on load).

**Files.** `resources/views/inspections/form.blade.php`, `resources/views/inspections/area_checklist.blade.php`, new `public/js/inspection-draft.js`.
**Est.** ~6 h.

### Fix 3 — Location change without full-page reload
**Problem.** `form.blade.php:298-301` reloads the whole page (`window.location.href = ...`) when the inspector changes the location. This discards any `fail` entries already filled in.
**Change.**
- Change `changeLocation()` to `fetch('/inspection/session/{s}/verify/{hash}?location_id={id}&partial=1')`.
- Add `?partial=1` handling in `InspectionController@showChecklist` that returns JSON: `{ checkpoints: [{id, title, description, image_good, image_bad, existing_log, reclean_request}], currentLocationId }`. (JSON not a Blade partial, so the client owns rendering and can re-hydrate cleanly.)
- Alpine.js re-renders the checkpoint list from the response; existing in-flight `fail` entries are preserved by re-hydrating from the Fix 2 auto-save.
- Replace the SweetAlert "loading" modal with a small inline toast: "เปลี่ยนไป <ชื่อจุด> แล้ว".

**Files.** `resources/views/inspections/form.blade.php`, `app/Http/Controllers/InspectionController.php`, possibly a new partial `resources/views/inspections/partials/_checkpoint_list.blade.php`.
**Est.** ~5 h.

### Fix 4 — Client-side image compression
**Problem.** Factory phones produce 3–5 MB JPEGs. On weak Wi-Fi this dominates submit time (10–30 s) and causes intermittent 5xx.
**Change.**
- Self-host `browser-image-compression.min.js` (~30 KB) at `public/vendor/`.
- Before submit, compress each `input[type=file]` payload: `maxSizeMB: 0.4, maxWidthOrHeight: 1600, useWebWorker: true, initialQuality: 0.75`.
- Show a progress bar during compress+upload.
- Expose compression params via `.env` (`INSPECTION_PHOTO_MAX_MB`, `INSPECTION_PHOTO_MAX_DIM`) so QA can tune without a code change.

**Files.** `resources/views/inspections/form.blade.php`, `resources/views/inspections/area_checklist.blade.php`, new `public/js/inspection-image.js`, `.env.example`.
**Est.** ~3 h.

### Fix 5 — Manual fallback prominence
**Problem.** When QR won't scan (dirty label, damaged tag), the "กรอกรหัสพนักงาน" link is a small `btn-link` under the card — easy to miss.
**Change.**
- Promote it to a full-width button next to "เลือกจากรายชื่อ" (two buttons side by side).
- Tapping "กรอกรหัส" expands an inline number input plus a "ตกลง" submit button (explicit submit — no auto-submit-on-length, since employee-ID length varies per company and detection would be brittle).

**Files.** `resources/views/inspections/scan.blade.php`.
**Est.** ~2 h.

### Fix 6 — Undo last entry pill
**Problem.** Mis-tapping `pass`/`fail` requires opening `browse`, finding the employee, re-opening — many taps to recover from one mistake.
**Change.**
- After a successful submit, show a sticky pill at the bottom-right for 10 s: "↶ ย้อน: <ชื่อล่าสุด> (fail)".
- Tapping the pill routes to that employee's checklist with a query flag `?revise=1` so the form pre-fills from the last log.

**Files.** `resources/views/inspections/scan.blade.php`, `resources/views/inspections/browse.blade.php`, small session-flash message from the controller.
**Est.** ~3 h.

## 3. Testing, Rollout, Risks

### Testing

**Manual QA (mandatory).** One QA Staff runs a full real shift on the factory floor before merging the final commit. Test cases:
- QR scan (success, unreadable, torch on/off).
- Submit (normal, network drop mid-submit, second attempt).
- Draft recovery (kill the tab mid-form, reopen).
- Location change mid-inspection (verify entries survive).
- Photo upload (small phone camera vs. large phone camera).

**Automated tests.**
- JS unit tests (Vitest) for `inspection-draft.js`: key format, save/load/clear, 24 h expiry.
- Pest feature test for the `?partial=1` response of Fix 3 (JSON shape, correct checkpoint list per location).
- Existing Blade views need no new tests — manual QA is the coverage.

### Rollout — ship the six fixes one commit at a time

| Order | Fix | Reason to ship first |
|:-:|---|---|
| 1 | Fix 5 (manual fallback) | Lowest risk, no JS beyond a UI move; helps anyone whose QR is unreadable. |
| 2 | Fix 4 (image compression) | Fixes the biggest bandwidth pain; isolated to the submit path. |
| 3 | Fix 1 (QR reliability) | Feedback lets users trust the scanner. |
| 4 | Fix 2 (auto-save draft) | Highest test-burden (localStorage lifecycle); ship after simpler wins. |
| 5 | Fix 3 (partial location reload) | Only fix that touches backend; ship once frontend patterns are stable. |
| 6 | Fix 6 (undo pill) | Nice-to-have; safe to ship last. |

Deploy path: merge each commit into `main` → `git pull` on the server at `192.168.1.201` → watch for one to two shifts → if a regression appears, revert only that commit.

### Risks

| Risk | Mitigation |
|---|---|
| `localStorage` fills up (base64 photos) | Fix 4 keeps photos small; 24 h auto-expire caps total volume. |
| iOS Safari lacks `navigator.vibrate` | Silent fallback to audio beep (which iOS supports). |
| Torch API unavailable on some cameras | Feature-detect and hide the button when unsupported. |
| Compressed photos too low quality to serve as evidence | Compression params in `.env` so QA can raise them without a redeploy. |
| Partial-reload race conditions (double-submit) | Debounce `changeLocation()` and disable submit while a fetch is in flight. |
| Blade files already large (`area_checklist.blade.php` 993 lines) get larger | Extract shared JS into `public/js/inspection-*.js` files; no new Blade partials unless Fix 3 needs one. |

### Follow-ups (out of scope for this sprint)

- Full offline mode via Service Worker + IndexedDB queue (Approach 2 from brainstorming).
- Sub-projects B (Verification), C (Dashboard), D (CAR).
- Backend refactor of `InspectionController@getDepartmentStats` (currently ~500 lines with several latent bugs surfaced during the 2026-08-07 debug, including SQLite date-cast mismatch and cross-midnight edge cases).
