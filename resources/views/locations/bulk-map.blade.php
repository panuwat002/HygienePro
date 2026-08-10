<x-app-layout>
    @section('header', 'กำหนดจุดตรวจหลายสถานที่')

    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="{{ route('locations.index') }}" class="text-decoration-none text-muted">
                    <i class="bi bi-arrow-left me-1"></i>กลับไปรายการสถานที่
                </a>
            </div>

            <form action="{{ route('locations.bulk-map.save') }}" method="POST" id="bulkMapForm">
                @csrf
                <div class="row">
                    {{-- Left: Checkpoints Selection --}}
                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-white border-0 py-3 px-4">
                                <h5 class="fw-bold mb-1"><i class="bi bi-list-check me-2 text-primary"></i>เลือกจุดตรวจที่ต้องการกำหนด</h5>
                                <p class="text-muted small mb-0">จุดตรวจที่เลือกจะถูกกำหนดให้กับทุกสถานที่ที่เลือกด้านขวา</p>
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
                    
                    {{-- Right: Locations Selection --}}
                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 2rem;">
                            <div class="card-header bg-white border-0 py-3 px-4">
                                <h5 class="fw-bold mb-1"><i class="bi bi-geo-alt me-2 text-success"></i>เลือกสถานที่</h5>
                                <p class="text-muted small mb-0">เลือกสถานที่ที่ต้องการกำหนดจุดตรวจ</p>
                            </div>
                            <div class="card-body p-4">
                                {{-- Search Box --}}
                                <div class="mb-3">
                                    <input type="text" class="form-control rounded-pill" id="locationSearch" placeholder="🔍 ค้นหาสถานที่...">
                                </div>

                                {{-- Select All Locations --}}
                                <div class="mb-3 p-3 bg-success bg-opacity-10 rounded-3 border border-success">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllLocations">
                                        <label class="form-check-label fw-bold text-success" for="selectAllLocations">
                                            <i class="bi bi-check-all me-1"></i>เลือกทั้งหมด <span id="locationCount" class="badge bg-success ms-1">{{ $locations->count() }}</span>
                                        </label>
                                    </div>
                                </div>

                                {{-- Location List --}}
                                <div class="location-list" style="max-height: 400px; overflow-y: auto;">
                                    @foreach($locations as $location)
                                    <div class="form-check mb-2 p-2 rounded border bg-light location-item" data-name="{{ strtolower($location->location_name) }}">
                                        <input class="form-check-input location-checkbox" type="checkbox" name="location_ids[]" value="{{ $location->id }}" id="location_{{ $location->id }}">
                                        <label class="form-check-label d-flex align-items-center gap-2" for="location_{{ $location->id }}">
                                            <span class="fw-medium">{{ $location->location_name }}</span>
                                            <small class="text-muted">({{ $location->checkpoints_count ?? 0 }} จุดตรวจ)</small>
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

        // Select All Locations
        document.getElementById('selectAllLocations').addEventListener('change', function() {
            const visibleLocations = document.querySelectorAll('.location-item:not([style*="display: none"]) .location-checkbox');
            visibleLocations.forEach(cb => cb.checked = this.checked);
        });

        // Search Locations
        document.getElementById('locationSearch').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            let visibleCount = 0;

            document.querySelectorAll('.location-item').forEach(item => {
                const name = item.dataset.name;
                if (name.includes(search)) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            document.getElementById('locationCount').textContent = visibleCount;
        });

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
            const locations = document.querySelectorAll('.location-checkbox:checked').length;
            
            if (checkpoints === 0) {
                alert('กรุณาเลือกจุดตรวจอย่างน้อย 1 รายการ');
                return false;
            }
            if (locations === 0) {
                alert('กรุณาเลือกสถานที่อย่างน้อย 1 รายการ');
                return false;
            }
            return confirm(`ยืนยันกำหนดจุดตรวจ ${checkpoints} รายการ ให้กับสถานที่ ${locations} รายการ?`);
        }
    });
    </script>
    @endpush

    <style>
        .checkpoint-item:has(input:checked) {
            background-color: rgba(13, 110, 253, 0.1) !important;
            border-color: #0d6efd !important;
        }
        .location-item:has(input:checked) {
            background-color: rgba(25, 135, 84, 0.1) !important;
            border-color: #198754 !important;
        }
    </style>
</x-app-layout>
