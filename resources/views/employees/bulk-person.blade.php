<x-app-layout>
    @section('header', 'กำหนดจุดตรวจพนักงาน')

    <div class="mb-4">
        <a href="{{ route('employees.index') }}" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i>กลับไปรายการพนักงาน
        </a>
    </div>

    <form action="{{ route('employees.bulk-person.save') }}" method="POST" id="bulkPersonForm">
        @csrf
        <div class="row">
            {{-- Left: Employees Selection --}}
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>เลือกพนักงาน</h5>
                            <small class="text-muted">เลือกพนักงานที่จะกำหนดจุดตรวจ</small>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        {{-- Select All --}}
                        <div class="mb-3 p-3 bg-primary bg-opacity-10 rounded-3 border border-primary">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllEmployees">
                                <label class="form-check-label fw-bold text-primary" for="selectAllEmployees">
                                    <i class="bi bi-check-all me-1"></i>เลือกทั้งหมดในหน้านี้ <span id="employeeCount" class="badge bg-primary ms-1"></span>
                                </label>
                            </div>
                        </div>

                        {{-- Filters --}}
                        <div class="row g-2 mb-3">
                            <div class="col-md-5">
                                <input type="text" class="form-control rounded-pill" id="employeeSearch" placeholder="🔍 ค้นหาพนักงาน...">
                            </div>
                            <div class="col-md-4">
                                <select class="form-select rounded-pill" id="departmentFilter">
                                    <option value="">ทุกแผนก</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- หากลุ่มที่จุดตรวจไม่เท่าคนอื่น เช่น ห้องแคะที่ไม่ผ่านตู้เป่าลม --}}
                            <div class="col-md-3">
                                <select class="form-select rounded-pill" id="checkpointCountFilter" title="จำนวนจุดตรวจที่กำหนดไว้">
                                    <option value="">ทุกจำนวนจุด</option>
                                    @foreach($checkpointCountOptions as $count => $headcount)
                                        <option value="{{ $count }}">{{ $count == 0 ? 'ไม่มีจุดตรวจ' : $count . ' จุด' }} ({{ $headcount }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Employee List Partial Container --}}
                        <div id="employeeListContainer">
                            @include('employees.partials.bulk-person-list', ['employees' => $employees])
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Right: Checkpoints Selection --}}
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 2rem;">
                    <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-list-check me-2 text-success"></i>เลือกจุดตรวจสุขลักษณะ</h5>
                            <p class="text-muted small mb-0">จุดตรวจที่จะถูกกำหนดให้พนักงาน</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-light text-primary border rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#quickCreateModal">
                            <i class="bi bi-plus-lg me-1"></i> เพิ่มรายการใหม่
                        </button>
                    </div>
                    <div class="card-body p-4">
                        {{-- Select All Checkpoints --}}
                        <div class="mb-3 p-3 bg-success bg-opacity-10 rounded-3 border border-success">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllCheckpoints">
                                <label class="form-check-label fw-bold text-success" for="selectAllCheckpoints">
                                    <i class="bi bi-check-all me-1"></i>เลือกทั้งหมด <span class="badge bg-success ms-1">{{ $personCheckpoints->flatten()->count() }}</span>
                                </label>
                            </div>
                        </div>

                        {{-- Checkpoints List --}}
                        <div class="checkpoint-list" style="max-height: 400px; overflow-y: auto;">
                            @forelse($personCheckpoints as $categoryName => $checkpoints)
                            <div class="mb-4">
                                <h6 class="text-uppercase text-muted fw-bold mb-3 border-bottom pb-2" style="font-size: 0.8rem; letter-spacing: 1px;">
                                    <i class="bi bi-folder me-1"></i>{{ $categoryName ?: 'ไม่มีหมวดหมู่' }}
                                </h6>
                                <div class="row g-2">
                                    @foreach($checkpoints as $cp)
                                    <div class="col-12">
                                        <div class="position-relative p-2 rounded border bg-light checkpoint-item">
                                            <div class="form-check">
                                                <input class="form-check-input checkpoint-checkbox" type="checkbox" name="checkpoint_ids[]" value="{{ $cp->id }}" id="cp_{{ $cp->id }}">
                                                <label class="form-check-label d-block cursor-pointer" for="cp_{{ $cp->id }}">
                                                    <span class="fw-bold">{{ $cp->title }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @empty
                            <div class="text-center py-5 text-muted">
                                ยังไม่มีจุดตรวจประเภท "สุขลักษณะพนักงาน"
                            </div>
                            @endforelse
                        </div>

                        <hr class="my-4">

                        {{-- Summary --}}
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">พนักงานที่เลือก:</span>
                                <strong id="selectedEmployeeCount" class="text-primary">0 คน</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">จุดตรวจที่เลือก:</span>
                                <strong id="selectedCheckpointCount" class="text-success">0 รายการ</strong>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-warning rounded-pill" id="btnReplace">
                                <i class="bi bi-arrow-repeat me-1"></i>แทนที่จุดตรวจเดิมทั้งหมด
                            </button>
                            <button type="button" class="btn btn-success rounded-pill" id="btnAdd">
                                <i class="bi bi-plus-lg me-1"></i>เพิ่มจุดตรวจใหม่ (ไม่ลบของเดิม)
                            </button>
                        </div>
                        <input type="hidden" name="mode" id="assignMode" value="add">
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('modals')
    <!-- Quick Create Modal -->
    <div class="modal fade" id="quickCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">➕ เพิ่มจุดตรวจใหม่ (Quick Add)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="quickCreateForm">
                        <div class="mb-3">
                            <label for="new_title" class="form-label fw-bold small">ชื่อจุดตรวจ (Checklist Title)</label>
                            <input type="text" class="form-control" id="new_title" required placeholder="เช่น ไม่สวมแหวน, เล็บสั้น">
                        </div>
                        <div class="mb-3">
                            <label for="new_description" class="form-label fw-bold small">คำอธิบายเพิ่มเติม</label>
                            <textarea class="form-control" id="new_description" rows="2" placeholder="อธิบายลายละเอียด..."></textarea>
                        </div>

                        <!-- Visual Standards -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small">รูปตัวอย่างมาตรฐาน (Visual Standards)</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="p-2 border rounded bg-light">
                                        <label for="new_image_good" class="form-label text-success small fw-bold mb-1"><i class="bi bi-check-circle-fill me-1"></i>Good Example</label>
                                        <input type="file" class="form-control form-control-sm" id="new_image_good" accept="image/*">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 border rounded bg-light">
                                        <label for="new_image_bad" class="form-label text-danger small fw-bold mb-1"><i class="bi bi-x-circle-fill me-1"></i>Bad Example</label>
                                        <input type="file" class="form-control form-control-sm" id="new_image_bad" accept="image/*">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Type Selection -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small">1. ระบุประเภท (Type)</label>
                            <div class="d-flex gap-2">
                                <div class="flex-fill">
                                    <input type="radio" class="btn-check" name="new_type" id="type_person" value="person" checked>
                                    <label class="btn btn-outline-primary w-100" for="type_person">
                                        <i class="bi bi-person-check me-1"></i> บุคคล (Hygiene)
                                    </label>
                                </div>
                                <div class="flex-fill">
                                    <input type="radio" class="btn-check" name="new_type" id="type_area" value="area">
                                    <label class="btn btn-outline-dark w-100" for="type_area">
                                        <i class="bi bi-gear-wide-connected me-1"></i> พื้นที่ (Area)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 bg-light p-3 rounded-3 border">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold small mb-0">2. เลือกหมวดหมู่ (Category)</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="toggleNewCategory">
                                    <label class="form-check-label small text-muted" for="toggleNewCategory">สร้างหมวดใหม่?</label>
                                </div>
                            </div>
                            
                            <!-- Select Existing -->
                            <div id="existingCategoryGroup">
                                <select class="form-select" id="new_category_id">
                                    <option value="">-- ไม่ระบุหมวดหมู่ --</option>
                                    @if(isset($categories))
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <!-- Input New -->
                            <div id="newCategoryGroup" class="d-none">
                                <input type="text" class="form-control" id="new_category_name" placeholder="ระบุชื่อหมวดหมู่ใหม่...">
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary-custom py-2 shadow-sm rounded-3">บันทึกและเลือกทันที</button>
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
        const listContainer = document.getElementById('employeeListContainer');
        const searchInput = document.getElementById('employeeSearch');
        const deptFilter = document.getElementById('departmentFilter');
        const checkpointCountFilter = document.getElementById('checkpointCountFilter');
        let debounceTimer;

        // Initial update
        updateSummary();

        // AJAX Function to fetch employees
        function fetchEmployees(url = null) {
            const currentUrl = url || "{{ route('employees.bulk-person') }}";
            const params = new URLSearchParams();
            
            if (!url) { // If filtering, add params. If paging, params are in url
                params.set('search', searchInput.value);
                params.set('department_id', deptFilter.value);
                params.set('checkpoint_count', checkpointCountFilter.value);
            }

            // If url implies paging/filtering, fetch it
            const fetchUrl = url ? url : `${currentUrl}?${params.toString()}`;

            listContainer.style.opacity = '0.5';

            fetch(fetchUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                listContainer.innerHTML = html;
                listContainer.style.opacity = '1';
                rebindEvents();
                
                // Reset select all checkbox since page changed
                document.getElementById('selectAllEmployees').checked = false;
                updateSummary();
            })
            .catch(error => {
                console.error('Error:', error);
                listContainer.style.opacity = '1';
            });
        }

        // Search Debounce
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => fetchEmployees(), 300);
        });

        // Filter Change
        deptFilter.addEventListener('change', function() {
            fetchEmployees();
        });

        checkpointCountFilter.addEventListener('change', function() {
            fetchEmployees();
        });

        // Rebind events for dynamic content
        function rebindEvents() {
            // Checkboxes
            document.querySelectorAll('.employee-checkbox').forEach(cb => {
                cb.addEventListener('change', updateSummary);
            });

            // Pagination Links
            document.querySelectorAll('#pagination-links a').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    fetchEmployees(this.href);
                });
            });
        }

        // Initial Rebind
        rebindEvents();

        // Select All Employees (Current Page Only)
        document.getElementById('selectAllEmployees').addEventListener('change', function() {
            document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = this.checked);
            updateSummary();
        });

        // Select All Checkpoints
        document.getElementById('selectAllCheckpoints').addEventListener('change', function() {
            document.querySelectorAll('.checkpoint-checkbox').forEach(cb => cb.checked = this.checked);
            updateSummary();
        });

        // Individual checkbox change (Checkpoints)
        document.querySelectorAll('.checkpoint-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSummary);
        });

        function updateSummary() {
            const employees = document.querySelectorAll('.employee-checkbox:checked').length;
            const checkpoints = document.querySelectorAll('.checkpoint-checkbox:checked').length;
            
            document.getElementById('selectedEmployeeCount').textContent = employees + ' คน';
            document.getElementById('selectedCheckpointCount').textContent = checkpoints + ' รายการ';
        }

        // Submit buttons
        document.getElementById('btnReplace').addEventListener('click', function() {
            document.getElementById('assignMode').value = 'replace';
            if (validateForm()) document.getElementById('bulkPersonForm').submit();
        });

        document.getElementById('btnAdd').addEventListener('click', function() {
            document.getElementById('assignMode').value = 'add';
            if (validateForm()) document.getElementById('bulkPersonForm').submit();
        });

        function validateForm() {
            const employees = document.querySelectorAll('.employee-checkbox:checked').length;
            const checkpoints = document.querySelectorAll('.checkpoint-checkbox:checked').length;
            
            if (employees === 0) {
                alert('กรุณาเลือกพนักงานอย่างน้อย 1 คน');
                return false;
            }
            if (checkpoints === 0) {
                alert('กรุณาเลือกจุดตรวจอย่างน้อย 1 รายการ');
                return false;
            }
            return confirm(`ยืนยันกำหนดจุดตรวจ ${checkpoints} รายการ ให้กับพนักงาน ${employees} คน?`);
        }

        // Quick Create Checkpoint JS
        const quickForm = document.getElementById('quickCreateForm');
        const toggleNewCategory = document.getElementById('toggleNewCategory');
        const existingGroup = document.getElementById('existingCategoryGroup');
        const newGroup = document.getElementById('newCategoryGroup');
        const newCategoryInput = document.getElementById('new_category_name');
        const existingCategoryConfig = document.getElementById('new_category_id');

        if(quickForm) {
            toggleNewCategory.addEventListener('change', function() {
                if(this.checked) {
                    existingGroup.classList.add('d-none');
                    newGroup.classList.remove('d-none');
                    newCategoryInput.focus();
                    existingCategoryConfig.value = "";
                } else {
                    existingGroup.classList.remove('d-none');
                    newGroup.classList.add('d-none');
                    newCategoryInput.value = "";
                }
            });

            quickForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const title = document.getElementById('new_title').value;
                const description = document.getElementById('new_description').value;

                const formData = new FormData();
                formData.append('title', title);
                formData.append('description', description);
                formData.append('type', document.querySelector('input[name="new_type"]:checked').value);
                
                if (toggleNewCategory.checked) {
                    const newName = newCategoryInput.value.trim();
                    if (!newName) {
                        alert("กรุณาระบุชื่อหมวดหมู่ใหม่");
                        return;
                    }
                    formData.append('new_category_name', newName);
                } else {
                    formData.append('category_id', existingCategoryConfig.value || "");
                }

                const fileGood = document.getElementById('new_image_good').files[0];
                const fileBad = document.getElementById('new_image_bad').files[0];
                if(fileGood) formData.append('image_good', fileGood);
                if(fileBad) formData.append('image_bad', fileBad);

                const btn = quickForm.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังบันทึก...';
                btn.disabled = true;

                fetch('{{ route("checkpoints.quick-store") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(async response => {
                    if (!response.ok) {
                        const err = await response.json().catch(() => null);
                        throw err || { message: 'เกิดข้อผิดพลาดในการเชื่อมต่อ (HTTP ' + response.status + ')' };
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('quickCreateModal'));
                        modal.hide();
                        quickForm.reset();
                        window.location.reload(); 
                    } else {
                        alert('Error: ' + JSON.stringify(data.errors));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (error.errors) {
                        let msg = '';
                        for (let key in error.errors) {
                            msg += error.errors[key].join('\n') + '\n';
                        }
                        alert('ไม่สามารถบันทึกได้:\n' + msg);
                    } else if (error.message) {
                        alert('ข้อผิดพลาด: ' + error.message);
                    } else {
                        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                    }
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            });
        }
    });
    </script>
    @endpush

    <style>
        .employee-item:has(input:checked), .checkpoint-item:has(input:checked) {
            background-color: rgba(13, 110, 253, 0.1) !important;
            border-color: #0d6efd !important;
        }
    </style>
</x-app-layout>
