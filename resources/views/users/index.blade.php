<x-app-layout>
    @section('header', 'จัดการผู้ใช้งาน')

    <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius-lg);">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1 fw-bold" style="color: var(--slate-800);">รายชื่อผู้ใช้งานระบบ</h5>
                        <p class="text-muted mb-0 small">จัดการสิทธิ์การเข้าถึงและบทบาทของผู้ใช้งาน</p>
                    </div>
                    <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="bi bi-person-plus-fill me-2"></i>สร้างผู้ใช้งานใหม่
                    </button>
                </div>
            </div>
        </div>

        <!-- Role Information -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm" style="background: var(--slate-50); border-radius: var(--radius-lg);">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2" style="color: var(--primary);"></i>สิทธิ์การใช้งาน (Role Permissions)</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <span class="badge-status fail mb-2 d-inline-block">Admin</span>
                            <p class="small mb-0" style="color: var(--slate-500);">ดูแลระบบทั้งหมด, จัดการผู้ใช้, แก้ไข Master Data</p>
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-primary mb-2">Manager</span>
                            <p class="small mb-0" style="color: var(--slate-500);">ดูรายงานระดับสูง, อนุมัติการทวนสอบ</p>
                        </div>
                        <div class="col-md-3">
                            <span class="badge-status reclean mb-2 d-inline-block">Supervisor</span>
                            <p class="small mb-0" style="color: var(--slate-500);">ทำหน้าที่ตรวจ, ทวนสอบเบื้องต้น</p>
                        </div>
                        <div class="col-md-3">
                            <span class="badge-status pending mb-2 d-inline-block">Staff</span>
                            <p class="small mb-0" style="color: var(--slate-500);">ผู้ใช้งานทั่วไป (View Only)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="table-modern">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">ชื่อ-นามสกุล</th>
                                    <th>รหัสพนักงาน</th>
                                    <th>อีเมล</th>
                                    <th>ตำแหน่ง (Role)</th>
                                    <th>แผนก</th>
                                    <th>วันที่สร้าง</th>
                                    <th class="text-end pe-4">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($users as $user)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-circle p-2 me-3">
                                                    <i class="bi bi-person-fill text-secondary"></i>
                                                </div>
                                                <span class="fw-bold">{{ $user->name }}</span>
                                            </div>
                                        </td>
                                        <td data-label="รหัสพนักงาน">{{ $user->employee_code ?: '-' }}</td>
                                        <td data-label="อีเมล" style="word-break: break-all;">{{ $user->email ?: '-' }}</td>
                                        <td data-label="ตำแหน่ง (Role)">
                                            @if($user->role === 'admin')
                                                <span class="badge bg-danger">Admin</span>
                                            @elseif($user->role === 'manager')
                                                <span class="badge bg-primary">Manager</span>
                                            @elseif($user->role === 'supervisor')
                                                <span class="badge bg-info text-dark">Supervisor</span>
                                            @else
                                                <span class="badge bg-secondary">Staff</span>
                                            @endif
                                        </td>
                                        {{-- แผนกเป็นตัวตัดสินว่าใครเห็นข้อมูลของใคร คนที่ไม่มีแผนกจะมองไม่เห็นรายงานเลย
                                             จึงต้องเห็นได้จากหน้านี้ ไม่ต้องเปิดหน้าแก้ไขทีละคน --}}
                                        <td data-label="แผนก">
                                            @if($user->department)
                                                <span class="text-dark">{{ $user->department->dept_name }}</span>
                                                <small class="text-muted d-block">{{ $user->department->dept_code }}</small>
                                            @elseif($user->role === 'admin')
                                                <span class="text-muted small">ทุกแผนก (Admin)</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>ไม่มีแผนก
                                                </span>
                                                <small class="text-danger d-block">มองไม่เห็นรายงาน</small>
                                            @endif
                                        </td>
                                        <td data-label="วันที่สร้าง" class="text-muted small">{{ $user->created_at->format('d/m/Y') }}</td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-link text-primary">
                                                <i class="bi bi-pencil-square fs-5"></i>
                                            </a>
                                            <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline-block" onsubmit="return confirm('ยืนยันการลบผู้ใช้งานนี้?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-link text-danger" {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                                                    <i class="bi bi-trash fs-5"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($users->hasPages())
                        <div class="p-4 border-top">
                            {{ $users->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('modals')
    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">สร้างผู้ใช้งานใหม่ (Quick Add)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="{{ route('users.store') }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">ชื่อ-นามสกุล (Full Name)</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">รหัสพนักงาน (Employee Code)</label>
                                <input type="text" name="employee_code" class="form-control @error('employee_code') is-invalid @enderror" value="{{ old('employee_code') }}">
                                @error('employee_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text text-muted small">ตัวเลือก: สามารถใช้ login เข้าสู่ระบบแทนอีเมลได้</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">อีเมล (Email)</label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">สังกัดแผนก (Department)</label>
                                <select name="department_id" class="form-select @error('department_id') is-invalid @enderror">
                                    <option value="">-- ไม่ระบุ / ส่วนกลาง --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->dept_name }} ({{ $dept->dept_code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('department_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">สิทธิ์การใช้งาน (Role)</label>
                                <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                                    <option value="">-- เลือกสิทธิ์ --</option>
                                    <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin (ผู้ดูแลระบบ)</option>
                                    <option value="manager" {{ old('role') == 'manager' ? 'selected' : '' }}>Manager (ผู้จัดการ)</option>
                                    <option value="supervisor" {{ old('role') == 'supervisor' ? 'selected' : '' }}>Supervisor (หัวหน้างาน)</option>
                                    <option value="staff" {{ old('role') == 'staff' ? 'selected' : '' }}>Staff (พนักงานทั่วไป)</option>
                                </select>
                                @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">รหัสผ่าน (Password)</label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">ยืนยันรหัสผ่าน (Confirm Password)</label>
                                <input type="password" name="password_confirmation" class="form-control" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary-custom px-4">บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endpush

    @if($errors->any())
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('createUserModal'));
                modal.show();
            });
        </script>
        @endpush
    @endif

</x-app-layout>
