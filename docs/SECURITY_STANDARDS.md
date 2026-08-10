# System Security Standards (มาตรฐานความปลอดภัยของระบบ)

## 1. Authentication & Authorization (การยืนยันตัวตนและสิทธิ์การเข้าถึง)

### 1.1 Authentication
- **Requirement:** All sensitive routes must be protected by the `auth` middleware.
- **Implementation:**
    - Public routes: Login, Password Reset only.
    - Application routes: Must be wrapped in `Route::middleware(['auth'])`.

### 1.2 Role-Based Access Control (RBAC)
- **Requirement:** Access to features must be restricted based on user roles and permissions.
- **Implementation:**
    - Use Laravel Gates/Policies via middleware `can:permission-name`.
    - **Standard Permissions:**
        - `manage-master-data`: Admin only (Users, Departments, Structure).
        - `verify`: Supervisors (Verify inspection results).
        - `approve`: Managers (Final approval of sessions).
    - **Code Enforcement:** Controllers must verify ownership of resources (e.g., "Is this User the inspector for this Session?") before allowing updates.

## 2. Data Integrity & Validation (ความถูกต้องของข้อมูล)

### 2.1 Input Validation
- **Requirement:** All user inputs must be validated on the server-side.
- **Implementation:**
    - Use Laravel `FormRequest` or `request()->validate([])` in Controllers.
    - Validate foreign keys (`exists:table,id`).
    - Validate enums (`in:pass,fail`).

### 2.2 File Upload Security
- **Requirement:** Only allowed file types (Images) are strictly permitted.
- **Implementation:**
    - Validation Rule: `mimes:jpeg,png,jpg,webp|max:5120` (5MB Limit).
    - **Processing:** All uploads must be processed via `Intervention Image` to strip metadata and ensure valid image structure before storage.
    - **Storage:** Store in `storage/app/public/evidence` with generated unique filenames.

### 2.3 Session Locking (Transaction Safety)
- **Requirement:** Completed/Approved inspections must be immutable.
- **Implementation:**
    - **State Machine:** Enforce strict transitions (`In Progress` -> `Completed` -> `Verified` -> `Approved`).
    - **Locking:** Once approved (`is_locked = true`), no further edits are allowed via Controller logic.
    - **Exception:** Re-clean actions are allowed strictly on specific failed items.

## 3. Output Security (XSS Prevention)

- **Requirement:** Prevent Cross-Site Scripting (XSS).
- **Implementation:**
    - **Standard:** Use Blade `{{ $var }}` for all output.
    - **Raw Output:** Avoid `{!! $var !!}` unless content is strictly controlled (e.g., server-generated JSON for charts).
    - **Charts:** When passing data to JavaScript, ensure explicit `json_encode()` is used.

## 4. Audit & Logging (การบันทึกกิจกรรม)

- **Requirement:** Critical actions must be logged for audit trails.
- **Implementation:**
    - **User Actions:** Structure changes, User creation/deletion must be logged to `activity_logs`.
    - **Inspections:** Every inspection log captures `timestamp`, `employee_id` (inspector), and `result`.
    - **Modifications:** Changes to logs (e.g., re-clean) create a new log entry linking to the parent, preserving history.

---

## 5. Deployment Recommendations

- **HTTPS:** System must run over HTTPS in production to secure cookies and credentials.
- **Environment:**
    - `APP_DEBUG=false` in production.
    - `APP_KEY` must be rotated if compromised.
    - Database credentials must be secured in `.env`.
