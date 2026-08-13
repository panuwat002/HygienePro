<x-app-layout>
    @section('header', 'เลือกพนักงาน')

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">

            <!-- Session Header -->
            <div class="card border-0 shadow-sm mb-3 animate-in" style="background: var(--primary-soft); border-radius: var(--radius-lg);">
                <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <small class="fw-bold text-uppercase d-block" style="font-size: 0.7rem; letter-spacing: 1px; color: var(--primary);">กำลังตรวจ (Inspecting)</small>
                        <h6 class="fw-bold mb-0" style="color: var(--slate-800);">{{ $session->department->dept_name }}</h6>
                    </div>
                    <div class="text-end">
                        <span class="badge rounded-pill px-3" style="background: var(--primary);">รอบที่ {{ $session->round }}</span>
                        <small class="d-block mt-1" style="font-size: 0.75rem; color: var(--slate-500);">{{ str_starts_with($session->shift_label, 'กะ') ? $session->shift_label : 'กะ ' . $session->shift_label }}</small>
                    </div>
                </div>
            </div>

            <!-- Progress + Mode Toggle -->
            <div class="card border-0 shadow-sm mb-3 animate-in animate-in-delay-1" style="border-radius: var(--radius-lg);">
                <div class="card-body py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="fw-bold text-muted">ความก้าวหน้า (Progress)</small>
                        <span class="fw-bold" style="color: var(--primary);">{{ $inspectedCount }}/{{ $totalEmployees }}</span>
                    </div>
                    <div class="progress" style="height: 8px; border-radius: 4px;">
                        <div class="progress-bar" role="progressbar" style="width: {{ $progressPercent }}%; background: var(--primary); border-radius: 4px;"></div>
                    </div>
                    
                    <!-- Mode Toggle -->
                    <div class="d-flex gap-2 mt-3">
                        <a href="{{ route('inspection.scan', $session->id) }}" class="btn btn-outline-secondary btn-sm flex-fill text-center">
                            <i class="bi bi-qr-code me-1"></i> สแกน QR
                        </a>
                        <button class="btn btn-primary btn-sm flex-fill text-center" disabled>
                            <i class="bi bi-list-ul me-1"></i> เลือกจากรายชื่อ
                        </button>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="mb-3 animate-in animate-in-delay-2">
                <form action="{{ route('inspection.browse', $session->id) }}" method="GET" id="search-form">
                    <input type="hidden" name="show_all" value="{{ $showAll ? '1' : '0' }}" id="show_all_input">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white border-end-0" style="border-radius: var(--radius-md) 0 0 var(--radius-md);"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" id="employee-search" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อ, รหัสพนักงาน... (กด Enter)" style="border-radius: 0 var(--radius-md) var(--radius-md) 0;" autofocus value="{{ $search }}">
                    </div>
                </form>
                <div class="form-check form-switch mt-2 ps-5 ms-1">
                    <input class="form-check-input" type="checkbox" id="toggleShowAll" {{ $showAll ? 'checked' : '' }} style="cursor: pointer; transform: scale(1.1);">
                    <label class="form-check-label text-muted small fw-bold" for="toggleShowAll" style="cursor: pointer;">
                        แสดงพนักงานทั้งหมดในแผนก (รวมกะอื่น)
                    </label>
                </div>
            </div>

            <!-- Filter Tabs (Segmented Control) -->
            <div class="card border-0 shadow-sm mb-3 animate-in animate-in-delay-2" style="border-radius: var(--radius-lg);">
                <div class="card-body p-2">
                    <div class="d-flex gap-1 browse-tabs">
                        <button type="button" class="browse-tab" data-filter="all">
                            <i class="bi bi-people-fill me-1"></i>ทั้งหมด
                            <span class="tab-badge">{{ $totalEmployees }}</span>
                        </button>
                        <button type="button" class="browse-tab tab-pending active" data-filter="pending">
                            <i class="bi bi-clock me-1"></i>ยังไม่ตรวจ
                            <span class="tab-badge">{{ $totalEmployees - $inspectedCount }}</span>
                        </button>
                        <button type="button" class="browse-tab tab-done" data-filter="done">
                            <i class="bi bi-check-circle me-1"></i>ตรวจแล้ว
                            <span class="tab-badge">{{ $inspectedCount }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Employee List Grouped by Location -->
            <div id="employee-list">
                @foreach($grouped as $locationName => $employees)
                <div class="location-group mb-3 animate-in" style="animation-delay: {{ $loop->index * 80 }}ms;">
                    <div class="d-flex align-items-center mb-2 px-1">
                        @if($locationName === 'รายชื่อพนักงานทั้งหมด')
                        <i class="bi bi-people-fill text-primary me-2"></i>
                        @else
                        <i class="bi bi-geo-alt-fill text-primary me-2"></i>
                        @endif
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.85rem;">{{ $locationName }}</h6>
                        <span class="badge bg-light text-muted ms-2" style="font-size: 0.7rem;">{{ $employees->count() }} คน</span>
                    </div>
                    <div class="card border-0 shadow-sm" style="border-radius: var(--radius-lg); overflow: hidden;">
                        @foreach($employees as $emp)
                        @php 
                            $isInspected = in_array($emp->id, $inspectedIds); 
                            $isAbsent = in_array($emp->id, $absentIds ?? []);
                        @endphp
                        @if($isInspected || $isAbsent)
                        {{-- Already inspected or absent: non-clickable --}}
                        <div class="employee-item d-flex align-items-center p-3 border-bottom employee-done"
                           data-name="{{ mb_strtolower($emp->fullname) }}"
                           data-id="{{ mb_strtolower($emp->employee_id) }}"
                           data-status="done"
                           style="transition: all 0.2s ease; opacity: 0.85;">
                           
                            <a href="{{ route('inspection.checklist', ['session' => $session->id, 'hash' => $emp->employee_id, 'from' => 'browse'] + request()->query()) }}" class="d-flex flex-grow-1 align-items-center text-decoration-none" style="cursor: pointer;">
                                <div class="me-3 position-relative" style="min-width: 44px;">
                                    @if($emp->profile_image)
                                        <img src="{{ $emp->profile_image }}" alt="รูปโปรไฟล์ของ {{ $emp->fullname }}" class="rounded-circle" width="44" height="44" style="object-fit: cover; opacity: 0.7;">
                                    @else
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            <i class="bi bi-person-fill text-secondary"></i>
                                        </div>
                                    @endif
                                    <span class="position-absolute" style="bottom: -2px; right: -2px; font-size: 1rem;">
                                        @if($isAbsent)
                                        <i class="bi bi-dash-circle-fill text-secondary"></i>
                                        @else
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        @endif
                                    </span>
                                </div>
                                
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-muted" style="font-size: 0.9rem;">
                                        {{ $emp->fullname }}
                                        @if($showAll && optional($emp->shift)->shift_name !== $session->shift)
                                            <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">นอกกะ</span>
                                        @endif
                                    </div>
                                    <small class="text-muted">{{ $emp->employee_id }}</small>
                                </div>
                                
                                <div>
                                    @if($isAbsent)
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1 small me-2">
                                        <i class="bi bi-person-dash-fill me-1"></i>ลางาน
                                    </span>
                                    @elseif(in_array($emp->id, $inspectedOtherShiftIds ?? []) && !in_array($emp->id, $inspectedCurrentIds ?? []))
                                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1 small me-2">
                                        <i class="bi bi-check-circle-fill me-1"></i>ตรวจแล้ว (รอบก่อน)
                                    </span>
                                    @else
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 small me-2">
                                        <i class="bi bi-lock-fill me-1"></i>ตรวจแล้ว
                                    </span>
                                    @endif
                                </div>
                                
                                <div class="pe-2">
                                    <i class="bi bi-pencil-square text-muted opacity-50"></i>
                                </div>
                            </a>
                        </div>
                        @else
                        {{-- Pending: clickable link and absent button --}}
                        <div class="employee-item d-flex align-items-center p-3 border-bottom employee-pending"
                           data-name="{{ mb_strtolower($emp->fullname) }}"
                           data-id="{{ mb_strtolower($emp->employee_id) }}"
                           data-status="pending"
                           style="transition: all 0.2s ease;">
                            
                            <a href="{{ route('inspection.checklist', ['session' => $session->id, 'hash' => $emp->employee_id, 'from' => 'browse'] + request()->query()) }}" class="d-flex flex-grow-1 align-items-center text-decoration-none" style="cursor: pointer;">
                                <div class="me-3 position-relative" style="min-width: 44px;">
                                    @if($emp->profile_image)
                                        <img src="{{ $emp->profile_image }}" alt="รูปโปรไฟล์ของ {{ $emp->fullname }}" class="rounded-circle" width="44" height="44" style="object-fit: cover;">
                                    @else
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            <i class="bi bi-person-fill text-secondary"></i>
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark" style="font-size: 0.9rem;">
                                        {{ $emp->fullname }}
                                        @if(in_array($emp->id, $pendingRecleanEmployeeIds))
                                            <span class="badge bg-danger text-white ms-1 shadow-sm" style="font-size: 0.65rem;" title="มีรายการต้องแก้ไข (Re-clean) จากรอบก่อนหน้า">
                                                <i class="bi bi-tools me-1"></i> มีงานแก้เก่า
                                            </span>
                                        @endif
                                        @if(in_array($emp->id, $inspectedOtherShiftIds ?? []))
                                            <span class="badge bg-success text-white ms-1" style="font-size: 0.65rem;">
                                                <i class="bi bi-check-circle me-1"></i>ตรวจแล้ว (กะอื่น)
                                            </span>
                                        @elseif($showAll && optional($emp->shift)->shift_name !== $session->shift)
                                            <span class="badge bg-secondary text-white ms-1" style="font-size: 0.65rem;">นอกกะ</span>
                                        @endif
                                    </div>
                                    <small class="text-muted">{{ $emp->employee_id }}</small>
                                </div>
                                
                                <div class="me-2 pe-3 border-end">
                                    <i class="bi bi-chevron-right text-muted"></i>
                                </div>
                            </a>

                            <div class="ms-2">
                                <form action="{{ route('inspection.mark-absent', ['session' => $session->id, 'employee' => $emp->id]) }}" method="POST" class="d-inline mark-absent-form">
                                    @csrf
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-2 py-1 shadow-sm" onclick="confirmMarkAbsent(this, '{{ $emp->fullname }}')" style="font-size: 0.75rem;">
                                        <i class="bi bi-person-dash me-1"></i>ลา/ขาด
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mb-4">
                <!-- Pagination removed for grouping view -->
            </div>

            {{-- Bulk Pass Button (Strictly Current Shift Only) --}}
            @php
                // Count employees strictly in the current shift that haven't been inspected
                $shiftRemainingCount = 0;
                $currentShiftGroupName = 'เป้าหมายกะปัจจุบัน (' . $session->shift_label . ')';
                
                if ($grouped->has($currentShiftGroupName)) {
                    foreach($grouped[$currentShiftGroupName] as $emp) {
                        if (!in_array($emp->id, $inspectedIds) && !in_array($emp->id, $absentIds ?? [])) {
                            $shiftRemainingCount++;
                        }
                    }
                }
            @endphp
            
            @php
                $totalRemainingCount = $totalEmployees - $inspectedCount;
            @endphp
            
            @if($totalRemainingCount > 0)
                @php
                    $hasAnyInCurrentShift = $grouped->has($currentShiftGroupName);
                @endphp
                <div class="card border-0 shadow-sm mb-4 animate-in animate-in-delay-3" style="background: {{ $shiftRemainingCount > 0 ? 'var(--success-soft)' : '#f8f9fa' }}; border-radius: var(--radius-lg); border-left: 4px solid {{ $shiftRemainingCount > 0 ? 'var(--success)' : '#ced4da' }} !important;">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" style="color: {{ $shiftRemainingCount > 0 ? 'var(--success)' : '#6c757d' }};">ตรวจพนักงาน {{ $session->shift_label }} ครบแล้วใช่ไหม?</h6>
                            <small class="text-muted">
                                @if($shiftRemainingCount > 0)
                                    ระบบจะให้ "ผ่าน" เฉพาะพนักงานในกะปัจจุบันเท่านั้น
                                @elseif(!$hasAnyInCurrentShift)
                                    แผนกนี้ไม่มีพนักงานที่ทำงานในกะปัจจุบัน
                                @else
                                    คุณได้ตรวจสอบพนักงานกะปัจจุบันครบทุกคนแล้ว
                                @endif
                            </small>
                        </div>
                        @if($shiftRemainingCount > 0)
                            <button type="button" class="btn btn-success rounded-pill fw-bold shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#bulkPassModal">
                                <i class="bi bi-check-all me-1"></i> ผ่านทุกคนที่เหลือ ({{ $shiftRemainingCount }})
                            </button>
                        @elseif(!$hasAnyInCurrentShift)
                            <button type="button" class="btn btn-secondary rounded-pill fw-bold shadow-sm px-4" disabled>
                                <i class="bi bi-dash-circle me-1"></i> ไม่มีพนักงานในกะนี้
                            </button>
                        @else
                            <button type="button" class="btn btn-secondary rounded-pill fw-bold shadow-sm px-4" disabled>
                                <i class="bi bi-check-all me-1"></i> กะปัจจุบันครบแล้ว
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Action Buttons -->
            <div class="d-flex gap-2 mt-2 pb-5 animate-in animate-in-delay-3">
                <form action="{{ route('inspection.pause', $session->id) }}" method="POST" class="flex-fill">
                    @csrf
                    <button type="submit" class="btn btn-lg w-100 py-3 fw-bold border-0 shadow-sm" style="background: white; color: var(--warning); border-radius: var(--radius-md);">
                        <i class="bi bi-pause-circle me-2"></i> พักการตรวจ
                    </button>
                </form>
                <form action="{{ route('inspection.finish', $session->id) }}" method="POST" id="finish-session-form" class="flex-fill">
                    @csrf
                    <button type="button" class="btn btn-lg w-100 py-3 fw-bold shadow-sm" style="background: var(--slate-800); color: white; border-radius: var(--radius-md);" onclick="confirmFinishSession(this)">
                        <i class="bi bi-check2-circle me-2"></i> จบงาน
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if($shiftRemainingCount > 0)
        @push('modals')
        {{-- Bulk Pass Confirmation Modal --}}
        <div class="modal fade" id="bulkPassModal" tabindex="-1" aria-labelledby="bulkPassModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                    <div class="modal-header bg-success text-white border-0 py-3">
                        <h5 class="modal-title fw-bold" id="bulkPassModalLabel">
                            <i class="bi bi-shield-check me-2"></i>ยืนยัน Bulk Pass (เฉพาะกะปัจจุบัน)
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="text-center mb-3">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-people-fill text-success" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="fw-bold text-dark">คุณกำลังจะให้ผ่านทั้งหมด</h5>
                            <p class="text-muted mb-0">
                                พนักงานที่เหลืออีก <strong class="text-success fs-4">{{ $shiftRemainingCount }}</strong> คน
                                <strong class="text-danger border-bottom border-danger">เฉพาะ {{ $session->shift_label }}</strong><br>
                                จะถูกบันทึกว่า <strong class="text-success">"ผ่าน"</strong> ทุกหัวข้อตรวจโดยอัตโนมัติ
                            </p>
                        </div>
                        <div class="alert alert-warning d-flex align-items-start rounded-3 mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 mt-1 flex-shrink-0"></i>
                            <div class="small">
                                <strong>พนักงานกะอื่นจะไม่ได้รับผลกระทบ:</strong> การดำเนินการนี้จะเปลี่ยนสถานะเฉพาะพนักงานที่มีรอบทำงานตรงกับกะของ Session นี้เท่านั้น (พนักงานกะอื่นจะยังคงสถานะเดิม)
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-3 pt-0 gap-2">
                        <button type="button" class="btn btn-light rounded-pill flex-grow-1 fw-bold" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i> ยกเลิก
                        </button>
                        <form action="{{ route('inspection.bulk-pass', $session->id) }}" method="POST" class="flex-grow-1 m-0">
                            @csrf
                            <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-check-all me-1"></i> ยืนยัน ผ่านทุกคน ({{ $shiftRemainingCount }} คน)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endpush
    @endif

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('employee-search');
            const items = document.querySelectorAll('.employee-item');
            const groups = document.querySelectorAll('.location-group');
            const filterBtns = document.querySelectorAll('.browse-tab');
            let activeFilter = 'pending';

            // Apply default filter on page load
            applyFilters();

            // Search functionality is now server-side on Enter

            // Filter buttons
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    filterBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    activeFilter = this.dataset.filter;
                    applyFilters();
                });
            });

            function applyFilters() {
                const query = searchInput.value.toLowerCase().trim();
                
                items.forEach(item => {
                    const name = item.dataset.name;
                    const id = item.dataset.id;
                    const status = item.dataset.status;
                    
                    const matchesSearch = !query || name.includes(query) || id.includes(query);
                    const matchesFilter = activeFilter === 'all' || status === activeFilter;
                    
                    if (matchesSearch && matchesFilter) {
                        item.classList.remove('d-none');
                    } else {
                        item.classList.add('d-none');
                    }
                });

                // Hide empty location groups
                groups.forEach(group => {
                    const visibleItems = group.querySelectorAll('.employee-item:not(.d-none)');
                    if (visibleItems.length > 0) {
                        group.classList.remove('d-none');
                    } else {
                        group.classList.add('d-none');
                    }
                });
            }

            // Finish Session Confirmation
            window.confirmFinishSession = function(btn) {
                Swal.fire({
                    title: 'ยืนยันสรุปยอดการตรวจ?',
                    html: 'ตรวจแล้ว <strong>{{ $inspectedCount }}</strong> จาก <strong>{{ $totalEmployees }}</strong> คน ({{ $progressPercent }}%)'
                        + ({{ $totalEmployees - $inspectedCount }} > 0 ? '<br><span class="text-danger">ยังเหลืออีก {{ $totalEmployees - $inspectedCount }} คนที่ยังไม่ได้ตรวจ</span>' : '')
                        + '<br><small class="text-muted">หลังจากจบงานแล้วจะไม่สามารถตรวจเพิ่มในรอบนี้ได้อีก</small>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'ใช่, จบงานเลย',
                    cancelButtonText: 'ยังก่อน',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> กำลังบันทึก...';
                        document.getElementById('finish-session-form').submit();
                    }
                });
            }

            // Mark Absent Confirmation
            window.confirmMarkAbsent = function(btn, employeeName) {
                Swal.fire({
                    title: 'ยืนยันการลางาน/ขาด?',
                    html: `ต้องการบันทึกว่า <strong>${employeeName}</strong> ลางานหรือขาดงาน ใช่หรือไม่?<br><small class="text-muted">ระบบจะข้ามการตรวจพนักงานคนนี้และเพิ่มความก้าวหน้าทันที</small>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#64748b',
                    cancelButtonColor: '#e2e8f0',
                    confirmButtonText: 'ใช่, บันทึกการลา',
                    cancelButtonText: '<span class="text-dark">ยกเลิก</span>',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                        btn.closest('form').submit();
                    }
                });
            }

            // Toggle Show All Employees
            const toggleShowAll = document.getElementById('toggleShowAll');
            if (toggleShowAll) {
                toggleShowAll.addEventListener('change', function() {
                    const url = new URL(window.location.href);
                    if (this.checked) {
                        url.searchParams.set('show_all', '1');
                    } else {
                        url.searchParams.set('show_all', '0');
                    }
                    
                    url.searchParams.delete('page');

                    // Preserve current search query
                    const currentSearch = searchInput.value.trim();
                    if (currentSearch) {
                        url.searchParams.set('q', currentSearch);
                    }

                    // Show loading state
                    document.body.style.opacity = '0.5';
                    window.location.href = url.toString();
                });
            }
        });
    </script>

    <style>
        .browse-tabs {
            background: var(--slate-100);
            border-radius: 12px;
            padding: 4px;
        }
        .browse-tab {
            flex: 1;
            border: none;
            background: transparent;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--slate-500);
            padding: 0.65rem 0.5rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        .browse-tab:hover:not(.active) {
            color: var(--slate-700);
            background: rgba(255,255,255,0.5);
        }
        .browse-tab.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
        }
        .browse-tab.tab-pending.active {
            background: #f59e0b;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
        .browse-tab.tab-done.active {
            background: #10b981;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        .tab-badge {
            background: rgba(255,255,255,0.25);
            border-radius: 20px;
            padding: 1px 8px;
            font-size: 0.75rem;
            font-weight: 800;
        }
        .browse-tab.active .tab-badge {
            background: rgba(255,255,255,0.3);
            color: #fff;
        }
        .employee-item:hover {
            background: var(--primary-soft) !important;
        }
        .employee-done {
            background: var(--slate-50);
            opacity: 0.7;
        }
        .employee-done:hover {
            opacity: 1;
        }
        .employee-pending {
            background: #fff;
        }
    </style>
    @endpush
    @include('inspections.alert_script')
</x-app-layout>
