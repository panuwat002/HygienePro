<x-app-layout>
    @section('header', 'การจัดการจุดประจำการ (Locations)')

    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">กำหนดจุดหรือสถานีงานสำหรับพนักงาน</h5>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-md-end mt-3 mt-md-0">
                    {{-- Export Button --}}
                    <a href="{{ route('locations.export') }}" class="btn btn-outline-success px-3 rounded-pill shadow-sm" title="Export">
                        <i class="bi bi-download"></i><span class="d-none d-sm-inline ms-1">Export</span>
                    </a>
                    {{-- Import Button --}}
                    <button type="button" class="btn btn-outline-primary px-3 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal" title="Import">
                        <i class="bi bi-upload"></i><span class="d-none d-sm-inline ms-1">Import</span>
                    </button>
                    <a href="{{ route('locations.bulk-map') }}" class="btn btn-warning px-3 rounded-pill shadow-sm" title="Bulk Assign">
                        <i class="bi bi-diagram-3"></i><span class="d-none d-sm-inline ms-1">Bulk Assign</span>
                    </a>
                    <a href="{{ route('locations.create') }}" class="btn btn-primary px-4 bg-gradient shadow-sm rounded-pill" title="เพิ่มจุดประจำการ">
                        <i class="bi bi-geo-alt-fill"></i><span class="d-none d-sm-inline ms-2">เพิ่มจุดประจำการ</span>
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
                                <th class="ps-4 py-3 text-muted fw-bold">ชื่อจุดประจำการ</th>
                                <th class="py-3 text-muted fw-bold">คำอธิบาย</th>
                                <th class="py-3 text-muted fw-bold">จำนวนจุดตรวจ</th>
                                <th class="pe-4 py-3 text-muted fw-bold text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($locations as $location)
                            <tr>
                                <td class="ps-4" data-label="ชื่อจุดประจำการ">
                                    <span class="fw-bold text-dark">{{ $location->location_name }}</span>
                                </td>
                                <td data-label="คำอธิบาย">
                                    <span class="text-secondary small">{{ $location->description ?? '-' }}</span>
                                </td>
                                <td data-label="จำนวนจุดตรวจ">
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill border border-primary-opacity-25">
                                        <i class="bi bi-list-check me-1"></i> {{ $location->checkpoints_count }} จุด
                                    </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                        <a href="{{ route('locations.map', $location->id) }}" class="btn btn-primary btn-sm px-3 border-end" title="ตั้งค่าจุดตรวจ">
                                            <i class="bi bi-diagram-3"></i>
                                        </a>
                                        <a href="{{ route('locations.edit', $location->id) }}" class="btn btn-light btn-sm px-3 border-end">
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </a>
                                        <form action="{{ route('locations.destroy', $location->id) }}" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบจุดนี้?')">
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
                                    <i class="bi bi-geo display-6 mb-3 d-block"></i>
                                    ยังไม่มีข้อมูลจุดประจำการ
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Import Modal --}}
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="importModalLabel">
                        <i class="bi bi-upload me-2 text-primary"></i>นำเข้าข้อมูลจุดประจำการ
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('locations.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info border-0 bg-info bg-opacity-10">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>รูปแบบไฟล์:</strong> Excel (.xlsx, .xls) หรือ CSV
                            <br><small class="text-muted">คอลัมน์: ชื่อจุดประจำการ, คำอธิบาย</small>
                        </div>
                        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 small">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            หากมีจุดประจำการที่ชื่อซ้ำกัน ระบบจะอัปเดตข้อมูลของจุดเดิมแทน
                        </div>
                        <div class="mb-3">
                            <label for="locImportFile" class="form-label">เลือกไฟล์</label>
                            <input type="file" class="form-control" id="locImportFile" name="file" accept=".xlsx,.xls,.csv" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <a href="{{ route('locations.export') }}" class="btn btn-outline-secondary rounded-pill px-3 me-auto">
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

