<x-app-layout>
@section('header', 'จัดการตารางงานรายสัปดาห์ (Roster)')

<!-- Add SweetAlert2 for better notifications -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid px-2 px-md-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold"><i class="bi bi-calendar3-week text-primary me-2"></i>จัดการตารางงานรายสัปดาห์ (Roster)</h1>
            <p class="text-muted mb-0">
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 me-2">
                    <i class="bi bi-building me-1"></i> แผนก: {{ $department->dept_name }}
                </span>
                <span class="text-secondary small">
                    สัปดาห์: {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}
                </span>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-md-end">
            <input type="file" id="rosterImportFile" class="d-none" accept=".xlsx,.xls,.csv">
            <button type="button" id="rosterImportBtn" class="btn btn-outline-success rounded-pill shadow-sm bg-white d-none d-md-block">
                <i class="bi bi-file-earmark-excel me-1"></i> นำเข้า
            </button>
            <button type="button" id="rosterExportBtn" data-url="{{ route('departments.roster.export', ['department' => $department->id, 'start_date' => $startDate->format('Y-m-d')]) }}" class="btn btn-outline-success rounded-pill shadow-sm bg-white d-none d-md-block">
                <i class="bi bi-file-earmark-excel me-1"></i> นำออก
            </button>
            <a href="{{ route('departments.roster.print', ['department' => $department->id, 'start_date' => $startDate->format('Y-m-d')]) }}" target="_blank" class="btn btn-outline-secondary rounded-pill shadow-sm bg-white d-none d-md-block">
                <i class="bi bi-printer me-1"></i> พิมพ์
            </a>
            <div class="btn-group shadow-sm rounded-pill overflow-hidden">
                <a href="{{ route('departments.roster', ['department' => $department->id, 'start_date' => $startDate->copy()->subWeek()->format('Y-m-d')]) }}" class="btn btn-white border-end hover-bg-light px-3 py-2">
                    <i class="bi bi-chevron-left"></i> สัปดาห์ก่อนหน้า
                </a>
                <button class="btn btn-light px-3 fw-bold border-0" disabled>
                    {{ $startDate->format('M Y') }}
                </button>
                <a href="{{ route('departments.roster', ['department' => $department->id, 'start_date' => $startDate->copy()->addWeek()->format('Y-m-d')]) }}" class="btn btn-white border-start hover-bg-light px-3 py-2">
                    สัปดาห์ถัดไป <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex flex-column flex-md-row align-items-md-center gap-2 flex-grow-1" style="max-width: 650px;">
                <div class="input-group shadow-sm" style="flex: 1 1 auto; border-radius: 50rem;">
                    <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3 text-primary"><i class="bi bi-search"></i></span>
                    <input type="text" id="rosterSearch" class="form-control border-start-0 bg-white rounded-end-pill py-2 shadow-none" placeholder="ค้นหาชื่อ หรือรหัส..." style="border-color: #dee2e6;">
                </div>
                
                <div class="input-group shadow-sm" style="flex: 1 1 auto; border-radius: 50rem;">
                    <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3 text-secondary"><i class="bi bi-funnel"></i></span>
                    <select id="shiftFilter" class="form-select border-start-0 bg-white rounded-end-pill py-2 shadow-none fw-medium text-secondary" style="border-color: #dee2e6; cursor: pointer;">
                        <option value="ALL">-- แสดงกะทั้งหมด --</option>
                        <option value="UNASSIGNED" class="text-danger fw-bold">ยังไม่ได้จัดตารางงาน (Unassigned)</option>
                        @foreach($shifts as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->shift_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-2 bulk-toolbar p-2 rounded-pill shadow-sm" style="background: linear-gradient(to right, #f8f9fa, #e9ecef); border: 1px solid #dee2e6;">
                <div class="d-flex align-items-center bg-white rounded-pill px-3 py-1 shadow-sm">
                    <i class="bi bi-magic text-primary me-2 fs-5"></i>
                    <span class="small fw-bold text-dark d-none d-xl-inline me-2">จัดการกลุ่ม:</span>
                    <select id="bulkShift" class="form-select border-0 bg-transparent shadow-none fw-semibold text-primary py-1" style="width: auto; cursor: pointer;">
                        <option value="">-- เลือกกะ --</option>
                        @foreach($shifts as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->shift_name }}</option>
                        @endforeach
                        <option value="OFF" class="text-danger fw-bold">หยุด (Day Off)</option>
                        <option value="DEFAULT" class="text-muted">-- ยึดตามกะหลัก --</option>
                    </select>
                </div>
                <div class="bg-white rounded-pill shadow-sm px-1 py-1">
                    <select id="bulkDay" class="form-select border-0 bg-transparent shadow-none fw-semibold py-1 text-dark" style="width: auto; cursor: pointer;">
                        <option value="ALL">ทุกวัน (ทั้งสัปดาห์)</option>
                        @for($i = 0; $i < 7; $i++)
                            <option value="{{ $i }}">{{ ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'][$startDate->copy()->addDays($i)->dayOfWeek] }}</option>
                        @endfor
                    </select>
                </div>
                <button id="applyBulkBtn" class="btn btn-primary rounded-pill px-4 shadow-sm hover-lift fw-bold" style="background: linear-gradient(45deg, #0d6efd, #0dcaf0); border: none;">
                    <i class="bi bi-check2-circle me-1"></i> นำไปใช้
                </button>
            </div>
            
            <span class="badge bg-secondary-subtle text-secondary rounded-pill d-none d-md-inline">พนักงาน {{ $employees->count() }} คน</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive roster-table-container">
                <table class="table table-hover mb-0 roster-table" id="rosterTable">
                    <thead>
                        <tr>
                            <th class="sticky-col first-col bg-white border-end shadow-sm align-middle" style="min-width: 250px; z-index: 10;">
                                <div class="d-flex align-items-center px-3">
                                    <div class="form-check mb-0 me-3">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                    </div>
                                    <span class="text-uppercase text-muted fw-bold small tracking-wide">รายชื่อพนักงาน</span>
                                </div>
                            </th>
                            @for($i = 0; $i < 7; $i++)
                                @php 
                                    $currentDate = $startDate->copy()->addDays($i); 
                                    $isToday = $currentDate->isToday();
                                @endphp
                                <th class="text-center align-middle {{ $isToday ? 'bg-primary-subtle border-primary' : 'bg-light' }}" style="min-width: 140px; border-bottom: 2px solid {{ $isToday ? '#0d6efd' : '#dee2e6' }};">
                                    <div class="d-flex flex-column align-items-center justify-content-center py-2">
                                        <span class="fw-bold {{ $isToday ? 'text-primary' : 'text-dark' }} fs-6">
                                            {{ ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'][$currentDate->dayOfWeek] }}
                                        </span>
                                        <span class="badge {{ $isToday ? 'bg-primary' : 'bg-secondary text-white' }} rounded-pill mt-1" style="font-size: 0.75rem;">
                                            {{ $currentDate->format('d M') }}
                                        </span>
                                        @if($isToday)
                                            <span class="position-absolute top-0 end-0 p-1">
                                                <span class="badge bg-danger rounded-circle p-1"><span class="visually-hidden">Today</span></span>
                                            </span>
                                        @endif
                                    </div>
                                </th>
                            @endfor
                        </tr>
                    </thead>
                    @php
                        $weekDates = [];
                        for($i = 0; $i < 7; $i++) {
                            $dateObj = $startDate->copy()->addDays($i);
                            $weekDates[] = [
                                'date_string' => $dateObj->toDateString(),
                                'is_today' => $dateObj->isToday(),
                            ];
                        }
                        
                        // Pre-calculate shift options for performance
                        $shiftOptionsTemplate = [];
                        foreach($shifts as $shift) {
                            $shiftOptionsTemplate[] = [
                                'id' => $shift->id,
                                'name' => $shift->shift_name
                            ];
                        }
                    @endphp
                    <tbody>
                        @foreach($employees as $employee)
                            <tr data-shift-id="{{ $employee->shift_id ?? 'NONE' }}">
                                <td class="sticky-col first-col bg-white border-end align-middle fw-bold text-dark px-3">
                                    <div class="d-flex align-items-center">
                                        <div class="form-check mb-0 me-3">
                                            <input class="form-check-input row-checkbox" type="checkbox" value="{{ $employee->id }}">
                                        </div>
                                        <div class="avatar-circle bg-light text-primary d-flex align-items-center justify-content-center me-2 rounded-circle fw-bold shadow-sm" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                            {{ mb_substr($employee->fname, 0, 1) }}
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="text-truncate" style="max-width: 140px;" title="{{ $employee->fname }} {{ $employee->lname }}">
                                                {{ $employee->fname }} {{ $employee->lname }}
                                            </span>
                                            <small class="text-muted fw-normal" style="font-size: 0.75rem;">รหัส: {{ $employee->employee_id }}</small>
                                        </div>
                                    </div>
                                </td>
                                @foreach($weekDates as $day)
                                    @php 
                                        $currentDate = $day['date_string']; 
                                        $isToday = $day['is_today'];
                                        
                                        $empSchedule = $schedules[$employee->id][$currentDate] ?? null;
                                        $selectValue = $empSchedule ? ($empSchedule->is_day_off ? 'OFF' : $empSchedule->shift_id) : '';
                                        
                                        $selectClasses = 'form-select form-select-sm roster-select shadow-none border-0 fw-semibold text-center custom-select-ui';
                                        $tdClasses = $isToday ? 'bg-primary-subtle-light' : '';
                                        
                                        if ($selectValue === '') {
                                            $selectClasses .= ' bg-light text-muted';
                                        } elseif ($selectValue === 'OFF') {
                                            $selectClasses .= ' bg-danger-subtle text-danger';
                                            $tdClasses .= ' bg-light';
                                        } else {
                                            $selectClasses .= ' bg-info-subtle text-info';
                                        }
                                    @endphp
                                    <td class="text-center p-2 align-middle {{ $tdClasses }}" data-emp="{{ $employee->id }}" data-date="{{ $currentDate }}">
                                        <div class="position-relative">
                                            <select class="{{ $selectClasses }}" data-original="{{ $selectValue }}">
                                                <option value="" class="text-muted" {{ $selectValue === '' ? 'selected' : '' }}>-- ยึดตามกะหลัก --</option>
                                                @foreach($shiftOptionsTemplate as $sOpt)
                                                    <option value="{{ $sOpt['id'] }}" {{ (string)$selectValue === (string)$sOpt['id'] ? 'selected' : '' }}>
                                                        {{ $sOpt['name'] }}
                                                    </option>
                                                @endforeach
                                                <option value="OFF" class="text-danger fw-bold" {{ $selectValue === 'OFF' ? 'selected' : '' }}>หยุด (Day Off)</option>
                                            </select>
                                            <div class="select-indicator"></div>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
                    <div class="card-footer bg-light border-top text-end py-3 px-4 shadow-sm position-relative z-1">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i> ระบบบันทึกอัตโนมัติเมื่อกดบันทึกตารางงาน
                </div>
                <button id="saveRosterBtn" class="btn btn-primary rounded-pill px-5 py-2 shadow hover-lift fw-bold fs-6" style="background: linear-gradient(45deg, #0d6efd, #0b5ed7); border: none;">
                    <i class="bi bi-save2 me-2"></i> บันทึกตารางงาน
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Search & Shift Filter Logic
    const searchInput = document.getElementById('rosterSearch');
    const shiftFilter = document.getElementById('shiftFilter');

    function filterRoster() {
        const searchText = searchInput ? searchInput.value.toLowerCase() : '';
        const shiftValue = shiftFilter ? shiftFilter.value : 'ALL';
        
        let rows = document.querySelectorAll('#rosterTable tbody tr');
        rows.forEach(row => {
            let nameCell = row.querySelector('td:first-child');
            let rowShiftId = row.getAttribute('data-shift-id');
            
            let matchesSearch = true;
            if (nameCell && searchText !== '') {
                let text = nameCell.textContent.toLowerCase();
                matchesSearch = text.includes(searchText);
            }
            
            let matchesShift = true;
            if (shiftValue !== 'ALL') {
                if (shiftValue === 'UNASSIGNED') {
                    matchesShift = (rowShiftId === 'NONE');
                    if (matchesShift) {
                        let selects = row.querySelectorAll('.roster-select');
                        for(let i=0; i<selects.length; i++) {
                            if (selects[i].value !== '') {
                                matchesShift = false;
                                break;
                            }
                        }
                    }
                } else {
                    matchesShift = (rowShiftId === shiftValue);
                    if (!matchesShift) {
                        let selects = row.querySelectorAll('.roster-select');
                        for(let i=0; i<selects.length; i++) {
                            if (selects[i].value === shiftValue) {
                                matchesShift = true;
                                break;
                            }
                        }
                    }
                }
            }
            
            if (matchesSearch && matchesShift) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keyup', filterRoster);
    }
    if (shiftFilter) {
        shiftFilter.addEventListener('change', filterRoster);
    }
    
    // Select All Checkbox
    const selectAll = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    
    if(selectAll) {
        selectAll.addEventListener('change', function() {
            rowCheckboxes.forEach(cb => {
                // Only select visible rows if filtered
                if(cb.closest('tr').style.display !== 'none') {
                    cb.checked = this.checked;
                }
            });
        });
    }
    
    // Bulk Apply Logic
    document.getElementById('applyBulkBtn').addEventListener('click', function() {
        const selectedShift = document.getElementById('bulkShift').value;
        const selectedDay = document.getElementById('bulkDay').value;
        
        if (!selectedShift) {
            Swal.fire({ icon: 'warning', title: 'โปรดเลือกกะ', text: 'กรุณาเลือกกะการทำงานหรือวันหยุดก่อน' });
            return;
        }
        
        let checkedCount = 0;
        rowCheckboxes.forEach(cb => {
            if (cb.checked) {
                checkedCount++;
                let tr = cb.closest('tr');
                let selects = tr.querySelectorAll('.roster-select');
                
                selects.forEach((select, index) => {
                    // if selectedDay is 'ALL', apply to all 7 days. Otherwise apply only to the specific index (0-6)
                    if (selectedDay === 'ALL' || parseInt(selectedDay) === index) {
                        select.value = selectedShift === 'DEFAULT' ? '' : selectedShift;
                        // trigger change event to update colors
                        let event = new Event('change');
                        select.dispatchEvent(event);
                    }
                });
            }
        });
        
        if (checkedCount === 0) {
            Swal.fire({ icon: 'warning', title: 'ไม่ได้เลือกพนักงาน', text: 'กรุณาติ๊กเลือกพนักงานอย่างน้อย 1 คน' });
        } else {
            Swal.fire({ 
                icon: 'success', 
                title: 'กำหนดกะแบบกลุ่มสำเร็จ', 
                text: 'นำกะไปใส่ให้ ' + checkedCount + ' คนเรียบร้อยแล้ว (อย่าลืมกดบันทึกตารางงาน)',
                toast: true,
                position: 'top-end',
                timer: 3000,
                showConfirmButton: false
            });
            
            // Uncheck all after applying to avoid confusion
            if(selectAll) selectAll.checked = false;
            rowCheckboxes.forEach(cb => cb.checked = false);
        }
    });

    // Function to update select colors based on value
    function updateSelectColor(select) {
        select.classList.remove('bg-light', 'text-muted', 'bg-danger-subtle', 'text-danger', 'bg-info-subtle', 'text-info', 'bg-warning-subtle', 'text-warning');
        let val = select.value;
        let original = select.getAttribute('data-original');
        
        if (val === '') {
            select.classList.add('bg-light', 'text-muted');
        } else if (val === 'OFF') {
            select.classList.add('bg-danger-subtle', 'text-danger');
        } else {
            // Give different shifts slightly different tints if possible, or just a generic active tint
            select.classList.add('bg-info-subtle', 'text-info');
        }
        
        // Highlight if changed from original
        let indicator = select.nextElementSibling;
        if (val !== original) {
            select.classList.add('border', 'border-warning');
            if(indicator) indicator.classList.add('changed-indicator');
        } else {
            select.classList.remove('border', 'border-warning');
            if(indicator) indicator.classList.remove('changed-indicator');
        }
    }

    // Initialize event listeners (skip color initialization loop for performance)
    let selects = document.querySelectorAll('.roster-select');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            updateSelectColor(this);
        });
    });

    // Save functionality with SweetAlert
    document.getElementById('saveRosterBtn').addEventListener('click', function() {
        let schedules = [];
        
        selects.forEach(select => {
            if (select.value !== select.getAttribute('data-original')) {
                let td = select.closest('td');
                schedules.push({
                    employee_id: td.getAttribute('data-emp'),
                    date: td.getAttribute('data-date'),
                    shift_id: select.value !== 'OFF' && select.value !== '' ? select.value : null,
                    is_day_off: select.value === 'OFF' ? 1 : 0
                });
            }
        });

        if (schedules.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'ไม่มีการเปลี่ยนแปลง',
                text: 'คุณยังไม่ได้แก้ไขตารางงานใดๆ',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }

        let btn = this;
        let originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังบันทึก...';

        fetch('{{ route('departments.roster.store', $department->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ schedules: schedules })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'บันทึกสำเร็จ!',
                    text: 'อัปเดตตารางงานเรียบร้อยแล้ว',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                throw new Error('Server returned false');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง',
                confirmButtonColor: '#dc3545'
            });
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    });

    // Selective Export functionality
    const exportBtn = document.getElementById('rosterExportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            let baseUrl = this.getAttribute('data-url');
            let selectedIds = [];
            
            rowCheckboxes.forEach(cb => {
                if (cb.checked && cb.closest('tr').style.display !== 'none') {
                    selectedIds.push(cb.value);
                }
            });
            
            if (selectedIds.length > 0) {
                // Submit via POST form to avoid URL length limit (404/414 Error)
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = baseUrl; // baseUrl already contains ?start_date=...
                
                let csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = '{{ csrf_token() }}';
                form.appendChild(csrf);
                
                selectedIds.forEach(id => {
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'employee_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
                setTimeout(() => document.body.removeChild(form), 100);
            } else {
                // Default GET behavior if no one is selected
                window.location.href = baseUrl;
            }
        });
    }

    // Excel Import functionality
    const importBtn = document.getElementById('rosterImportBtn');
    const importFile = document.getElementById('rosterImportFile');
    
    if (importBtn && importFile) {
        importBtn.addEventListener('click', function() {
            importFile.click();
        });
        
        importFile.addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                const formData = new FormData();
                formData.append('file', file);
                formData.append('start_date', '{{ $startDate->format('Y-m-d') }}');
                
                let originalHtml = importBtn.innerHTML;
                importBtn.disabled = true;
                importBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังนำเข้า...';
                
                fetch('{{ route('departments.roster.import', $department->id) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'นำเข้าสำเร็จ!',
                            text: 'อัปเดตตารางงานจากไฟล์ Excel เรียบร้อยแล้ว',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        throw new Error(data.message || 'Server returned false');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาดในการนำเข้า',
                        text: error.message || 'ไม่สามารถนำเข้าข้อมูลได้ กรุณาตรวจสอบไฟล์และลองใหม่อีกครั้ง',
                        confirmButtonColor: '#dc3545'
                    });
                    importBtn.disabled = false;
                    importBtn.innerHTML = originalHtml;
                    importFile.value = ''; // Reset file input
                });
            }
        });
    }
});
</script>

