<x-app-layout>
    @section('header', 'จัดการแผนก')

    <div class="card border-0 shadow-sm" style="border-radius: var(--radius-lg);">
        <div class="card-body p-4">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                <div>
                    <h5 class="fw-bold mb-1" style="color: var(--slate-800);"><i class="bi bi-building me-2" style="color: var(--primary);"></i>รายชื่อแผนกทั้งหมด</h5>
                    <p class="text-muted mb-0 small">จัดการโครงสร้างแผนกและกลุ่มพนักงาน</p>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-md-end mt-3 mt-md-0">
                    {{-- Export Button --}}
                    <a href="{{ route('departments.export') }}" class="btn btn-outline-success px-3 rounded-pill shadow-sm" title="Export">
                        <i class="bi bi-download"></i><span class="d-none d-sm-inline ms-1">Export</span>
                    </a>
                    {{-- Import Button --}}
                    <button type="button" class="btn btn-outline-primary px-3 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#deptImportModal" title="Import">
                        <i class="bi bi-upload"></i><span class="d-none d-sm-inline ms-1">Import</span>
                    </button>
                    <a href="{{ route('departments.create') }}" class="btn btn-primary-custom" title="สร้างแผนกใหม่">
                        <i class="bi bi-plus-lg"></i><span class="d-none d-sm-inline ms-2">สร้างแผนกใหม่</span>
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="table-modern">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="rounded-start ps-4">รหัสแผนก</th>
                            <th>ชื่อแผนก (Name)</th>
                            <th>หัวหน้าแผนก (Manager)</th>
                            <th>ประเภท (Visibility)</th>
                            <th>จำนวนพนักงาน</th>
                            <th class="text-end rounded-end pe-4">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($departments as $dept)
                            <tr>
                                <td class="ps-4 fw-bold text-primary" data-label="รหัสแผนก">{{ $dept->dept_code }}</td>
                                <td data-label="ชื่อแผนก" class="fw-bold text-dark">{{ $dept->dept_name }}</td>
                                <td data-label="หัวหน้าแผนก">
                                    @if($dept->manager)
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold shadow-sm" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                                {{ mb_substr($dept->manager->name, 0, 1) }}
                                            </div>
                                            <div>
                                                <span class="d-block fw-semibold text-dark" style="font-size: 0.9rem;">{{ $dept->manager->name }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted small"><i class="bi bi-dash"></i> ยังไม่กำหนด</span>
                                    @endif
                                </td>
                                <td data-label="ประเภท (Visibility)">
                                    @if($dept->visibility_type === 'global')
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">Global (ส่วนกลาง)</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3">Isolated (เฉพาะส่วน)</span>
                                    @endif
                                </td>
                                <td data-label="จำนวนพนักงาน">
                                    <span class="badge bg-light text-dark border">{{ $dept->employees->count() }} คน</span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('departments.roster', $dept->id) }}" class="btn btn-sm btn-outline-info rounded-circle me-1" title="จัดตารางงาน (Roster)">
                                        <i class="bi bi-calendar-week"></i>
                                    </a>
                                    <a href="{{ route('departments.edit', $dept->id) }}" class="btn btn-sm btn-outline-warning rounded-circle me-1" title="แก้ไข">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <form method="POST" action="{{ route('departments.destroy', $dept->id) }}" class="d-inline" onsubmit="return confirm('ยืนยันการลบแผนกนี้?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="ลบ">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-building fs-1 d-block mb-3 opacity-25"></i>
                                    ยังไม่มีข้อมูลแผนก
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $departments->links() }}
            </div>
        </div>
    </div>
</div>

    {{-- Departments Import Modal --}}
    <div class="modal fade" id="deptImportModal" tabindex="-1" aria-labelledby="deptImportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="deptImportModalLabel">
                        <i class="bi bi-upload me-2 text-primary"></i>นำเข้าข้อมูลแผนก
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('departments.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info border-0 bg-info bg-opacity-10">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>รูปแบบไฟล์:</strong> Excel (.xlsx, .xls) หรือ CSV
                            <br><small class="text-muted">คอลัมน์: รหัสแผนก, ชื่อแผนก, คำอธิบาย, ประเภทการมองเห็น (global/isolated)</small>
                        </div>
                        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 small">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            หากมีรหัสแผนกซ้ำกัน ระบบจะอัปเดตข้อมูลของแผนกเดิมแทน
                        </div>
                        <div class="mb-3">
                            <label for="deptImportFile" class="form-label">เลือกไฟล์</label>
                            <input type="file" class="form-control" id="deptImportFile" name="file" accept=".xlsx,.xls,.csv" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <a href="{{ route('departments.export') }}" class="btn btn-outline-secondary rounded-pill px-3 me-auto">
                            <i class="bi bi-download me-1"></i>ดาวน์โหลด Template
                        </a>
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="bi bi-upload me-1"></i>นำเข้าข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
