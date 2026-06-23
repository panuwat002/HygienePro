<x-app-layout>
    @section('header', 'จัดการผู้ใช้งาน (User Management)')

    <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius-lg);">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1 fw-bold" style="color: var(--slate-800);">รายชื่อผู้ใช้งานระบบ</h5>
                        <p class="text-muted mb-0 small">จัดการสิทธิ์การเข้าถึงและบทบาทของผู้ใช้งาน</p>
                    </div>
                    <a href="{{ route('users.create') }}" class="btn btn-primary-custom">
                        <i class="bi bi-person-plus-fill me-2"></i>สร้างผู้ใช้งานใหม่
                    </a>
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
</x-app-layout>