<style>
/* Modern UI Polisher Styles */
.tracking-wide {
    letter-spacing: 0.05em;
}
.hover-bg-light:hover {
    background-color: #f8f9fa;
}
.btn-white {
    background-color: #fff;
    color: #495057;
}
.hover-lift {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
.transition-all {
    transition: all 0.2s ease-in-out;
}

/* Roster Table Specifics */
.roster-table-container {
    max-height: 70vh;
    overflow-y: auto;
}
.roster-table th {
    position: sticky;
    top: 0;
    z-index: 5;
}
.sticky-col.first-col {
    position: sticky;
    left: 0;
    z-index: 6;
}
.roster-table th.first-col {
    z-index: 11; /* Above both top and left scrolls */
}
.bg-primary-subtle-light {
    background-color: rgba(13, 110, 253, 0.03);
}

/* Custom Select Styling */
.custom-select-ui {
    appearance: none;
    border-radius: 8px;
    padding: 0.5rem 1.5rem 0.5rem 0.5rem;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
    background-size: 12px 12px;
}
.custom-select-ui:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    outline: none;
}
.custom-select-ui:hover {
    filter: brightness(0.95);
}

/* Indicators */
.position-relative {
    position: relative;
}
.changed-indicator {
    position: absolute;
    top: -4px;
    right: -4px;
    width: 10px;
    height: 10px;
    background-color: #ffc107;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 4px rgba(0,0,0,0.1);
}

