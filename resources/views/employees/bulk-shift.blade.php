<x-app-layout>
    @section('header', 'กำหนดพนักงานประจำสถานที่')

    <div class="mb-4">
        <a href="{{ route('employees.index') }}" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i>กลับไปรายการพนักงาน
        </a>
    </div>

    <form action="{{ route('employees.bulk-shift.save') }}" method="POST" id="bulkShiftForm">
        @csrf
        <div class="row">
            {{-- Left: Employees Selection --}}
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="fw-bold mb-1"><i class="bi bi-people me-2 text-primary"></i>เลือกพนักงาน</h5>
                        <p class="text-muted small mb-0">กะที่เลือกจะถูกกำหนดให้พนักงานทั้งหมดด้านซ้าย</p>
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
                                    @if($employee->shift)
                                        <span class="badge bg-success bg-opacity-25 text-success small">⏳ {{ $employee->shift->shift_name }}</span>
                                    @endif
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Right: Shift Selection --}}
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 2rem;">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="fw-bold mb-1"><i class="bi bi-clock-history me-2 text-success"></i>เลือกกะการทำงาน (Shift)</h5>
                        <p class="text-muted small mb-0">เลือกกะที่ต้องการกำหนดให้พนักงาน</p>
                    </div>
                    <div class="card-body p-4">
                        {{-- Shift Search --}}
                        <div class="mb-3">
                            <input type="text" class="form-control rounded-pill" id="shiftSearch" placeholder="🔍 ค้นหากะการทำงาน...">
                        </div>

                        {{-- Select All Shifts (Visual only usually) --}}
                        <div class="mb-3 p-3 bg-success bg-opacity-10 rounded-3 border border-success">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="shift_id" value="" id="selectAllShifts" disabled>
                                <label class="form-check-label fw-bold text-success" for="selectAllShifts">
                                    <i class="bi bi-check-all me-1"></i>จำนวนกะ <span id="shiftCount" class="badge bg-success ms-1">{{ $shifts->count() }}</span>
                                </label>
                            </div>
                        </div>

                        {{-- Shift List --}}
                        <div class="shift-list" style="max-height: 300px; overflow-y: auto;">
                            @foreach($shifts as $shift)
                            <div class="form-check mb-2 p-2 rounded border bg-light shift-item" data-name="{{ strtolower($shift->shift_name) }}">
                                <input class="form-check-input shift-radio" type="radio" name="shift_id" value="{{ $shift->id }}" id="shift_{{ $shift->id }}" required>
                                <label class="form-check-label d-flex align-items-center justify-content-between w-100" for="shift_{{ $shift->id }}">
                                    <span class="fw-medium">{{ $shift->shift_name }}</span>
                                    <span class="badge bg-primary bg-opacity-25 text-primary small">{{ $shift->employees_count ?? 0 }} คน</span>
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
                                <span class="text-muted">กะการทำงานใหม่:</span>
                                <strong id="selectedShift" class="text-success">-</strong>
                            </div>
                        </div>

                        {{-- Submit Button --}}
                        <div class="d-grid">
                            <button type="button" class="btn btn-success btn-lg rounded-pill shadow" id="submitBtn" onclick="confirmShiftAssignment(this)" disabled>
                                <i class="bi bi-clock-fill me-2"></i>เปลี่ยนกะพนักงานที่เลือก
                            </button>
                        </div>

                        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 mt-3 small">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>หมายเหตุ:</strong> พนักงานที่เลือกจะถูกย้ายไปประจำกะการทำงานที่เลือกใหม่
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

        // Search Shifts
        document.getElementById('shiftSearch').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            let visibleCount = 0;

            document.querySelectorAll('.shift-item').forEach(item => {
                const name = item.dataset.name;
                const match = name.includes(search);
                item.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });

            document.getElementById('shiftCount').textContent = visibleCount;
        });

        // Update summary on checkbox/radio change
        document.querySelectorAll('.employee-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSummary);
        });

        document.querySelectorAll('.shift-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                const label = this.nextElementSibling.querySelector('.fw-medium');
                document.getElementById('selectedShift').textContent = label ? label.textContent : '-';
                updateSummary();
            });
        });

        function updateSummary() {
            const checkedEmployees = document.querySelectorAll('.employee-checkbox:checked').length;
            const shiftSelected = document.querySelector('.shift-radio:checked');
            
            document.getElementById('selectedEmployeeCount').textContent = checkedEmployees + ' คน';
            submitBtn.disabled = !(checkedEmployees > 0 && shiftSelected);
        }

        // Make function globally available for onclick
        window.confirmShiftAssignment = function(btnElement) {
            const checkedCount = document.querySelectorAll('.employee-checkbox:checked').length;
            const shiftText = document.getElementById('selectedShift').textContent;
            
            confirmAction(
                btnElement, 
                `ยืนยันการตั้งกะการทำงาน "${shiftText}" ให้กับพนักงาน ${checkedCount} คน?`,
                'ยืนยันการเปลี่ยนกะ'
            );
        };
    });
    </script>
    @endpush

    <style>
        .employee-item:has(input:checked) {
            background-color: rgba(13, 110, 253, 0.1) !important;
            border-color: #0d6efd !important;
        }
        .shift-item:has(input:checked) {
            background-color: rgba(25, 135, 84, 0.1) !important;
            border-color: #198754 !important;
        }
    </style>
</x-app-layout>
