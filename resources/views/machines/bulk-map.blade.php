<x-app-layout>
    @section('header', 'กำหนดจุดตรวจหลายเครื่องพร้อมกัน (Bulk Assign)')

    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="{{ route('machines.index') }}" class="text-decoration-none text-muted">
                    <i class="bi bi-arrow-left me-1"></i>กลับไปรายการเครื่องจักร
                </a>
            </div>

            <form action="{{ route('machines.bulk-map.save') }}" method="POST" id="bulkMapForm">
                @csrf
                <div class="row">
                    {{-- Left: Checkpoints Selection --}}
                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-white border-0 py-3 px-4">
                                <h5 class="fw-bold mb-1"><i class="bi bi-list-check me-2 text-primary"></i>เลือกจุดตรวจที่ต้องการกำหนด</h5>
                                <p class="text-muted small mb-0">จุดตรวจที่เลือกจะถูกกำหนดให้กับทุกเครื่องจักรที่เลือกด้านขวา</p>
                            </div>
                            <div class="card-body p-4">
                                {{-- Select All Checkbox --}}
                                <div class="mb-3 p-3 bg-light rounded-3 border">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllCheckpoints">
                                        <label class="form-check-label fw-bold" for="selectAllCheckpoints">
                                            เลือกทั้งหมด
                                        </label>
                                    </div>
                                </div>

                                @forelse($allCheckpoints as $categoryName => $checkpoints)
                                <div class="mb-4">
                                    <h6 class="text-uppercase text-muted fw-bold mb-3 border-bottom pb-2" style="font-size: 0.8rem; letter-spacing: 1px;">
                                        {{ $categoryName ?: 'ไม่มีหมวดหมู่' }}
                                    </h6>
                                    <div class="row g-2">
                                        @foreach($checkpoints as $cp)
                                        <div class="col-md-6">
                                            <div class="position-relative p-3 rounded-3 border bg-light h-100 checkpoint-item">
                                                <div class="form-check">
                                                    <input class="form-check-input checkpoint-checkbox" type="checkbox" name="checkpoint_ids[]" value="{{ $cp->id }}" id="cp_{{ $cp->id }}">
                                                    <label class="form-check-label d-block cursor-pointer" for="cp_{{ $cp->id }}">
                                                        <div class="d-flex align-items-center mb-1">
                                                            @if($cp->type === 'area')
                                                                <span class="badge bg-dark me-2 rounded-pill px-2 py-1" style="font-size: 0.65rem;">Area</span>
                                                            @else
                                                                <span class="badge bg-primary me-2 rounded-pill px-2 py-1" style="font-size: 0.65rem;">Person</span>
                                                            @endif
                                                            <span class="fw-bold text-break">{{ $cp->title }}</span>
                                                        </div>
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
                    
                    {{-- Right: Machines Selection --}}
                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 2rem;">
                            <div class="card-header bg-white border-0 py-3 px-4">
                                <h5 class="fw-bold mb-1"><i class="bi bi-gear-wide-connected me-2 text-success"></i>เลือกเครื่องจักร</h5>
                                <p class="text-muted small mb-0">เลือกเครื่องจักรที่ต้องการกำหนดจุดตรวจ</p>
                            </div>
                            <div class="card-body p-4">
                                {{-- Search Box --}}
                                <div class="mb-3">
                                    <input type="text" class="form-control rounded-pill" id="machineSearch" placeholder="🔍 ค้นหาเครื่องจักร...">
                                </div>

                                {{-- Filter by Location --}}
                                <div class="mb-3">
                                    <select class="form-select rounded-pill" id="locationFilter">
                                        <option value="">ทุก Location</option>
                                        @foreach($locations as $location)
                                            <option value="{{ $location->id }}">{{ $location->location_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Select All Machines --}}
                                <div class="mb-3 p-3 bg-success bg-opacity-10 rounded-3 border border-success">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllMachines">
                                        <label class="form-check-label fw-bold text-success" for="selectAllMachines">
                                            <i class="bi bi-check-all me-1"></i>เลือกทั้งหมด <span id="machineCount" class="badge bg-success ms-1">{{ $machines->count() }}</span>
                                        </label>
                                    </div>
                                </div>

                                {{-- Machine List --}}
                                <div class="machine-list" style="max-height: 400px; overflow-y: auto;">
                                    @foreach($machines as $machine)
                                    <div class="form-check mb-2 p-2 rounded border bg-light machine-item" data-location="{{ $machine->location_id }}" data-name="{{ strtolower($machine->name) }}">
                                        <input class="form-check-input machine-checkbox" type="checkbox" name="machine_ids[]" value="{{ $machine->id }}" id="machine_{{ $machine->id }}">
                                        <label class="form-check-label d-flex align-items-center gap-2" for="machine_{{ $machine->id }}">
                                            <span class="fw-medium">{{ $machine->name }}</span>
                                            <small class="text-muted">{{ $machine->location->location_name ?? '' }}</small>
                                        </label>
                                    </div>
                                    @endforeach
                                </div>

                                <hr class="my-4">

                                {{-- Action Buttons --}}
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-warning flex-fill rounded-pill" id="btnReplace">
                                        <i class="bi bi-arrow-repeat me-1"></i>แทนที่
                                    </button>
                                    <button type="button" class="btn btn-success flex-fill rounded-pill" id="btnAdd">
                                        <i class="bi bi-plus-lg me-1"></i>เพิ่มเติม
                                    </button>
                                </div>
                                <input type="hidden" name="mode" id="assignMode" value="add">

                                <div class="alert alert-info border-0 bg-info bg-opacity-10 mt-3 small">
                                    <strong>แทนที่:</strong> ลบจุดตรวจเดิม แล้วใส่ใหม่<br>
                                    <strong>เพิ่มเติม:</strong> เพิ่มจุดตรวจใหม่ โดยไม่ลบของเดิม
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Select All Checkpoints
        document.getElementById('selectAllCheckpoints').addEventListener('change', function() {
            document.querySelectorAll('.checkpoint-checkbox').forEach(cb => cb.checked = this.checked);
        });

        // Select All Machines
        document.getElementById('selectAllMachines').addEventListener('change', function() {
            const visibleMachines = document.querySelectorAll('.machine-item:not([style*="display: none"]) .machine-checkbox');
            visibleMachines.forEach(cb => cb.checked = this.checked);
        });

        // Search Machines
        document.getElementById('machineSearch').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            filterMachines();
        });

        // Filter by Location
        document.getElementById('locationFilter').addEventListener('change', function() {
            filterMachines();
        });

        function filterMachines() {
            const search = document.getElementById('machineSearch').value.toLowerCase();
            const locationId = document.getElementById('locationFilter').value;
            let visibleCount = 0;

            document.querySelectorAll('.machine-item').forEach(item => {
                const name = item.dataset.name;
                const loc = item.dataset.location;
                const matchSearch = name.includes(search);
                const matchLocation = !locationId || loc == locationId;

                if (matchSearch && matchLocation) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            document.getElementById('machineCount').textContent = visibleCount;
        }

        // Submit buttons
        document.getElementById('btnReplace').addEventListener('click', function() {
            document.getElementById('assignMode').value = 'replace';
            if (validateForm()) document.getElementById('bulkMapForm').submit();
        });

        document.getElementById('btnAdd').addEventListener('click', function() {
            document.getElementById('assignMode').value = 'add';
            if (validateForm()) document.getElementById('bulkMapForm').submit();
        });

        function validateForm() {
            const checkpoints = document.querySelectorAll('.checkpoint-checkbox:checked').length;
            const machines = document.querySelectorAll('.machine-checkbox:checked').length;
            
            if (checkpoints === 0) {
                alert('กรุณาเลือกจุดตรวจอย่างน้อย 1 รายการ');
                return false;
            }
            if (machines === 0) {
                alert('กรุณาเลือกเครื่องจักรอย่างน้อย 1 รายการ');
                return false;
            }
            return confirm(`ยืนยันกำหนดจุดตรวจ ${checkpoints} รายการ ให้กับเครื่องจักร ${machines} รายการ?`);
        }
    });
    </script>
    @endpush

    <style>
        .checkpoint-item:has(input:checked) {
            background-color: rgba(13, 110, 253, 0.1) !important;
            border-color: #0d6efd !important;
        }
        .machine-item:has(input:checked) {
            background-color: rgba(25, 135, 84, 0.1) !important;
            border-color: #198754 !important;
        }
    </style>
</x-app-layout>