/* Print Styles */
@media print {
    body {
        background-color: #fff !important;
    }
    /* Hide navigation, sidebar, and non-printable elements */
    #sidebar, .navbar, .btn, .card-footer, .bulk-toolbar, .input-group, .select-indicator, .form-check {
        display: none !important;
    }
    /* Reset margins/paddings for print */
    .container-fluid {
        padding: 0 !important;
        margin: 0 !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .roster-table-container {
        max-height: none !important;
        overflow: visible !important;
    }
    /* Format selects for printing */
    .custom-select-ui {
        appearance: none !important;
        background-image: none !important;
        border: none !important;
        padding: 0 !important;
        font-size: 0.75rem !important;
        color: #000 !important;
        background-color: transparent !important;
        width: 100% !important;
        text-align: center !important;
    }
    /* Force background colors to print if needed, though most browsers skip them unless configured. 
       We rely on the text being visible. */
    .text-danger { color: #dc3545 !important; }
    .text-primary { color: #0d6efd !important; }
}

/* Custom UI Checkbox & Select */
.form-check-input {
    width: 1.2em;
    height: 1.2em;
    border-color: #adb5bd;
    cursor: pointer;
}
.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
select.custom-select-ui {
    background-color: #f8f9fa;
    transition: all 0.2s ease-in-out;
}
select.custom-select-ui:hover {
    background-color: #e9ecef;
}
</style>
</x-app-layout>
