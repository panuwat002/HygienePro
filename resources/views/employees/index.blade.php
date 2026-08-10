<x-app-layout>
    @section('header', 'จัดการพนักงาน')

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 mb-4" style="position: relative; z-index: 1020;">
                <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div>
                        <h5 class="fw-bold mb-1">รายชื่อพนักงานทั้งหมด</h5>
                        <p class="text-muted mb-0 small">จัดการข้อมูลพนักงาน / สร้าง QR Code / นำเข้า-ส่งออก Excel</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end mt-3 mt-md-0">
                        <div class="dropdown d-none" id="bulkActionsBtnGroup">
                            <button class="btn btn-primary-custom shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-ui-checks-grid"></i> <span class="d-none d-sm-inline ms-1">จัดการที่เลือก (<span id="bulkCount">0</span>)</span>
                            </button>
                            <ul class="dropdown-menu border-0 shadow">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="printSelected()"><i class="bi bi-printer me-2 text-info"></i> พิมพ์บัตรประจำตัว</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="openBulkShiftModal()"><i class="bi bi-clock me-2 text-warning"></i> เปลี่ยนกะการทำงาน</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="openBulkDepartmentModal()"><i class="bi bi-building me-2 text-primary"></i> ย้ายแผนก</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="openBulkLocationModal()"><i class="bi bi-geo-alt me-2 text-success"></i> กำหนดจุดประจำการ</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="submitBulkDelete()"><i class="bi bi-trash me-2"></i> ลบข้อมูลที่เลือก</a></li>
                            </ul>
                        </div>
                        <form id="bulkDeleteForm" action="{{ route('employees.bulk-delete') }}" method="POST" class="d-none">
                            @csrf
                            <div id="bulkDeleteHiddenInputs"></div>
                        </form>
                        <a href="{{ route('employees.bulk-person') }}" class="btn btn-outline-info shadow-sm" title="กำหนดจุดตรวจ">
                            <i class="bi bi-ui-checks"></i><span class="d-none d-sm-inline ms-1">กำหนดจุดตรวจ</span>
                        </a>
                        <a href="{{ route('employees.export') }}" class="btn btn-outline-success shadow-sm" title="Export">
                            <i class="bi bi-file-earmark-excel"></i><span class="d-none d-sm-inline ms-1">Export</span>
                        </a>
                        <button type="button" class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal" title="Import">
                            <i class="bi bi-file-earmark-arrow-up"></i><span class="d-none d-sm-inline ms-1">Import Data</span>
                        </button>
                        <button type="button" class="btn btn-outline-info shadow-sm" data-bs-toggle="modal" data-bs-target="#importSchedulesModal" title="Import กะรายวัน">
                            <i class="bi bi-calendar-range"></i><span class="d-none d-sm-inline ms-1">Import กะรายวัน</span>
                        </button>
                        <a href="{{ route('employees.create') }}" class="btn btn-primary-custom shadow-sm" title="เพิ่มพนักงานใหม่">
                            <i class="bi bi-person-plus-fill"></i><span class="d-none d-sm-inline ms-2">เพิ่มพนักงานใหม่</span>
                        </a>
                    </div>
                </div>
            </div>



            <!-- Search Form -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
                <div class="card-body p-3">
                    <form action="{{ route('employees.index') }}" method="GET" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อ, รหัสพนักงาน..." value="{{ request('search') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="department_id" class="form-select">
                                <option value="">-- ทุกแผนก --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->dept_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="shift_id" class="form-select">
                                <option value="">-- ทุกกะ --</option>
                                @foreach($shifts as $shift)
                                    <option value="{{ $shift->id }}" {{ request('shift_id') == $shift->id ? 'selected' : '' }}>{{ $shift->shift_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary-custom flex-grow-1"><i class="bi bi-search me-1"></i>ค้นหา</button>
                            @if(request()->hasAny(['search', 'department_id', 'shift_id']) && request()->anyFilled(['search', 'department_id', 'shift_id']))
                                <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary" title="ล้างการค้นหา"><i class="bi bi-x-lg"></i></a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 table-modern">
                <div class="card-body p-0">
                    <div class="table-responsive overflow-visible">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 pe-2 py-3" style="width: 40px;">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" id="selectAll">
                                        </div>
                                    </th>
                                    <th class="py-3 text-muted fw-bold">พนักงาน</th>
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
                                    <td class="ps-4 pe-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input employee-checkbox" type="checkbox" value="{{ $employee->id }}">
                                        </div>
                                    </td>
                                    <td data-label="พนักงาน">
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
                                    <td data-label="ระดับ" class="text-center">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">{{ $employee->level ?? '-' }}</span>
                                    </td>
                                    <td data-label="รหัสพนักงาน"><span class="badge bg-light text-dark border">{{ $employee->employee_id }}</span></td>
                                    <td data-label="แผนก" class="text-secondary">{{ $employee->department->dept_name ?? '-' }}</td>
                                    <td data-label="กะ / จุดประจำการ" class="text-center">
                                        <div class="d-flex flex-column gap-1 align-items-center">
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small" style="padding: 0.25rem 0.5rem; font-size: 0.7rem;">
                                                {{ $employee->today_schedule ?? ($employee->shift->shift_name ?? '-') }}
                                            </span>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small" style="padding: 0.25rem 0.5rem; font-size: 0.7rem;">{{ $employee->location->location_name ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td data-label="QR Hash">
                                        <div class="input-group input-group-sm" style="width: 150px; justify-content: flex-end;">
                                            <input type="text" class="form-control bg-light text-end" value="{{ $employee->qr_code_hash }}" readonly style="max-width: 90px; font-size: 0.8rem;">
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
                                                <li><a class="dropdown-item" href="{{ route('employees.print_card', $employee->id) }}" target="_blank"><i class="bi bi-person-badge me-2 text-info"></i> พิมพ์บัตรประจำตัว</a></li>
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
                                    <td colspan="8" class="text-center py-5 text-muted">
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
            
            <div class="mt-4">
                {{ $employees->appends(request()->query())->links() }}
            </div>
        </div>
    </div>

    @push('modals')
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
                            7. กะการทำงาน (Shift) <span class="badge bg-success">ใหม่</span><br>
                            <em class="text-danger">* แนะนำให้กด Export ไฟล์ออกมาแก้ไขแล้วนำเข้ากลับเข้าไป (สามารถแก้ไขกะการทำงานแล้วนำเข้าทับได้เลย ข้อมูลจะไม่ซ้ำซ้อน)</em>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary-custom">นำเข้าข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Import Schedules Modal -->
    <div class="modal fade" id="importSchedulesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">นำเข้าตารางกะการทำงานรายวัน (Excel)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="{{ route('employees.import_schedules') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label text-muted">เลือกไฟล์ตารางกะ .xlsx, .xls</label>
                            <input type="file" name="file" class="form-control" required>
                        </div>
                        <div class="alert alert-light border small text-muted">
                            <strong>รูปแบบไฟล์ตารางกะที่รองรับ:</strong><br>
                            - มีแถวหัวตารางเป็นวันที่ เช่น "11-7-2026", "12-7-2026"<br>
                            - มีรหัสพนักงานอยู่ในคอลัมน์แรกๆ<br>
                            - เวลาเข้า-ออกอยู่ในเซลล์เดียวกัน (ใช้ <code>Alt+Enter</code> ขึ้นบรรทัดใหม่) เช่น <code>19.00</code> กลับบรรทัด <code>04.00</code><br>
                            - วันหยุดใส่เป็นคำว่า <code>WH</code> หรือ <code>OFF</code>
                            
                            <div class="mt-3">
                                <a href="{{ route('employees.download_schedules_template') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-download me-1"></i> ดาวน์โหลดไฟล์ตัวอย่าง (Template)
                                </a>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-info text-white">นำเข้าข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Bulk Shift Modal -->
    <div class="modal fade" id="bulkShiftModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-clock text-warning me-2"></i>เปลี่ยนกะการทำงาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="{{ route('employees.bulk-shift.save') }}" method="POST" id="bulkShiftForm">
                        @csrf
                        <div id="bulkShiftHiddenInputs"></div>
                        <div class="mb-3">
                            <label class="form-label text-muted">เลือกกะการทำงานใหม่</label>
                            <select name="shift_id" class="form-select" required>
                                <option value="">-- เลือกกะ --</option>
                                @foreach($shifts as $shift)
                                    <option value="{{ $shift->id }}">{{ $shift->shift_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary-custom">บันทึกการเปลี่ยนแปลง</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Department Modal -->
    <div class="modal fade" id="bulkDepartmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-building text-primary me-2"></i>ย้ายแผนก</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="{{ route('employees.bulk-department.save') }}" method="POST" id="bulkDepartmentForm">
                        @csrf
                        <div id="bulkDepartmentHiddenInputs"></div>
                        <div class="mb-3">
                            <label class="form-label text-muted">เลือกแผนกใหม่</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">-- เลือกแผนก --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="document.getElementById('bulkDepartmentForm').submit()">บันทึก</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Location Modal -->
    <div class="modal fade" id="bulkLocationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt text-success me-2"></i>กำหนดจุดประจำการ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="{{ route('employees.bulk-location.save') }}" method="POST" id="bulkLocationForm">
                        @csrf
                        <div id="bulkLocationHiddenInputs"></div>
                        <div class="mb-3">
                            <label class="form-label text-muted">เลือกจุดประจำการใหม่</label>
                            <select name="location_id" class="form-select" required>
                                <option value="">-- เลือกจุดประจำการ --</option>
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->location_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary-custom">บันทึกการเปลี่ยนแปลง</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endpush

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.employee-checkbox');
            const bulkActionsBtnGroup = document.getElementById('bulkActionsBtnGroup');
            const bulkCount = document.getElementById('bulkCount');

            function getSelectedIds() {
                return Array.from(document.querySelectorAll('.employee-checkbox:checked')).map(cb => cb.value);
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
                        selectAll.checked = document.querySelectorAll('.employee-checkbox:checked').length === checkboxes.length;
                    }
                });
            });

            window.printSelected = function() {
                const selected = getSelectedIds();
                if (selected.length === 0) return;
                const url = '{{ route('employees.print_selected') }}?selected_ids=' + selected.join(',');
                window.open(url, '_blank');
            };

            window.submitBulkDelete = function() {
                const selected = getSelectedIds();
                if (selected.length === 0) return;
                
                if (confirm('ยืนยันการลบพนักงานที่เลือกจำนวน ' + selected.length + ' รายการ? ข้อมูลที่ลบจะไม่สามารถกู้คืนได้')) {
                    const container = document.getElementById('bulkDeleteHiddenInputs');
                    container.innerHTML = '';
                    selected.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'employee_ids[]';
                        input.value = id;
                        container.appendChild(input);
                    });
                    document.getElementById('bulkDeleteForm').submit();
                }
            };

            window.openBulkShiftModal = function() {
                const selected = getSelectedIds();
                if (selected.length === 0) return;
                
                const container = document.getElementById('bulkShiftHiddenInputs');
                container.innerHTML = '';
                selected.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'employee_ids[]';
                    input.value = id;
                    container.appendChild(input);
                });
                
                new bootstrap.Modal(document.getElementById('bulkShiftModal')).show();
            };

            window.openBulkDepartmentModal = function() {
                const selected = getSelectedIds();
                if (selected.length === 0) return;
                
                const container = document.getElementById('bulkDepartmentHiddenInputs');
                container.innerHTML = '';
                selected.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'employee_ids[]';
                    input.value = id;
                    container.appendChild(input);
                });
                
                new bootstrap.Modal(document.getElementById('bulkDepartmentModal')).show();
            };

            window.openBulkLocationModal = function() {
                const selected = getSelectedIds();
                if (selected.length === 0) return;
                
                const container = document.getElementById('bulkLocationHiddenInputs');
                container.innerHTML = '';
                selected.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'employee_ids[]';
                    input.value = id;
                    container.appendChild(input);
                });
                
                new bootstrap.Modal(document.getElementById('bulkLocationModal')).show();
            };
        });
    </script>
    @endpush
</x-app-layout>
