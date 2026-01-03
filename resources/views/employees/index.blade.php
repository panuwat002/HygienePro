<x-app-layout>
    @section('header', 'จัดการข้อมูลพนักงาน (Employees)')

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-1">รายชื่อพนักงานทั้งหมด</h5>
                        <p class="text-muted mb-0 small">จัดการข้อมูลพนักงาน / สร้าง QR Code / นำเข้า-ส่งออก Excel</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('employees.export') }}" class="btn btn-outline-success shadow-sm">
                            <i class="bi bi-file-earmark-excel me-2"></i> Export
                        </a>
                        <button type="button" class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="bi bi-file-earmark-arrow-up me-2"></i> Import
                        </button>
                        <a href="{{ route('employees.create') }}" class="btn btn-primary-custom shadow-sm">
                            <i class="bi bi-person-plus-fill me-2"></i> เพิ่มพนักงานใหม่
                        </a>
                    </div>
                </div>
            </div>

            <!-- Import Modal -->
            <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content rounded-4 border-0">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold">นำเข้าข้อมูลพนักงาน (Excel)</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <form action="{{ route('employees.import') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label text-muted">เลือกไฟล์ .xlsx, .xls</label>
                                    <input type="file" name="file" class="form-control" required>
                                </div>
                                <div class="alert alert-light border small text-muted">
                                    <strong>รูปแบบไฟล์ (เรียงตามคอลัมน์):</strong><br>
                                    1. รหัสพนักงาน (จำเป็น)<br>
                                    2. คำนำหน้า<br>
                                    3. ชื่อจริง (จำเป็น)<br>
                                    4. นามสกุล<br>
                                    5. แผนก<br>
                                    6. ระดับ<br>
                                    <em class="text-danger">* แนะนำให้กด Export ไฟล์ออกมาแก้ไขแล้วนำเข้ากลับเข้าไป</em>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary-custom">นำเข้าข้อมูล</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-muted fw-bold">พนักงาน</th>
                                    <th class="py-3 text-muted fw-bold text-center">ระดับ</th>
                                    <th class="py-3 text-muted fw-bold">รหัสพนักงาน</th>
                                    <th class="py-3 text-muted fw-bold">แผนก</th>
                                    <th class="py-3 text-muted fw-bold text-center">กะ / จุดประจำการ</th>
                                    <th class="py-3 text-muted fw-bold">QR Hash</th>
                                    <th class="pe-4 py-3 text-muted fw-bold text-end">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($employees as $employee)
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-3 object-fit-cover" style="width:40px; height:40px; overflow:hidden;">
                                                @if($employee->profile_image)
                                                    <img src="{{ $employee->profile_image }}" class="w-100 h-100" style="object-fit: cover;">
                                                @else
                                                    <span class="fw-bold text-secondary">{{ substr($employee->fname, 0, 1) }}</span>
                                                @endif
                                            </div>
                                            <div>
                                                <span class="fw-bold text-dark d-block">{{ $employee->fullname }}</span>
                                                <small class="text-muted">{{ $employee->prefix }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">{{ $employee->level ?? '-' }}</span>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ $employee->employee_id }}</span></td>
                                    <td class="text-secondary">{{ $employee->department->dept_name ?? '-' }}</td>
                                    <td class="text-center">
                                        <div class="d-flex flex-column gap-1 align-items-center">
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small" style="padding: 0.25rem 0.5rem; font-size: 0.7rem;">{{ $employee->shift->shift_name ?? '-' }}</span>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small" style="padding: 0.25rem 0.5rem; font-size: 0.7rem;">{{ $employee->location->location_name ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm" style="width: 150px;">
                                            <input type="text" class="form-control bg-light" value="{{ $employee->qr_code_hash }}" readonly>
                                            <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('{{ $employee->qr_code_hash }}')">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                                <li><a class="dropdown-item" href="{{ route('employees.edit', $employee->id) }}"><i class="bi bi-pencil me-2 text-warning"></i> แก้ไข</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="{{ route('employees.destroy', $employee->id) }}" method="POST" onsubmit="return confirm('ยืนยันการลบพนักงาน?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i> ลบ</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-people fs-1 d-block mb-3"></i>
                                        ยังไม่มีข้อมูลพนักงาน
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
