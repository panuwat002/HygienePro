<x-app-layout>
    @section('header', 'ตั้งค่า Approval Flow')

    <div class="row">
        <!-- Flow Info Header -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius-lg);">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1 fw-bold" style="color: var(--slate-800);">
                            <i class="bi bi-diagram-3 me-2 text-primary"></i>{{ $flow->name }}
                        </h5>
                        <p class="text-muted mb-0 small">
                            เป้าหมาย: <code class="bg-light px-2 py-1 rounded text-danger">{{ $flow->target_model }}</code>
                            <span class="mx-2">|</span>
                            สถานะ: 
                            @if($flow->is_active)
                                <span class="badge bg-success rounded-pill px-2 py-1">Active</span>
                            @else
                                <span class="badge bg-secondary rounded-pill px-2 py-1">Inactive</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="col-12 mb-4">
                <div class="alert alert-success border-0 shadow-sm rounded-3 d-flex align-items-center">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div>{{ session('success') }}</div>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="col-12 mb-4">
                <div class="alert alert-danger border-0 shadow-sm rounded-3 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div>{{ session('error') }}</div>
                </div>
            </div>
        @endif

        <!-- Current Steps -->
        <div class="col-12 mb-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-list-ol me-2" style="color: var(--primary);"></i>ลำดับขั้นตอนการอนุมัติ (Current Steps)</h6>
            
            <div class="table-modern">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">ลำดับที่ (Step)</th>
                                    <th>กำหนดตามบทบาท (Role)</th>
                                    <th>กำหนดตามผู้ใช้งาน (User)</th>
                                    <th class="text-end pe-4">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($flow->steps as $step)
                                    <tr>
                                        <td class="ps-4">
                                            <span class="badge bg-primary rounded-pill px-3 py-2 fs-6 shadow-sm">{{ $step->step_order }}</span>
                                        </td>
                                        <td data-label="Role">
                                            @if($step->role)
                                                <span class="badge bg-info text-dark px-3 py-2 rounded-3 mb-1"><i class="bi bi-shield-lock me-1"></i> {{ ucfirst($step->role) }}</span>
                                                @php
                                                    $roleUsers = $usersByRole[$step->role] ?? collect();
                                                @endphp
                                                <div class="small text-muted mt-1">
                                                    <i class="bi bi-people"></i> มีผู้ใช้งาน {{ $roleUsers->count() }} คน
                                                    @if($roleUsers->count() > 0)
                                                        <br><span style="font-size: 0.75rem;">(เช่น {{ $roleUsers->take(3)->pluck('name')->join(', ') }}{{ $roleUsers->count() > 3 ? '...' : '' }})</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td data-label="User">
                                            @if($step->user)
                                                <span class="badge bg-secondary px-3 py-2 rounded-3"><i class="bi bi-person me-1"></i> {{ $step->user->name }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            <form action="{{ route('admin.approvals.steps.destroy', $step->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm('ยืนยันการลบขั้นตอนนี้?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-link text-danger">
                                                    <i class="bi bi-trash fs-5"></i> ลบ
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="text-muted opacity-50 mb-3"><i class="bi bi-inbox fs-1"></i></div>
                                            <p class="text-muted fw-semibold mb-0">ยังไม่มีการตั้งขั้นตอน ระบบจะอนุมัติอัตโนมัติหากไม่มีผู้ผูกมัด</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add New Step -->
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="background: var(--slate-50); border-radius: var(--radius-lg);">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-4"><i class="bi bi-plus-circle me-2" style="color: var(--primary);"></i>เพิ่มขั้นตอนใหม่ (Add New Step)</h6>
                    
                    <form action="{{ route('admin.approvals.steps.store', $flow->id) }}" method="POST">
                        @csrf
                        <div class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label fw-semibold small text-muted">ลำดับที่ (Step Order)</label>
                                <input type="number" name="step_order" class="form-control" value="{{ $flow->steps->count() + 1 }}" required min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small text-muted">กำหนดตามบทบาท (Role)</label>
                                <select name="role" id="roleSelect" class="form-select">
                                    <option value="">-- ไม่ระบุบทบาท (ใช้ผู้ใช้เฉพาะเจาะจง) --</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                                    @endforeach
                                </select>
                                <div id="roleHelperText" class="mt-2 small d-none p-2 bg-white border rounded shadow-sm">
                                    <div class="fw-bold text-primary mb-1" style="font-size: 0.8rem;"><i class="bi bi-people-fill me-1"></i> พบพนักงาน <span id="roleCount">0</span> คน ใน Role นี้</div>
                                    <div id="roleNames" class="text-muted" style="font-size: 0.75rem; word-break: break-word; line-height: 1.4;"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small text-muted">กำหนดตามผู้ใช้งาน (Specific User)</label>
                                <select name="user_id" class="form-select">
                                    <option value="">-- ไม่ระบุผู้ใช้ (ใช้ตามบทบาท) --</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ ucfirst($user->role ?? 'No Role') }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary-custom w-100">
                                    <i class="bi bi-plus-lg me-1"></i> เพิ่ม
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 text-muted small">
                            <i class="bi bi-info-circle me-1"></i> ต้องเลือกระบุอย่างน้อยหนึ่งอย่าง (บทบาท หรือ ผู้ใช้งาน)
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const usersByRole = @json($usersByRole);
        const roleSelect = document.getElementById('roleSelect');
        const helper = document.getElementById('roleHelperText');
        
        if (roleSelect) {
            roleSelect.addEventListener('change', function() {
                const role = this.value;
                
                if (role && usersByRole[role]) {
                    const users = usersByRole[role];
                    document.getElementById('roleCount').innerText = users.length;
                    
                    const roleNamesDiv = document.getElementById('roleNames');
                    if (users.length > 0) {
                        const names = users.map(u => u.name).join(', ');
                        roleNamesDiv.innerText = 'รายชื่อ: ' + names;
                        roleNamesDiv.classList.remove('text-danger');
                        roleNamesDiv.classList.add('text-muted');
                    } else {
                        roleNamesDiv.innerText = '⚠️ ยังไม่มีพนักงานถูกกำหนดให้อยู่ใน Role นี้เลย (เมื่อกดส่ง จะไม่มีใครเห็นคำขอนี้)';
                        roleNamesDiv.classList.remove('text-muted');
                        roleNamesDiv.classList.add('text-danger');
                    }
                    
                    helper.classList.remove('d-none');
                } else {
                    helper.classList.add('d-none');
                }
            });
        }
    });
</script>
@endpush
