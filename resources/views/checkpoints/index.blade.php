<x-app-layout>
    @section('header', 'จัดการจุดตรวจ')

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">รายการคำถามหรือจุดตรวจทางสุขอนามัยในระบบ</h5>
                </div>
                <div class="d-flex gap-2">
                    {{-- Export Button --}}
                    <a href="{{ route('checkpoints.export') }}" class="btn btn-outline-success px-3 rounded-pill shadow-sm">
                        <i class="bi bi-download me-1"></i>Export
                    </a>
                    {{-- Import Button --}}
                    <button type="button" class="btn btn-outline-primary px-3 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="bi bi-upload me-1"></i>Import
                    </button>
                    <a href="{{ route('checkpoints.create') }}" class="btn btn-primary px-4 bg-gradient shadow-sm rounded-pill">
                        <i class="bi bi-plus-lg me-2"></i>เพิ่มจุดตรวจใหม่
                    </a>
                </div>
            </div>

            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <div class="table-modern">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-muted fw-bold">ชื่อจุดตรวจ</th>
                                <th class="py-3 text-muted fw-bold">หมวดหมู่</th>
                                <th class="py-3 text-muted fw-bold">คำอธิบาย</th>
                                <th class="pe-4 py-3 text-muted fw-bold text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($checkpoints as $cp)
                            <tr>
                                <td class="ps-4" data-label="ชื่อจุดตรวจ">
                                    <span class="fw-bold text-dark">{{ $cp->title }}</span>
                                </td>
                                <td data-label="หมวดหมู่">
                                    @if($cp->category)
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-opacity-25">
                                            <i class="bi bi-{{ $cp->category->icon ?? 'tag' }} me-1"></i> {{ $cp->category->name }}
                                        </span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td data-label="คำอธิบาย">
                                    <span class="text-secondary small">{{ $cp->description ?? '-' }}</span>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                        <a href="{{ route('checkpoints.edit', $cp->id) }}" class="btn btn-light btn-sm px-3 border-end">
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </a>
                                        <form action="{{ route('checkpoints.destroy', $cp->id) }}" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบจุดตรวจนี้?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-light btn-sm px-3">
                                                <i class="bi bi-trash3 text-danger"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="py-5 text-center text-muted">
                                    <i class="bi bi-list-columns-reverse display-6 mb-3 d-block"></i>
                                    ยังไม่มีข้อมูลจุดตรวจ
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('modals')
    {{-- Import Modal --}}
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="importModalLabel">
                        <i class="bi bi-upload me-2 text-primary"></i>นำเข้าข้อมูลจุดตรวจ
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('checkpoints.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info border-0 bg-info bg-opacity-10">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>รูปแบบไฟล์:</strong> Excel (.xlsx, .xls) หรือ CSV
                            <br><small class="text-muted">คอลัมน์: หมวดหมู่, ชื่อจุดตรวจ, คำอธิบาย, ประเภท (person/area), สถานะ</small>
                        </div>
                        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 small">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            หากมีจุดตรวจที่ชื่อซ้ำกัน ระบบจะอัปเดตข้อมูลของจุดเดิมแทน
                        </div>
                        <div class="mb-3">
                            <label for="cpImportFile" class="form-label">เลือกไฟล์</label>
                            <input type="file" class="form-control" id="cpImportFile" name="file" accept=".xlsx,.xls,.csv" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <a href="{{ route('checkpoints.export') }}" class="btn btn-outline-secondary rounded-pill px-3 me-auto">
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
    @endpush
</x-app-layout>

