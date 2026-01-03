<x-app-layout>
    @section('header', "กำหนดจุดตรวจ: $machine->name")

    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="{{ route('machines.index') }}" class="text-decoration-none text-muted">
                    <i class="bi bi-arrow-left me-1"></i>กลับไปรายการเครื่องจักร
                </a>
            </div>

            <form action="{{ route('machines.map.save', $machine->id) }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0">เลือกรายการตรวจสอบสำหรับเครื่องจักรนี้</h5>
                                <div class="d-flex gap-2">
                                     <button type="button" class="btn btn-light text-primary border rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#quickCreateModal">
                                        <i class="bi bi-plus-lg me-1"></i> Quick Add
                                    </button>
                                    <button type="submit" class="btn btn-primary px-4 rounded-pill shadow-sm">
                                        <i class="bi bi-save me-2"></i>บันทึก
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                @forelse($allCheckpoints as $categoryName => $checkpoints)
                                <div class="mb-4 last-child-mb-0">
                                    <h6 class="text-uppercase text-muted fw-bold mb-3 border-bottom pb-2" style="font-size: 0.8rem; letter-spacing: 1px;">
                                        {{ $categoryName ?: 'ไม่มีหมวดหมู่' }}
                                    </h6>
                                    <div class="row g-3">
                                        @foreach($checkpoints as $cp)
                                        <div class="col-md-6">
                                            <div class="position-relative p-3 rounded-3 border h-100 hover-border {{ in_array($cp->id, $selectedCheckpoints) ? 'bg-primary-subtle border-primary' : 'bg-light border-transparent' }}">
                                                <div class="form-check">
                                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="checkpoint_ids[]" value="{{ $cp->id }}" id="cp_{{ $cp->id }}" @checked(in_array($cp->id, $selectedCheckpoints))>
                                                    <label class="form-check-label d-block cursor-pointer pe-4" for="cp_{{ $cp->id }}">
                                                        <div class="d-flex align-items-center mb-1">
                                                            @if($cp->type === 'area')
                                                                <span class="badge bg-dark me-2 rounded-pill px-2 py-1" style="font-size: 0.65rem;">Area</span>
                                                            @else
                                                                <span class="badge bg-primary me-2 rounded-pill px-2 py-1" style="font-size: 0.65rem;">Person</span>
                                                            @endif
                                                            <span class="fw-bold d-block text-break">{{ $cp->title }}</span>
                                                        </div>
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
                                    <i class="bi bi-list-columns-reverse display-6 mb-3 d-block"></i>
                                    ยังไม่มีข้อมูลจุดตรวจในระบบ
                                </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 2rem;">
                            <div class="card-body p-4 text-center">
                                @if($machine->image)
                                    <div class="mb-3 rounded-3 overflow-hidden border mx-auto" style="width: 150px; height: 150px;">
                                        <img src="{{ asset('storage/' . $machine->image) }}" class="w-100 h-100 object-fit-cover">
                                    </div>
                                @else
                                    <i class="bi bi-gear-wide-connected display-1 text-light mb-3"></i>
                                @endif
                                <h5 class="fw-bold">{{ $machine->name }}</h5>
                                <p class="text-muted small">{{ $machine->code }}</p>
                                <hr>
                                <p class="small text-muted text-start">
                                    เลือกรายการ Checkpoint ที่ต้องการให้แสดงในแบบฟอร์มการตรวจของเครื่องจักรนี้
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Include Quick Create Modal from previous implementation or reuse -->
    <!-- For brevity, simplistic version or include same component if extracted -->
    <!-- I will copy the Quick Create Modal logic structure here to ensure it works independent of the location view -->
    
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
                            <label for="new_title" class="form-label fw-bold small">ชื่อจุดตรวจ</label>
                            <input type="text" class="form-control" id="new_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_description" class="form-label fw-bold small">คำอธิบายเพิ่มเติม</label>
                            <textarea class="form-control" id="new_description" rows="2"></textarea>
                        </div>
                         <div class="mb-3">
                            <label class="form-label fw-bold small">ประเภท (Type)</label>
                            <div class="d-flex gap-2">
                                <div class="flex-fill">
                                    <input type="radio" class="btn-check" name="new_type" id="type_area" value="area" checked>
                                    <label class="btn btn-outline-dark w-100" for="type_area">Area/Machine</label>
                                </div>
                                <div class="flex-fill">
                                    <input type="radio" class="btn-check" name="new_type" id="type_person" value="person">
                                    <label class="btn btn-outline-primary w-100" for="type_person">Person</label>
                                </div>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary-custom py-2 shadow-sm rounded-3">บันทึก</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Simple Quick Create for Machine Page
        const quickForm = document.getElementById('quickCreateForm');
        quickForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const title = document.getElementById('new_title').value;
            const description = document.getElementById('new_description').value;
            const type = document.querySelector('input[name="new_type"]:checked').value;

            fetch('{{ route("checkpoints.quick-store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ title, description, type })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) window.location.reload();
                else alert('Error');
            });
        });
    });
    </script>
    @endpush
    
    <style>
        .hover-border:hover {
            border-color: #dee2e6 !important;
        }
        .last-child-mb-0:last-child {
            margin-bottom: 0 !important;
        }
    </style>
</x-app-layout>
