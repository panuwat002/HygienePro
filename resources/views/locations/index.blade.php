<x-app-layout>
    @section('header', 'จัดการจุดประจำการ')

    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">กำหนดจุดหรือสถานีงานสำหรับพนักงาน</h5>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-md-end mt-3 mt-md-0">
                    {{-- Bulk Actions Dropdown --}}
                    <div class="dropdown d-none" id="bulkActionsBtnGroup">
                        <button class="btn btn-primary-custom shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-ui-checks-grid"></i> <span class="d-none d-sm-inline ms-1">จัดการที่เลือก (<span id="bulkCount">0</span>)</span>
                        </button>
                        <ul class="dropdown-menu border-0 shadow">
                            <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="submitBulkDelete()"><i class="bi bi-trash me-2"></i> ลบข้อมูลที่เลือก</a></li>
                        </ul>
                    </div>
                    <form id="bulkDeleteForm" action="{{ route('locations.bulk-delete') }}" method="POST" class="d-none">
                        @csrf
                        <div id="bulkDeleteHiddenInputs"></div>
                    </form>

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
                                <th class="ps-4 pe-2 py-3" style="width: 40px;">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                    </div>
                                </th>
                                <th class="py-3 text-muted fw-bold">ชื่อจุดประจำการ</th>
                                <th class="py-3 text-muted fw-bold">คำอธิบาย</th>
                                <th class="py-3 text-muted fw-bold">ข้อมูลจุดประจำการ</th>
                                <th class="pe-4 py-3 text-muted fw-bold text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($locations as $location)
                            <tr>
                                <td class="ps-4 pe-2">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input location-checkbox" type="checkbox" value="{{ $location->id }}">
                                    </div>
                                </td>
                                <td data-label="ชื่อจุดประจำการ">
                                    <span class="fw-bold text-dark">{{ $location->location_name }}</span>
                                </td>
                                <td data-label="คำอธิบาย">
                                    <span class="text-secondary small">{{ $location->description ?? '-' }}</span>
                                </td>
                                <td data-label="ข้อมูลจุด">
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill border border-primary-opacity-25">
                                            <i class="bi bi-list-check me-1"></i> {{ $location->checkpoints_count }} จุดตรวจ
                                        </span>
                                        @if($location->machines_count > 0)
                                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill border border-success-opacity-25">
                                                <i class="bi bi-gear-fill me-1"></i> {{ $location->machines_count }} เครื่องจักร
                                            </span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill border border-secondary-opacity-25">
                                                <i class="bi bi-gear me-1"></i> ไม่มีเครื่องจักร
                                            </span>
                                        @endif
                                    </div>
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
                                <td colspan="5" class="py-5 text-center text-muted">
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

    @push('modals')
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
    @endpush

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.location-checkbox');
            const bulkActionsBtnGroup = document.getElementById('bulkActionsBtnGroup');
            const bulkCount = document.getElementById('bulkCount');

            function getSelectedIds() {
                return Array.from(document.querySelectorAll('.location-checkbox:checked')).map(cb => cb.value);
            }

            function updateBulkBtn() {
                const selected = getSelectedIds();
                if (selected.length > 0) {
                    bulkActionsBtnGroup.classList.remove('d-none');
                    bulkCount.innerText = selected.length;
                } else {
                    bulkActionsBtnGroup.classList.add('d-none');
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(cb => {
                        cb.checked = this.checked;
                    });
                    updateBulkBtn();
                });
            }

            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    updateBulkBtn();
                    if (selectAll) {
                        selectAll.checked = document.querySelectorAll('.location-checkbox:checked').length === checkboxes.length;
                    }
                });
            });

            window.submitBulkDelete = function() {
                const selected = getSelectedIds();
                if (selected.length === 0) return;
                
                if (confirm('ยืนยันการลบจุดประจำการที่เลือกจำนวน ' + selected.length + ' รายการ? ข้อมูลที่ลบจะไม่สามารถกู้คืนได้')) {
                    const container = document.getElementById('bulkDeleteHiddenInputs');
                    container.innerHTML = '';
                    selected.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'location_ids[]';
                        input.value = id;
                        container.appendChild(input);
                    });
                    document.getElementById('bulkDeleteForm').submit();
                }
            };
        });
    </script>
    @endpush
</x-app-layout>
