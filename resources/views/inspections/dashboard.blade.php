<x-app-layout>
    @php
        $isPersonnel = $type === 'personnel';
        $title = $isPersonnel ? 'ตรวจพนักงาน (Personnel Search)' : 'ตรวจพื้นที่/เครื่องจักร (Area/Machine)';
        $icon = $isPersonnel ? 'bi-people-fill' : 'bi-tools';
    @endphp
    @section('header', $title)
    
    <style>
        .masonry-grid {
            column-count: 2;
            column-gap: 1rem;
        }
        @media (max-width: 767.98px) {
            .masonry-grid {
                column-count: 1;
            }
        }
        .masonry-item {
            break-inside: avoid;
            margin-bottom: 1rem;
        }
    </style>

    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">
            
            <!-- Type Tabs -->
            <ul class="nav nav-pills nav-pills-modern nav-fill mb-4 animate-in">
                <li class="nav-item">
                    <a class="nav-link {{ $type === 'personnel' ? 'active' : '' }}" 
                       href="{{ route('inspection.dashboard', 'personnel') }}">
                        <i class="bi bi-people-fill me-2"></i>ตรวจพนักงาน<span class="d-none d-sm-inline"> (Personnel)</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $type === 'area' ? 'active' : '' }}" 
                       href="{{ route('inspection.dashboard', 'area') }}">
                        <i class="bi bi-shop me-2"></i>ตรวจพื้นที่<span class="d-none d-sm-inline"> (Area)</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $type === 'machine' ? 'active' : '' }}" 
                       href="{{ route('inspection.dashboard', 'machine') }}">
                        <i class="bi bi-gear-wide-connected me-2"></i>ตรวจเครื่องจักร<span class="d-none d-sm-inline"> (Machine)</span>
                    </a>
                </li>
            </ul>

            <!-- Active Session / Remaining Items Card -->
            @if(isset($scheduledTasks) && $scheduledTasks->isNotEmpty())
            <div class="card border-0 rounded-4 shadow-sm mb-4 card-accent-info animate-in animate-in-delay-1">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-circle bg-info bg-opacity-10 text-info me-3">
                            <i class="bi bi-calendar-check fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">งานที่กำหนดตามแผน (Today's Schedule)</h5>
                            <p class="text-info mb-0 small fw-bold">ประจำวันที่ {{ now()->format('d/m/Y') }}</p>
                        </div>
                    </div>

                    <div class="list-group list-group-flush rounded-3 border overflow-hidden bg-white shadow-sm">
                        @foreach($scheduledTasks as $task)
                        <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold mb-1">{{ $task['schedule']->title }}</h6>
                                <div class="small text-muted">
                                    <i class="bi bi-clock me-1"></i>{{ $task['formatted_window'] }} | 
                                    <i class="bi bi-crosshair me-1"></i>{{ $task['schedule']->targetable?->name ?? $task['schedule']->targetable?->location_name ?? 'Unknown' }}
                                    @if($task['schedule']->department)
                                        | <i class="bi bi-building me-1"></i>{{ $task['schedule']->department->dept_name }}
                                    @endif
                                </div>
                            </div>
                            <div>
                                @if($task['status'] === 'completed')
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Completed</span>
                                @elseif($task['status'] === 'missed')
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2"><i class="bi bi-x-circle-fill me-1"></i>Missed</span>
                                @else
                                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-2"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Active Session / Remaining Items Card -->
            @if(isset($currentSession) && $remainingCount > 0)
            <div class="card border-0 rounded-4 shadow-sm mb-4 bg-primary bg-opacity-10 border border-primary border-opacity-25">
                <div class="card-body p-4 position-relative overflow-hidden">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary text-white rounded-circle p-3 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px;">
                            <i class="bi bi-clipboard-data fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">การตรวจยังไม่เสร็จสิ้น</h5>
                            <p class="text-primary mb-0 small fw-bold">
                                {{ $currentSession->department->dept_name }} | กะ{{ $currentSession->shift }}
                            </p>
                        </div>
                        <div class="ms-auto text-end">
                            <h2 class="fw-bold text-primary mb-0">{{ $remainingCount }}</h2>
                            <small class="text-muted">รายการที่เหลือ</small>
                        </div>
                    </div>
                    
                    <div class="progress bg-white mb-3" style="height: 10px; border-radius: 10px;">
                        <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: {{ $progressPercent }}%"></div>
                    </div>

                    @if($type === 'personnel' && isset($remainingList) && $remainingList->isNotEmpty())
                        <div class="mt-3">
                            <button class="btn btn-light text-primary w-100 rounded-pill fw-bold border shadow-sm collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#remainingListCollapse" aria-expanded="false">
                                <i class="bi bi-list-ul me-2"></i> ดูรายชื่อตกหล่น ({{ $remainingCount }})
                            </button>
                            <div class="collapse mt-2" id="remainingListCollapse">
                                <div class="card card-body border-0 shadow-sm rounded-4 p-0 overflow-hidden">
                                    <div class="bg-light px-3 py-2 border-bottom text-center">
                                        <small class="text-muted fw-bold"><i class="bi bi-info-circle me-1"></i>แสดงรายชื่อตกหล่นเฉพาะพนักงานกะปกติ</small>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        @foreach($remainingList->take(20) as $emp)
                                        <li class="list-group-item d-flex justify-content-between align-items-center bg-white px-3 py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-2 flex-shrink-0" style="width:32px; height:32px;">
                                                    @if($emp->profile_image)
                                                        <img src="{{ $emp->profile_image }}" class="w-100 h-100 rounded-circle object-fit-cover">
                                                    @else
                                                        <i class="bi bi-person text-secondary"></i>
                                                    @endif
                                                </div>
                                                <span class="small fw-bold text-dark">{{ $emp->fullname }}</span>
                                            </div>
                                            <a href="{{ route('inspection.checklist', ['session' => $currentSession->id, 'hash' => $emp->qr_code_hash ?? $emp->employee_id]) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                ตรวจ
                                            </a>
                                        </li>
                                        @endforeach
                                        @if($remainingList->count() > 20)
                                            <li class="list-group-item text-center text-muted small bg-light">และอีก {{ $remainingList->count() - 20 }} คน...</li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Pending Re-cleans Section -->
            @if($pendingReCleans->isNotEmpty())
            <div class="card border-0 rounded-4 shadow-sm mb-4 bg-warning bg-opacity-10 border border-warning border-opacity-50">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#recleanCollapse">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning text-dark rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-dark">รายการกักตัวเตรียมแก้ไข ({{ $pendingReCleans->count() }})</h5>
                                <p class="text-muted small mb-0">มีรายการที่ต้องแก้ไขและตรวจสอบซ้ำ</p>
                            </div>
                        </div>
                        <i class="bi bi-chevron-down text-muted"></i>
                    </div>

                    <div class="collapse show" id="recleanCollapse">
                        <div class="list-group list-group-flush rounded-3 border overflow-hidden bg-white shadow-sm">
                            @foreach($pendingReCleans as $groupKey => $logs)
                                @php
                                    $firstLog = $logs->first();
                                    $name = $firstLog->employee->fullname ?? ($firstLog->machine->name ?? ($firstLog->location->location_name ?? 'Unknown'));

                                    // FIX: Use qr_code_hash OR string employee_id, NOT integer ID. Remove base64_encode.
                                    // Only for Personnel type. For Area/Machine, employee is null.
                                    $empCode = optional($firstLog->employee)->qr_code_hash ?? optional($firstLog->employee)->employee_id;
                                    
                                    // FIX: For Re-cleans, ALWAYS use the ORIGINAL Session ID to avoid creating new rounds.
                                    // The Controller must allow editing 'Completed' sessions if it's a re-clean.
                                    $targetSessionId = $firstLog->session_id;

                                    $actionUrl = '#'; // Default

                                    if ($targetSessionId) {
                                        if ($firstLog->employee_id) {
                                            $actionUrl = route('inspection.checklist', ['session' => $targetSessionId, 'hash' => $empCode]);
                                        } elseif ($firstLog->machine_id) {
                                            $actionUrl = route('inspection.area.bulk', [
                                                'department' => $firstLog->session->department_id,
                                                'session' => $targetSessionId,
                                                'targets' => 'machine:' . $firstLog->machine_id,
                                            ]);
                                        } elseif ($firstLog->location_id) {
                                            $actionUrl = route('inspection.area.bulk', [
                                                'department' => $firstLog->session->department_id,
                                                'session' => $targetSessionId,
                                                'targets' => 'loc:' . $firstLog->location_id,
                                            ]);
                                        }
                                    }
                                    
                                    // Special Case: Area checklist often finds/creates session automatically?
                                    // If so, we might not need targetSessionId check for Area.
                                    // But let's keep it safe. If user has NO session, they should start one.
                                    
                                    // Redundant safety: if not employee_id, ensure we don't use empCode.
                                @endphp
                                <div class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3 position-relative">
                                                @if($firstLog->employee_id && $firstLog->employee->profile_image)
                                                    <img src="{{ $firstLog->employee->profile_image }}" class="rounded-circle object-fit-cover" style="width: 40px; height: 40px;">
                                                @else
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                        <i class="bi bi-{{ $firstLog->employee_id ? 'person' : 'gear' }}"></i>
                                                    </div>
                                                @endif
                                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white">
                                                    {{ $logs->count() }}
                                                </span>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark">
                                                    {{ $name }}
                                                    @if($firstLog->inspected_at && $firstLog->inspected_at->lt(now()->startOfDay()))
                                                        <span class="badge bg-danger ms-2" style="font-size: 0.7em;">LATE (ข้ามวัน)</span>
                                                    @endif
                                                </div>
                                                <div class="d-flex flex-column small mt-1">
                                                    <span class="text-muted mb-1" style="font-size: 0.8em;">
                                                        <i class="bi bi-geo-alt me-1"></i>{{ $firstLog->session->department->dept_name ?? '-' }} | 
                                                        <i class="bi bi-clock mx-1"></i>{{ $firstLog->inspected_at ? $firstLog->inspected_at->format('H:i') : '-' }} น.
                                                    </span>
                                                    <span class="text-secondary mb-1" style="font-size: 0.8em;">
                                                        <i class="bi bi-person-check me-1"></i>ตรวจโดย: {{ $firstLog->session->inspector->name ?? 'Unknown' }}
                                                    </span>
                                                    <span class="text-danger fw-bold">
                                                        <i class="bi bi-exclamation-circle me-1"></i>{{ $logs->first()->checkpoint->title }} 
                                                        @if($logs->count() > 1) <span class="badge bg-danger rounded-pill ms-1">+{{ $logs->count() - 1 }}</span> @endif
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        @if($actionUrl !== '#')
                                            <a href="{{ $actionUrl }}" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold">
                                                แก้ไข
                                            </a>
                                        @else
                                            <button type="button" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold" onclick="alert('กรุณากด \'เริ่มการตรวจสอบ\' ด้านล่างเพื่อเริ่มรอบตรวจใหม่ก่อนดำเนินการแก้ไขครับ');">
                                                แก้ไข
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="card shadow-sm border-0 rounded-4 animate-in animate-in-delay-2">
                <div class="card-body p-4 p-md-5">
                    <form action="{{ route('inspection.start', $type) }}" method="POST">
                        @csrf
                        
                        <div class="mb-4" @if($type !== 'personnel') style="display: none;" @endif>
                            <label for="department_id" class="form-label fw-bold text-muted">เลือกแผนก (Department)</label>
                            <select class="form-select form-select-lg" name="department_id" id="department_id" @if($type === 'personnel') required @endif>
                                <option value="" selected disabled>-- กรุณาเลือกแผนก --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ (isset($currentSession) && $currentSession->department_id == $dept->id) ? 'selected' : '' }}>
                                        {{ $dept->dept_code }} - {{ $dept->dept_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Location Filter for Area/Machine --}}
                        <div class="mb-4" id="location-filter-container" @if($type === 'personnel') style="display: none;" @endif>
                            <label for="location_filter" class="form-label fw-bold text-muted">เลือกพื้นที่ (Select Location)</label>
                            <select class="form-select form-select-lg" id="location_filter">
                                <option value="all" selected>-- ทั้งหมด (All Locations) --</option>
                                {{-- Options will be populated via JS --}}
                            </select>
                        </div>

                        <!-- Location Overview Section -->
                        <div id="location-overview" class="mb-4 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-muted mb-0"><i class="bi bi-geo-alt-fill me-2"></i>สรุปเป้าหมายการตรวจ</h6>
                                @if($type === 'area' || $type === 'machine')
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 border-0 bg-light" id="select-all-btn" style="font-size: 0.75rem;">
                                    <i class="bi bi-check-all me-1"></i>เลือกทั้งหมด
                                </button>
                                @endif
                            </div>
                            <div class="masonry-grid" id="location-cards">
                                <!-- Cards will be injected here via JS -->
                            </div>
                        </div>

                         <div class="mb-4">
                            <label class="form-label fw-bold text-muted"><i class="bi bi-clock me-1"></i>กะการทำงาน (Current Shift)</label>
                            <div class="shift-indicator @if($currentAutoShift == 'morning') shift-morning @elseif($currentAutoShift == 'afternoon') shift-afternoon @else shift-night @endif">
                                <div class="shift-icon">
                                    @if($currentAutoShift == 'morning') <i class="bi bi-sun-fill"></i>
                                    @elseif($currentAutoShift == 'afternoon') <i class="bi bi-brightness-high-fill"></i>
                                    @else <i class="bi bi-moon-stars-fill"></i>
                                    @endif
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark">
                                        @if($currentAutoShift == 'morning') กะเช้า (Morning)
                                        @elseif($currentAutoShift == 'afternoon') กะบ่าย (Afternoon)
                                        @else กะดึก (Night)
                                        @endif
                                    </h5>
                                    <small class="text-muted">ระบบตรวจจับกะอัตโนมัติ (Auto-detected)</small>
                                </div>
                            </div>
                         </div>

                        @if($type === 'personnel')
                        <div id="new-round-container" class="form-check mb-4 p-3 border rounded-3 bg-light {{ isset($currentSession) ? 'd-none' : '' }}">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="force_new_round" id="force_new_round" value="1" style="transform: scale(1.2);">
                            <label class="form-check-label fw-bold text-secondary" for="force_new_round">
                                <i class="bi bi-plus-circle-dotted me-1"></i> ต้องการขึ้นรอบใหม่ (Start New Round)
                            </label>
                            <div class="form-text ms-4 small text-muted">
                                ติ๊กเลือกหากต้องการตัดรอบเดิมและเริ่มนับเป็น "รอบถัดไป" ทันที
                            </div>
                        </div>
                        @endif

                        <div class="d-grid mt-5">
                            <button type="submit" id="start-session-btn" class="btn btn-primary-custom btn-lg shadow-sm py-3 fs-5 animate-pulse-glow">
                                {{ isset($currentSession) ? 'กลับเข้าสู่การตรวจ (Resume Inspection)' : 'เริ่มการตรวจสอบ (Start Inspection)' }} <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    let currentSessionData = null;
    document.addEventListener('DOMContentLoaded', function() {
        const deptSelect = document.getElementById('department_id');
        const overviewContainer = document.getElementById('location-overview');
        const cardsContainer = document.getElementById('location-cards');
        const locationFilter = document.getElementById('location_filter');
        const locationFilterContainer = document.getElementById('location-filter-container');

        // 2. Fetch Data Function
        let currentLocations = []; // Store locations globally for filtering

        function fetchStats() {
            let deptId = 'all';
            if ('{{ $type }}' === 'personnel') {
                deptId = deptSelect.value;
                if(!deptId) {
                    overviewContainer.classList.add('d-none');
                    return;
                }
            }

            // Loading state
            overviewContainer.classList.remove('d-none');
            cardsContainer.innerHTML = '<div class="col-12 text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">กำลังโหลดข้อมูล...</p></div>';

            fetch(`/inspection/summary/{{ $type }}/${deptId}`)
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        currentLocations = data.locations || [];

                        // Populate Location Filter (for Area/Machine)
                        if ('{{ $type }}' !== 'personnel' && locationFilter) {
                            locationFilter.innerHTML = '<option value="" selected disabled>-- กรุณาเลือกพื้นที่ (Please Select) --</option><option value="all">-- ทั้งหมด (All Locations) --</option>';
                            if (currentLocations.length > 0) {
                                currentLocations.forEach(loc => {
                                    const option = document.createElement('option');
                                    option.value = loc.id;
                                    option.textContent = loc.location_name;
                                    locationFilter.appendChild(option);
                                });
                            }
                            // Reset filter to empty (forcing selection)
                            locationFilter.value = ""; 
                            
                            // Don't render locations yet for Area/Machine
                             cardsContainer.innerHTML = '<div class="col-12 empty-state py-5"><i class="bi bi-geo-alt-fill empty-state-icon animate-float"></i><p class="empty-state-text">กรุณาเลือกพื้นที่เพื่อเริ่มการตรวจสอบ<br><small class="text-muted">(Please select a location to start)</small></p></div>';
                        } else {
                            // For Personnel, render immediately
                            renderLocations(currentLocations);
                        }
                        
                        // Handle Session UI
                        refreshSessionUI(data);
                    } else {
                        const errorMsg = data.message || 'ไม่สามารถโหลดข้อมูลได้';
                        const errDiv = document.createElement('div');
                        errDiv.className = 'col-12 text-center text-danger';
                        errDiv.textContent = errorMsg;
                        cardsContainer.innerHTML = '';
                        cardsContainer.appendChild(errDiv);
                    }
                })
                .catch(err => {
                    console.error('Fetch Error:', err);
                    const errDiv = document.createElement('div');
                    errDiv.className = 'col-12 text-center text-danger';
                    errDiv.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + err.message;
                    cardsContainer.innerHTML = '';
                    cardsContainer.appendChild(errDiv);
                });
        }

        // 3. Event Listeners
        if('{{ $type }}' === 'personnel') {
            deptSelect.addEventListener('change', fetchStats);
        } else {
            fetchStats(); 
            // Location Filter Change Event
            if (locationFilter) {
                locationFilter.addEventListener('change', function() {
                    const selectedLocId = this.value;
                    
                    if (!selectedLocId) return;

                    let filtered = [];
                    if (selectedLocId === 'all') {
                        filtered = currentLocations;
                    } else {
                        // filtered = currentLocations.filter(loc => loc.id == selectedLocId); // loose comparison for string/int types
                        // Actually, renderLocations expects array.
                        // But wait, if we filter from JS array, we don't need to depend on DOM elements being present/hidden.
                        // We can just re-render.
                        filtered = currentLocations.filter(loc => loc.id == selectedLocId);
                    }
                    renderLocations(filtered);
                });
            }
        }
        
        // shiftInputs.forEach(input => {
        //    input.addEventListener('change', fetchStats);
        // });

        // 4. Render Logic with Progress
        function renderLocations(locations) {
            cardsContainer.innerHTML = '';
            
            if(!locations || locations.length === 0) {
                 overviewContainer.classList.add('d-none');
                 cardsContainer.innerHTML = '<div class="col-12 text-center text-muted py-3">ไม่มีข้อมูลจุดประจำการ</div>';
                 return;
            }

            overviewContainer.classList.remove('d-none');
            locations.forEach(loc => {
                const total = loc.employees_count;
                const inspected = loc.inspected_count || 0;
                const hasEmployees = total > 0;
                const opacityClass = hasEmployees ? '' : 'opacity-75';
                
                // Progress Logic
                let progressBadge = '';
                if('{{ $type }}' === 'personnel') {
                    if(hasEmployees) {
                        if(inspected >= total) {
                            progressBadge = `<span class="badge bg-success rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>ครบแล้ว (${inspected}/${total})</span>`;
                        } else {
                            progressBadge = `<span class="badge bg-warning text-dark rounded-pill"><i class="bi bi-hourglass-split me-1"></i>ความคืบหน้า ${inspected}/${total}</span>`;
                        }
                    } else {
                        progressBadge = `<span class="badge bg-secondary rounded-pill">ไม่มีพนักงาน</span>`;
                    }
                } else if ('{{ $type }}' === 'machine') {
                    // Machine inspection style
                    let totalMachines = 0;
                    let inspectedMachines = 0;
                    if(loc.machines && loc.machines.length > 0) {
                        totalMachines = loc.machines.length;
                        inspectedMachines = loc.machines.filter(m => m.is_inspected).length;
                    }

                    if(totalMachines === 0) {
                         progressBadge = `<span class="badge bg-secondary rounded-pill">ไม่มีเครื่องจักร</span>`;
                    } else if(inspectedMachines >= totalMachines) {
                        progressBadge = `<span class="badge bg-success rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>ครบแล้ว (${inspectedMachines}/${totalMachines})</span>`;
                    } else if(inspectedMachines > 0) {
                        progressBadge = `<span class="badge bg-warning text-dark rounded-pill"><i class="bi bi-hourglass-split me-1"></i>ตรวจแล้ว ${inspectedMachines}/${totalMachines}</span>`;
                    } else {
                        progressBadge = `<span class="badge bg-secondary rounded-pill">ยังไม่ตรวจ (${totalMachines} เครื่อง)</span>`;
                    }
                } else {
                    // Area inspection style
                    if(inspected > 0) {
                        progressBadge = `<span class="badge bg-success rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>ตรวจพื้นที่แล้ว</span>`;
                    } else {
                        progressBadge = `<span class="badge bg-secondary rounded-pill">ยังไม่ตรวจพื้นที่</span>`;
                    }
                }

                // Machine/Area Selection Logic (Area Mode Only)
                // Machine/Area Selection Logic
                let areaSelectionHtml = '';
                if ('{{ $type }}' === 'area') {
                     // Area Mode: Show ONLY Location (General Area)
                    const isAreaInspected = loc.inspected_count > 0;
                    const hasCheckpoints = loc.has_checkpoints;
                    const areaTargetId = `target_loc_${loc.id}`;
                    
                    const disabledAttr = hasCheckpoints ? '' : 'disabled';
                    const labelClass = hasCheckpoints 
                        ? (isAreaInspected ? 'btn-outline-success' : 'btn-outline-primary')
                        : 'btn-outline-secondary text-muted bg-light border-0';
                    
                    const statusIcon = hasCheckpoints
                        ? (isAreaInspected ? '<i class="bi bi-check-circle-fill me-1 text-success"></i>' : '<i class="bi bi-circle check-box-icon"></i>')
                        : '<span class="badge bg-secondary text-light">ไม่มีจุดตรวจ</span>';

                    areaSelectionHtml += `
                        <div class="mt-2 selection-menu">
                            <div class="form-check p-0">
                                <input type="checkbox" class="btn-check target-checkbox" name="targets[]" value="loc:${loc.id}" id="${areaTargetId}" autocomplete="off" ${disabledAttr}>
                                <label class="btn btn-sm ${labelClass} w-100 rounded-3 text-start px-3 mb-2 py-2 border-dashed d-flex justify-content-between align-items-center" for="${areaTargetId}" style="${!hasCheckpoints ? 'opacity: 0.7; cursor: not-allowed;' : ''}">
                                    <span class="fw-medium"><i class="bi bi-layers me-2"></i>ตรวจพื้นที่ทั่วไป (General Area)</span>
                                    <div class="check-indicator">
                                        ${statusIcon}
                                    </div>
                                </label>
                            </div>
                        </div>
                    `;
                } else if ('{{ $type }}' === 'machine') {
                    // Machine Mode: Show ONLY Machines
                    if (loc.machines && loc.machines.length > 0) {
                        loc.machines.forEach(m => {
                            const isMInspected = m.is_inspected;
                            const mHasCheckpoints = m.has_checkpoints;
                            const mTargetId = `target_machine_${m.id}`;
                            
                            const mDisabledAttr = mHasCheckpoints ? '' : 'disabled';
                            const mLabelClass = mHasCheckpoints
                                ? (isMInspected ? 'btn-outline-success' : 'btn-outline-info')
                                : 'btn-outline-secondary text-muted bg-light border-0';

                            const mStatusIcon = mHasCheckpoints
                                ? (isMInspected ? '<i class="bi bi-check-circle-fill me-1 text-success"></i>' : '<i class="bi bi-circle check-box-icon"></i>')
                                : '<span class="badge bg-secondary text-light">ไม่มีจุดตรวจ</span>';

                            areaSelectionHtml += `
                                <div class="selection-menu">
                                    <div class="form-check p-0">
                                        <input type="checkbox" class="btn-check target-checkbox" name="targets[]" value="machine:${m.id}" id="${mTargetId}" autocomplete="off" ${mDisabledAttr}>
                                        <label class="btn btn-sm ${mLabelClass} w-100 rounded-3 text-start px-3 mb-2 py-2 d-flex justify-content-between align-items-center" for="${mTargetId}" style="${!mHasCheckpoints ? 'opacity: 0.7; cursor: not-allowed;' : ''}">
                                            <span class="fw-medium"><i class="bi bi-gear-wide-connected me-2"></i>${m.name}</span>
                                            <div class="check-indicator">
                                                ${mStatusIcon}
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                         areaSelectionHtml += `<div class="text-muted small ms-2 my-2 fst-italic">ไม่มีเครื่องจักรในพื้นที่นี้</div>`;
                    }
                }

                const html = `
                <div class="masonry-item location-card-col" data-location-id="${loc.id}">
                    <div class="card border-0 shadow-sm rounded-4 ${opacityClass}">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h6 class="fw-bold fs-5 mb-0 text-dark">${loc.location_name}</h6>
                                ${progressBadge}
                            </div>
                            
                            ${'{{ $type }}' === 'personnel' ? `
                                <p class="text-muted small mb-3">${loc.description || '-'}</p>
                                <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                    <small class="text-muted"><i class="bi bi-people me-1"></i> มีพนักงาน ${total} คน</small>
                                </div>
                            ` : areaSelectionHtml}
                        </div>
                    </div>
                </div>`;
                
                cardsContainer.innerHTML += html;
            });
        }
    });

        // 5. Select All & Selection Count Logic
        document.addEventListener('click', function(e) {
            const selectAllBtn = e.target.closest('#select-all-btn');
            if (selectAllBtn) {
                // Find visible checkboxes
                // Note: We use the cardsContainer ID to ensure we look inside the cards
                const container = document.getElementById('location-cards');
                if(!container) return;

                const checkboxes = container.querySelectorAll('.location-card-col:not(.d-none) .target-checkbox:not(:disabled)');
                
                if(checkboxes.length === 0) return;

                // Determine target state (if any is unchecked, we verify all; else uncheck all)
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                const targetState = !allChecked;
                
                checkboxes.forEach(cb => {
                    cb.checked = targetState;
                    // Trigger change event for any listeners
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                });

                updateSubmitButton();
                
                selectAllBtn.innerHTML = targetState ? '<i class="bi bi-check-all me-1"></i>เลือกทั้งหมด' : '<i class="bi bi-dash-circle me-1"></i>ยกเลิกทั้งหมด';
            }
        });

        const overviewContainer = document.getElementById('location-overview');
        if (overviewContainer) {
            overviewContainer.addEventListener('change', function(e) {
                if (e.target.classList.contains('target-checkbox')) {
                    updateSubmitButton();
                }
            });
        }

        function updateSubmitButton() {
            if ('{{ $type }}' === 'personnel') return; // Only for area mode
            const submitButton = document.getElementById('start-session-btn');
            if (!submitButton) return;

            const selectedCount = document.querySelectorAll('.target-checkbox:checked').length;
            if (selectedCount > 0) {
                submitButton.innerHTML = `เริ่มการตรวจสอบ (${selectedCount} รายการ) <i class="bi bi-arrow-right ms-2"></i>`;
                submitButton.classList.replace('btn-primary-custom', 'btn-success');
                submitButton.classList.remove('btn-warning');
            } else {
                // If session exists but nothing selected, show default
                // We'll manage session state in the fetchStats then
                refreshSessionUI(currentSessionData);
            }
        }

        function refreshSessionUI(data) {
            currentSessionData = data;
            const newRoundContainer = document.getElementById('new-round-container');
            const submitButton = document.getElementById('start-session-btn');
            if (!submitButton) return;

            const selectedCount = document.querySelectorAll('.target-checkbox:checked').length;
            if (selectedCount > 0) {
                updateSubmitButton();
                return;
            }

            if (data && data.session_exists) {
                if (data.session_status === 'in_progress') {
                    if (newRoundContainer) newRoundContainer.classList.add('d-none');
                    submitButton.innerHTML = `กลับเข้าสู่การตรวจรอบที่ ${data.session_round} <i class="bi bi-arrow-right ms-2"></i>`;
                    submitButton.className = 'btn btn-primary-custom btn-lg shadow-sm py-3 fs-5';
                } else if (data.session_status === 'paused') {
                    if (newRoundContainer) newRoundContainer.classList.add('d-none');
                    submitButton.innerHTML = `ดำเนินการตรวจต่อ (รอบที่ ${data.session_round}) <span class="badge bg-warning text-dark ms-2">Paused</span>`;
                    submitButton.className = 'btn btn-warning btn-lg shadow-sm py-3 fs-5 fw-bold text-dark';
                } else {
                    // Completed or other
                    if (newRoundContainer) newRoundContainer.classList.remove('d-none');
                    submitButton.innerHTML = `เริ่มการตรวจสอบ (Start Inspection) <i class="bi bi-arrow-right ms-2"></i>`;
                    submitButton.className = 'btn btn-primary-custom btn-lg shadow-sm py-3 fs-5';
                }
            } else {
                if (newRoundContainer) newRoundContainer.classList.add('d-none');
                submitButton.innerHTML = `เริ่มการตรวจสอบ (Start Inspection) <i class="bi bi-arrow-right ms-2"></i>`;
                submitButton.className = 'btn btn-primary-custom btn-lg shadow-sm py-3 fs-5';
            }
        }
    </script>
    @endpush
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
