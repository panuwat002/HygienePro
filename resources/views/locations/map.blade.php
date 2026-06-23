<x-app-layout>
    @section('header', "แผงผังจุดตรวจ: $location->location_name")

    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="{{ route('locations.index') }}" class="text-decoration-none text-muted">
                    <i class="bi bi-arrow-left me-1"></i>กลับไปรายการจุดประจำการ
                </a>
            </div>

            <form action="{{ route('locations.map.save', $location->id) }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0">เลือกจุดตรวจสำหรับจุดประจำการนี้</h5>
                                <div class="d-flex gap-2">
                                     <button type="button" class="btn btn-light text-primary border rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#quickCreateModal">
                                        <i class="bi bi-plus-lg me-1"></i> เพิ่มรายการใหม่
                                    </button>
                                    <button type="submit" class="btn btn-primary px-4 rounded-pill shadow-sm">
                                        <i class="bi bi-save me-2"></i>บันทึกการตั้งค่า
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                {{-- Tabs Navigation --}}
                                <ul class="nav nav-pills nav-fill mb-4 bg-light rounded-pill p-1" id="checkpointTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link rounded-pill active" id="person-tab" data-bs-toggle="pill" data-bs-target="#person-panel" type="button" role="tab">
                                            <i class="bi bi-person-check me-1"></i>ตรวจสุขลักษณะพนักงาน
                                            <span class="badge bg-primary ms-1">{{ $allCheckpoints->flatten()->where('type', 'person')->count() }}</span>
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link rounded-pill" id="area-tab" data-bs-toggle="pill" data-bs-target="#area-panel" type="button" role="tab">
                                            <i class="bi bi-geo-alt me-1"></i>ตรวจพื้นที่/อุปกรณ์
                                            <span class="badge bg-dark ms-1">{{ $allCheckpoints->flatten()->where('type', 'area')->count() }}</span>
                                        </button>
                                    </li>
                                </ul>

                                {{-- Tab Content --}}
                                <div class="tab-content" id="checkpointTabsContent">
                                    {{-- Person Tab --}}
                                    <div class="tab-pane fade show active" id="person-panel" role="tabpanel">
                                        @php
                                            $personCheckpoints = $allCheckpoints->map(function($categoryCheckpoints, $categoryName) {
                                                return collect($categoryCheckpoints)->where('type', 'person');
                                            })->filter(function($cp) { return $cp->count() > 0; });
                                        @endphp
                                        
                                        @forelse($personCheckpoints as $categoryName => $checkpoints)
                                        <div class="mb-4 last-child-mb-0">
                                            <h6 class="text-uppercase text-muted fw-bold mb-3 border-bottom pb-2" style="font-size: 0.8rem; letter-spacing: 1px;">
                                                <i class="bi bi-folder me-1"></i>{{ $categoryName ?: 'ไม่มีหมวดหมู่' }}
                                            </h6>
                                            <div class="row g-3">
                                                @foreach($checkpoints as $cp)
                                                <div class="col-md-6">
                                                    <div class="position-relative p-3 rounded-3 border h-100 hover-border {{ in_array($cp->id, $selectedCheckpoints) ? 'bg-primary-subtle border-primary' : 'bg-light border-transparent' }}">
                                                        <div class="position-absolute top-0 end-0 p-2 d-flex gap-1" style="z-index: 10;">
                                                            <button type="button" class="btn btn-sm bg-white border text-muted shadow-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" 
                                                                onclick="openEditModal({{ $cp->id }}, '{{ addslashes($cp->title) }}', '{{ addslashes($cp->description) }}', '{{ $cp->category_id }}', event)" title="แก้ไข">
                                                                <i class="bi bi-pencil" style="font-size: 0.7rem;"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm bg-white border text-danger shadow-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" 
                                                                onclick="deleteCheckpoint({{ $cp->id }}, event)" title="ลบ">
                                                                <i class="bi bi-trash" style="font-size: 0.7rem;"></i>
                                                            </button>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input ms-0 me-2" type="checkbox" name="checkpoint_ids[]" value="{{ $cp->id }}" id="cp_{{ $cp->id }}" @checked(in_array($cp->id, $selectedCheckpoints))>
                                                            <label class="form-check-label d-block cursor-pointer pe-4" for="cp_{{ $cp->id }}">
                                                                <span class="badge bg-primary me-2 rounded-pill px-2 py-1" style="font-size: 0.65rem;">Person</span>
                                                                <span class="fw-bold d-block text-break mt-1">{{ $cp->title }}</span>
                                                                <small class="text-muted d-block text-break">{{ Str::limit($cp->description, 50) }}</small>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        @empty
                                        <div class="text-center py-5 text-muted">
                                            <i class="bi bi-person-x display-6 mb-3 d-block"></i>
                                            ยังไม่มีจุดตรวจประเภท "สุขลักษณะพนักงาน"
                                        </div>
                                        @endforelse
                                    </div>

                                    {{-- Area Tab --}}
                                    <div class="tab-pane fade" id="area-panel" role="tabpanel">
                                        @php
                                            $areaCheckpoints = $allCheckpoints->map(function($categoryCheckpoints, $categoryName) {
                                                return collect($categoryCheckpoints)->where('type', 'area');
                                            })->filter(function($cp) { return $cp->count() > 0; });
                                        @endphp
                                        
                                        @forelse($areaCheckpoints as $categoryName => $checkpoints)
                                        <div class="mb-4 last-child-mb-0">
                                            <h6 class="text-uppercase text-muted fw-bold mb-3 border-bottom pb-2" style="font-size: 0.8rem; letter-spacing: 1px;">
                                                <i class="bi bi-folder me-1"></i>{{ $categoryName ?: 'ไม่มีหมวดหมู่' }}
                                            </h6>
                                            <div class="row g-3">
                                                @foreach($checkpoints as $cp)
                                                <div class="col-md-6">
                                                    <div class="position-relative p-3 rounded-3 border h-100 hover-border {{ in_array($cp->id, $selectedCheckpoints) ? 'bg-dark bg-opacity-10 border-dark' : 'bg-light border-transparent' }}">
                                                        <div class="position-absolute top-0 end-0 p-2 d-flex gap-1" style="z-index: 10;">
                                                            <button type="button" class="btn btn-sm bg-white border text-muted shadow-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" 
                                                                onclick="openEditModal({{ $cp->id }}, '{{ addslashes($cp->title) }}', '{{ addslashes($cp->description) }}', '{{ $cp->category_id }}', event)" title="แก้ไข">
                                                                <i class="bi bi-pencil" style="font-size: 0.7rem;"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm bg-white border text-danger shadow-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" 
                                                                onclick="deleteCheckpoint({{ $cp->id }}, event)" title="ลบ">
                                                                <i class="bi bi-trash" style="font-size: 0.7rem;"></i>
                                                            </button>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input ms-0 me-2" type="checkbox" name="checkpoint_ids[]" value="{{ $cp->id }}" id="cp_{{ $cp->id }}" @checked(in_array($cp->id, $selectedCheckpoints))>
                                                            <label class="form-check-label d-block cursor-pointer pe-4" for="cp_{{ $cp->id }}">
                                                                <span class="badge bg-dark me-2 rounded-pill px-2 py-1" style="font-size: 0.65rem;">Area</span>
                                                                <span class="fw-bold d-block text-break mt-1">{{ $cp->title }}</span>
                                                                <small class="text-muted d-block text-break">{{ Str::limit($cp->description, 50) }}</small>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        @empty
                                        <div class="text-center py-5 text-muted">
                                            <i class="bi bi-geo display-6 mb-3 d-block"></i>
                                            ยังไม่มีจุดตรวจประเภท "พื้นที่/อุปกรณ์"
                                        </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 2rem;">
                            <div class="card-body p-4">
                                <h6 class="fw-bold mb-3">คำแนะนำการใช้งาน</h6>
                                <ul class="text-muted small ps-3 mb-0">
                                    <li class="mb-2">เลือกจุดตรวจที่พนักงานประจำจุด <strong>{{ $location->location_name }}</strong> ต้องปฏิบัติ</li>
                                    <li class="mb-2">พนักงานที่ได้รับมอบหมายจุดประจำการนี้ จะเห็นรายการตรวจสอบเฉพาะที่เลือกไว้นี้เท่านั้น</li>
                                    <li>หากไม่เลือกเลย พนักงานอาจไม่เห็นรายการตรวจสอบใดๆ</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

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

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">✏️ แก้ไขจุดตรวจ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_title" class="form-label fw-bold small">ชื่อจุดตรวจ</label>
                            <input type="text" class="form-control" id="edit_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label fw-bold small">คำอธิบายเพิ่มเติม</label>
                            <textarea class="form-control" id="edit_description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_category_id" class="form-label fw-bold small">หมวดหมู่</label>
                            <select class="form-select" id="edit_category_id">
                                <option value="">-- ไม่ระบุหมวดหมู่ --</option>
                                @if(isset($categories))
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning py-2 shadow-sm rounded-3 text-white">บันทึกการแก้ไข</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    // Global functions need to be on window
    window.openEditModal = function(id, title, description, categoryId, event) {
        event.preventDefault();
        event.stopPropagation(); // Stop checkbox toggle
        
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_description').value = description;
        document.getElementById('edit_category_id').value = categoryId || "";
        
        const modal = new bootstrap.Modal(document.getElementById('editModal'));
        modal.show();
    }

    window.deleteCheckpoint = function(id, event) {
        event.preventDefault();
        event.stopPropagation(); // Stop checkbox toggle

        if(!confirm('⚠️ คำเตือน: คุณต้องการลบจุดตรวจนี้ออกจากระบบถาวรใช่หรือไม่?\n(การลบนี้จะมีผลต่อทุกจุดประจำการที่ใช้อยู่)')) {
            return;
        }

        fetch(`/checkpoints/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                window.location.reload();
            } else {
                alert('เกิดข้อผิดพลาดในการลบ');
            }
        })
        .catch(err => alert('Error connecting to server'));
    }

    document.addEventListener('DOMContentLoaded', function() {
        // ... (Existing Quick Create JS) ...
        const editForm = document.getElementById('editForm');
        if(editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const id = document.getElementById('edit_id').value;
                const title = document.getElementById('edit_title').value;
                const description = document.getElementById('edit_description').value;
                const categoryId = document.getElementById('edit_category_id').value;

                fetch(`/checkpoints/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        title: title,
                        description: description,
                        category_id: categoryId || null
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        window.location.reload();
                    } else {
                        alert('Error updating');
                    }
                })
                .catch(err => alert('Connection error'));
            });
        }
        
        const quickForm = document.getElementById('quickCreateForm');
        // ... rest of existing code ...

        const toggleNewCategory = document.getElementById('toggleNewCategory');
        const existingGroup = document.getElementById('existingCategoryGroup');
        const newGroup = document.getElementById('newCategoryGroup');
        const newCategoryInput = document.getElementById('new_category_name');
        const existingCategoryConfig = document.getElementById('new_category_id');

        // Toggle Logic
        toggleNewCategory.addEventListener('change', function() {
            if(this.checked) {
                existingGroup.classList.add('d-none');
                newGroup.classList.remove('d-none');
                newCategoryInput.focus();
                existingCategoryConfig.value = ""; // Reset select
            } else {
                existingGroup.classList.remove('d-none');
                newGroup.classList.add('d-none');
                newCategoryInput.value = ""; // Reset input
            }
        });

        quickForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get values
            const title = document.getElementById('new_title').value;
            const description = document.getElementById('new_description').value;

            // Prepare FormData
            const formData = new FormData();
            formData.append('title', title);
            formData.append('description', description);
            formData.append('type', document.querySelector('input[name="new_type"]:checked').value);
            
            // Category
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

            // Images
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
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData
            })
            .then(response => response.json())
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
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        });
    });
    </script>
    @endpush

    <style>
        .hover-border:hover {
            border-color: #dee2e6 !important;
        }
        .cursor-pointer {
            cursor: pointer;
        }
        .last-child-mb-0:last-child {
            margin-bottom: 0 !important;
        }
    </style>
</x-app-layout>
