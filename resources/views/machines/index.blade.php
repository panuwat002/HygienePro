<x-app-layout>
    @section('header', 'จัดการเครื่องจักร')

    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">รายการเครื่องจักรในระบบ</h5>
                    <p class="text-muted small mb-0">Manage machines for specific inspection points</p>
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
                    <form id="bulkDeleteForm" action="{{ route('machines.bulk-delete') }}" method="POST" class="d-none">
                        @csrf
                        <div id="bulkDeleteHiddenInputs"></div>
                    </form>

                    {{-- Export Button --}}
                    <a href="{{ route('machines.export') }}" class="btn btn-outline-success px-3 rounded-pill shadow-sm" title="Export">
                        <i class="bi bi-download"></i><span class="d-none d-sm-inline ms-1">Export</span>
                    </a>
                    {{-- Import Button --}}
                    <button type="button" class="btn btn-outline-primary px-3 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal" title="Import">
                        <i class="bi bi-upload"></i><span class="d-none d-sm-inline ms-1">Import</span>
                    </button>
                    {{-- Bulk Assign Button --}}
                    <a href="{{ route('machines.bulk-map') }}" class="btn btn-warning px-3 rounded-pill shadow-sm" title="Bulk Assign">
                        <i class="bi bi-diagram-3"></i><span class="d-none d-sm-inline ms-1">Bulk Assign</span>
                    </a>
                    <a href="{{ route('machines.create') }}" class="btn btn-primary px-4 bg-gradient shadow-sm rounded-pill" title="เพิ่มเครื่องจักรใหม่">
                        <i class="bi bi-gear-wide-connected"></i><span class="d-none d-sm-inline ms-2">เพิ่มเครื่องจักรใหม่</span>
                    </a>
                </div>
            </div>

            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
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
                                <th class="py-3 text-muted fw-bold">ชื่อเครื่องจักร / พื้นที่ย่อย</th>
                                <th class="py-3 text-muted fw-bold">สังกัด (Location)</th>
                                <th class="py-3 text-muted fw-bold text-center">สถานะ</th>
                                <th class="pe-4 py-3 text-muted fw-bold text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($machines as $machine)
                            <tr>
                                <td class="ps-4 pe-2">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input machine-checkbox" type="checkbox" value="{{ $machine->id }}">
                                    </div>
                                </td>
                                <td data-label="ชื่อเครื่องจักร / พื้นที่ย่อย">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-3 bg-light d-flex justify-content-center align-items-center border overflow-hidden me-3 flex-shrink-0" style="width: 50px; height: 50px;">
                                            @if($machine->image)
                                                <img src="{{ asset('storage/' . $machine->image) }}" class="w-100 h-100 object-fit-cover" alt="Machine">
                                            @else
                                                <i class="bi bi-gear text-secondary fs-5"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark d-block text-start">{{ $machine->name }}</span>
                                            <small class="text-muted d-block text-start">{{ Str::limit($machine->description, 30) }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="สังกัด (Location)">
                                    <span class="badge bg-info bg-opacity-10 text-info fw-normal">
                                        <i class="bi bi-geo-alt me-1"></i> {{ $machine->location->location_name ?? 'Unassigned' }}
                                    </span>
                                </td>
                                <td data-label="สถานะ" class="text-center">
                                    @if($machine->is_active)
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2">Active</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2">Inactive</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                        <a href="{{ route('machines.map', $machine->id) }}" class="btn btn-primary btn-sm px-3 border-end" title="กำหนดจุดตรวจ">
                                            <i class="bi bi-diagram-3"></i>
                                        </a>
                                        <a href="{{ route('machines.edit', $machine->id) }}" class="btn btn-light btn-sm px-3 border-end">
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </a>
                                        <form action="{{ route('machines.destroy', $machine->id) }}" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันลบเครื่องจักรนี้? ข้อมูลการตรวจที่เกี่ยวข้องอาจได้รับผลกระทบ')">
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
                                <td colspan="6" class="py-5 text-center text-muted">
                                    <i class="bi bi-gear-wide display-6 mb-3 d-block opacity-25"></i>
                                    ยังไม่มีข้อมูลเครื่องจักร
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($machines->hasPages())
                    <div class="card-footer bg-white border-0 py-3 mt-3 shadow-sm rounded">
                        {{ $machines->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('modals')
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="importModalLabel">
                        <i class="bi bi-upload me-2 text-primary"></i>นำเข้าข้อมูลเครื่องจักร
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('machines.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info border-0 bg-info bg-opacity-10">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>รูปแบบไฟล์:</strong> Excel (.xlsx, .xls) หรือ CSV
                            <br><small class="text-muted">คอลัมน์: รหัส, ชื่อ, ที่ตั้ง, รายละเอียด, สถานะ</small>
                        </div>
                        <div class="mb-3">
                            <label for="importFile" class="form-label">เลือกไฟล์</label>
                            <input type="file" class="form-control" id="importFile" name="file" accept=".xlsx,.xls,.csv" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
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
            const checkboxes = document.querySelectorAll('.machine-checkbox');
            const bulkActionsBtnGroup = document.getElementById('bulkActionsBtnGroup');
            const bulkCount = document.getElementById('bulkCount');

            function getSelectedIds() {
                return Array.from(document.querySelectorAll('.machine-checkbox:checked')).map(cb => cb.value);
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
                        selectAll.checked = document.querySelectorAll('.machine-checkbox:checked').length === checkboxes.length;
                    }
                });
            });

            window.submitBulkDelete = function() {
                const selected = getSelectedIds();
                if (selected.length === 0) return;
                
                if (confirm('ยืนยันการลบเครื่องจักรที่เลือกจำนวน ' + selected.length + ' รายการ? ข้อมูลที่ลบจะไม่สามารถกู้คืนได้')) {
                    const container = document.getElementById('bulkDeleteHiddenInputs');
                    container.innerHTML = '';
                    selected.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'machine_ids[]';
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
