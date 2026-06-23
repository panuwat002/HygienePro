<x-app-layout>
    @section('header', 'กำหนดพนักงานประจำสถานที่')

    <div class="mb-4">
        <a href="{{ route('employees.index') }}" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i>กลับไปรายการพนักงาน
        </a>
    </div>

    <form action="{{ route('employees.bulk-location.save') }}" method="POST" id="bulkLocationForm">
        @csrf
        <div class="row">
            {{-- Left: Employees Selection --}}
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="fw-bold mb-1"><i class="bi bi-people me-2 text-primary"></i>เลือกพนักงาน</h5>
                        <p class="text-muted small mb-0">จุดตรวจที่เลือกจะถูกกำหนดให้สถานที่ที่เลือกทั้งหมดด้านขวา</p>
                    </div>
                    <div class="card-body p-4">
                        {{-- Select All --}}
                        <div class="mb-3 p-3 bg-primary bg-opacity-10 rounded-3 border border-primary">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllEmployees">
                                <label class="form-check-label fw-bold text-primary" for="selectAllEmployees">
                                    <i class="bi bi-check-all me-1"></i>เลือกทั้งหมด
                                </label>
                            </div>
                        </div>

                        {{-- Filters --}}
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control rounded-pill" id="employeeSearch" placeholder="🔍 ค้นหาพนักงาน...">
                            </div>
                            <div class="col-md-6">
                                <select class="form-select rounded-pill" id="departmentFilter">
                                    <option value="">ทุกแผนก</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Employee List --}}
                        <div class="employee-list" style="max-height: 450px; overflow-y: auto;">
                            @foreach($employees as $employee)
                            <div class="form-check mb-2 p-2 rounded border bg-light employee-item" 
                                 data-name="{{ strtolower($employee->fullname ?? $employee->fname . ' ' . $employee->lname) }}" 
                                 data-dept="{{ $employee->department_id }}">
                                <input class="form-check-input employee-checkbox" type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" id="emp_{{ $employee->id }}">
                                <label class="form-check-label d-flex align-items-center gap-2 flex-wrap" for="emp_{{ $employee->id }}">
                                    <span class="fw-medium">{{ $employee->fullname ?? $employee->fname . ' ' . $employee->lname }}</span>
                                    <small class="text-muted">({{ $employee->employee_id }})</small>
                                    <span class="badge bg-secondary bg-opacity-25 text-dark small">{{ $employee->department->dept_name ?? '' }}</span>
                                    @if($employee->location)
                                        <span class="badge bg-success bg-opacity-25 text-success small">📍 {{ $employee->location->location_name }}</span>
                                    @endif
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Right: Location Selection --}}
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 2rem;">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="fw-bold mb-1"><i class="bi bi-geo-alt me-2 text-success"></i>เลือกสถานที่</h5>
                        <p class="text-muted small mb-0">เลือกสถานที่ที่ต้องการกำหนดจุดตรวจ</p>
                    </div>
                    <div class="card-body p-4">
                        {{-- Location Search --}}
                        <div class="mb-3">
                            <input type="text" class="form-control rounded-pill" id="locationSearch" placeholder="🔍 ค้นหาสถานที่...">
                        </div>

                        {{-- Select All Locations --}}
                        <div class="mb-3 p-3 bg-success bg-opacity-10 rounded-3 border border-success">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="location_id" value="" id="selectAllLocations" disabled>
                                <label class="form-check-label fw-bold text-success" for="selectAllLocations">
                                    <i class="bi bi-check-all me-1"></i>เลือกทั้งหมด <span id="locationCount" class="badge bg-success ms-1">{{ $locations->count() }}</span>
                                </label>
                            </div>
                        </div>

                        {{-- Location List --}}
                        <div class="location-list" style="max-height: 300px; overflow-y: auto;">
                            @foreach($locations as $loc)
                            <div class="form-check mb-2 p-2 rounded border bg-light location-item" data-name="{{ strtolower($loc->location_name) }}">
                                <input class="form-check-input location-radio" type="radio" name="location_id" value="{{ $loc->id }}" id="loc_{{ $loc->id }}" required>
                                <label class="form-check-label d-flex align-items-center justify-content-between w-100" for="loc_{{ $loc->id }}">
                                    <span class="fw-medium">{{ $loc->location_name }}</span>
                                    <span class="badge bg-primary bg-opacity-25 text-primary small">{{ $loc->employees_count ?? 0 }} คน</span>
                                </label>
                            </div>
                            @endforeach
                        </div>

                        <hr class="my-4">

                        {{-- Summary --}}
                        <div class="p-3 bg-light rounded-3 border mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">พนักงานที่เลือก:</span>
                                <strong id="selectedEmployeeCount" class="text-primary">0 คน</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">สถานที่ปลายทาง:</span>
                                <strong id="selectedLocation" class="text-success">-</strong>
                            </div>
                        </div>

                        {{-- Submit Button --}}
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg rounded-pill shadow" id="submitBtn" disabled>
                                <i class="bi bi-geo-alt-fill me-2"></i>กำหนดพนักงานไปสถานที่ที่เลือก
                            </button>
                        </div>

                        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 mt-3 small">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>หมายเหตุ:</strong> พนักงานที่เลือกจะถูกย้ายไปประจำสถานที่ใหม่
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const submitBtn = document.getElementById('submitBtn');

        // Select All Employees
        document.getElementById('selectAllEmployees').addEventListener('change', function() {
            const visibleEmployees = document.querySelectorAll('.employee-item:not([style*="display: none"]) .employee-checkbox');
            visibleEmployees.forEach(cb => cb.checked = this.checked);
            updateSummary();
        });

        // Search & Filter Employees
        document.getElementById('employeeSearch').addEventListener('input', filterEmployees);
        document.getElementById('departmentFilter').addEventListener('change', filterEmployees);

        function filterEmployees() {
            const search = document.getElementById('employeeSearch').value.toLowerCase();
            const deptId = document.getElementById('departmentFilter').value;

            document.querySelectorAll('.employee-item').forEach(item => {
                const name = item.dataset.name;
                const dept = item.dataset.dept;
                const matchSearch = name.includes(search);
                const matchDept = !deptId || dept == deptId;

                item.style.display = (matchSearch && matchDept) ? '' : 'none';
            });
        }

        // Search Locations
        document.getElementById('locationSearch').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            let visibleCount = 0;

            document.querySelectorAll('.location-item').forEach(item => {
                const name = item.dataset.name;
                const match = name.includes(search);
                item.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });

            document.getElementById('locationCount').textContent = visibleCount;
        });

        // Update summary on checkbox/radio change
        document.querySelectorAll('.employee-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSummary);
        });

        document.querySelectorAll('.location-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                const label = this.nextElementSibling.querySelector('.fw-medium');
                document.getElementById('selectedLocation').textContent = label ? label.textContent : '-';
                updateSummary();
            });
        });

        function updateSummary() {
            const checkedEmployees = document.querySelectorAll('.employee-checkbox:checked').length;
            const locationSelected = document.querySelector('.location-radio:checked');
            
            document.getElementById('selectedEmployeeCount').textContent = checkedEmployees + ' คน';
            submitBtn.disabled = !(checkedEmployees > 0 && locationSelected);
        }

        // Form submission confirmation
        document.getElementById('bulkLocationForm').addEventListener('submit', function(e) {
            const checkedCount = document.querySelectorAll('.employee-checkbox:checked').length;
            const locationText = document.getElementById('selectedLocation').textContent;
            
            if (!confirm(`ยืนยันการกำหนดพนักงาน ${checkedCount} คน ไปยัง "${locationText}"?`)) {
                e.preventDefault();
            }
        });
    });
    </script>
    @endpush

    <style>
        .employee-item:has(input:checked) {
            background-color: rgba(13, 110, 253, 0.1) !important;
            border-color: #0d6efd !important;
        }
        .location-item:has(input:checked) {
            background-color: rgba(25, 135, 84, 0.1) !important;
            border-color: #198754 !important;
        }
    </style>
</x-app-layout>
