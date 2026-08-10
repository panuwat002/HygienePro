<x-app-layout>
    @php
        $isPersonnel = $type === 'personnel';
        $title = $isPersonnel ? 'ตรวจพนักงาน' : ($type === 'area' ? 'ตรวจพื้นที่' : 'ตรวจพื้นที่/เครื่องจักร');
        $icon = $isPersonnel ? 'bi-people-fill' : 'bi-tools';
    @endphp
    @section('header', $title)
    
    <style>
        .masonry-grid {
            column-count: 2;
            column-gap: 1.5rem;
        }
        @media (max-width: 767.98px) {
            .masonry-grid {
                column-count: 1;
            }
        }
        .masonry-item {
            break-inside: avoid;
            margin-bottom: 1.5rem;
        }
        
        /* Premium UI Polishing */
        .hover-elevate {
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .hover-elevate:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.08) !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
        }
        
        @keyframes fadeInUpStagger {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-stagger {
            opacity: 0;
            animation: fadeInUpStagger 0.4s ease-out forwards;
        }
        
        .pulse-btn {
            animation: pulse-shadow 2s infinite;
        }
        @keyframes pulse-shadow {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }
        
        /* Modern Shift Indicator */
        .shift-indicator {
            display: flex;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(254, 240, 138, 0.4) 0%, rgba(253, 224, 71, 0.1) 100%);
            border: 1px solid rgba(250, 204, 21, 0.3);
            backdrop-filter: blur(8px);
        }
        .shift-night-indicator {
            background: linear-gradient(135deg, rgba(167, 139, 250, 0.15) 0%, rgba(139, 92, 246, 0.05) 100%);
            border: 1px solid rgba(167, 139, 250, 0.3);
        }
        .shift-icon {
            font-size: 2.2rem;
            margin-right: 1.2rem;
            color: #eab308;
            text-shadow: 0 2px 10px rgba(234, 179, 8, 0.4);
        }
        .shift-night-icon {
            font-size: 2.2rem;
            margin-right: 1.2rem;
            color: #8b5cf6;
            text-shadow: 0 2px 10px rgba(139, 92, 246, 0.4);
        }
    </style>

    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">

            @if(session('error'))
            <div class="alert alert-danger d-flex align-items-start rounded-3 shadow-sm border-0 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-4 me-3 flex-shrink-0"></i>
                <div>
                    <strong>ไม่สำเร็จ</strong><br>
                    <span class="small">{{ session('error') }}</span>
                </div>
            </div>
            @endif
            @if(session('success'))
            <div class="alert alert-success d-flex align-items-start rounded-3 shadow-sm border-0 mb-3" role="alert">
                <i class="bi bi-check-circle-fill fs-4 me-3 flex-shrink-0"></i>
                <div class="small">{{ session('success') }}</div>
            </div>
            @endif
            @if(session('info'))
            <div class="alert alert-info d-flex align-items-start rounded-3 shadow-sm border-0 mb-3" role="alert">
                <i class="bi bi-info-circle-fill fs-4 me-3 flex-shrink-0"></i>
                <div class="small">{{ session('info') }}</div>
            </div>
            @endif

            <!-- Type Tabs -->
            <ul class="nav nav-pills nav-pills-modern flex-nowrap overflow-auto mb-4 animate-in" style="gap: 0.25rem;">
                <li class="nav-item flex-shrink-0 flex-fill text-center">
                    <a class="nav-link px-3 {{ $type === 'personnel' ? 'active' : '' }}"
                       href="{{ route('inspection.dashboard', 'personnel') }}">
                        <i class="bi bi-people-fill me-1"></i>พนักงาน
                    </a>
                </li>
                <li class="nav-item flex-shrink-0 flex-fill text-center">
                    <a class="nav-link px-3 {{ $type === 'machine' ? 'active' : '' }}"
                       href="{{ route('inspection.dashboard', 'machine') }}">
                        <i class="bi bi-gear-wide-connected me-1"></i>พื้นที่/เครื่องจักร
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

                    @if($type === 'personnel')
                        <div class="mt-3 d-flex gap-2">
                            <a href="{{ route('inspection.scan', $currentSession->id) }}" class="btn btn-primary flex-grow-1 rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-play-fill me-1"></i> ดำเนินการตรวจต่อ (Resume)
                            </a>
                            @if(isset($remainingList) && $remainingList->isNotEmpty())
                            <button class="btn btn-light text-primary flex-grow-1 rounded-pill fw-bold border shadow-sm collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#remainingListCollapse" aria-expanded="false">
                                <i class="bi bi-list-ul me-1"></i> ตกหล่น ({{ $remainingCount }})
                            </button>
                            @endif
                        </div>
                    @else
                        <div class="mt-3">
                            <form action="{{ route('inspection.start', $type) }}" method="POST" class="w-100 m-0 p-0">
                                @csrf
                                <input type="hidden" name="department_id" value="{{ $currentSession->department_id }}">
                                <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">
                                    <i class="bi bi-play-fill me-1"></i> ดำเนินการตรวจต่อ (Resume)
                                </button>
                            </form>
                        </div>
                    @endif

                    @if($type === 'personnel' && isset($remainingList) && $remainingList->isNotEmpty())
                            <div class="collapse mt-3" id="remainingListCollapse">
                                <div class="card card-body border-0 shadow-sm rounded-4 p-0 overflow-hidden">
                                    <div class="bg-light px-3 py-2 border-bottom text-center">
                                        <small class="text-muted fw-bold"><i class="bi bi-info-circle me-1"></i>แสดงรายชื่อตกหล่นเฉพาะพนักงานกะปกติ</small>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        @php
                                            $dashboardRecleanEmployeeIds = $pendingReCleans->flatMap(function($logs) {
                                                return $logs->pluck('employee_id');
                                            })->filter()->unique()->toArray();
                                        @endphp
                                        @foreach($remainingList->take(20) as $emp)
                                        <li class="list-group-item d-flex justify-content-between align-items-center bg-white px-3 py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-2 flex-shrink-0" style="width:32px; height:32px;">
                                                    @if($emp->profile_image)
                                                        <img src="{{ $emp->profile_image }}" alt="รูปโปรไฟล์ของ {{ $emp->fullname }}" class="w-100 h-100 rounded-circle object-fit-cover">
                                                    @else
                                                        <i class="bi bi-person text-secondary"></i>
                                                    @endif
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <span class="small fw-bold text-dark">{{ $emp->fullname }}</span>
                                                    @if(in_array($emp->id, $dashboardRecleanEmployeeIds))
                                                        <span class="badge bg-danger text-white shadow-sm" style="font-size: 0.65rem; width: fit-content;" title="มีรายการต้องแก้ไข (Re-clean)">
                                                            <i class="bi bi-tools me-1"></i> มีงานแก้เก่า
                                                        </span>
                                                    @endif
                                                </div>
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

                                    {{-- Bulk Pass Button --}}
                                    <div class="p-3 bg-light border-top">
                                        <button type="button" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm py-2" data-bs-toggle="modal" data-bs-target="#bulkPassModal" id="bulkPassTrigger">
                                            <i class="bi bi-check-all me-2 fs-5"></i> ผ่านทุกคนที่เหลือ (Pass All Remaining) — {{ $remainingCount }} คน
                                        </button>
                                    </div>
                                </div>
                            </div>


                        </div>
                    @endif
                </div>
            </div>
            @elseif(isset($currentSession) && $remainingCount == 0)
            <div class="card border-0 rounded-4 shadow-sm mb-4 bg-success bg-opacity-10 border border-success border-opacity-25">
                <div class="card-body p-4 position-relative overflow-hidden text-center">
                    <div class="bg-success text-white rounded-circle p-3 mx-auto mb-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 70px; height: 70px;">
                        <i class="bi bi-check-circle-fill fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-1 text-dark">ตรวจสอบครบถ้วนแล้ว</h5>
                    <p class="text-success mb-3 small fw-bold">
                        {{ $currentSession->department->dept_name }} | กะ{{ $currentSession->shift === 'morning' ? 'เช้า' : 'ดึก' }}
                    </p>
                    <p class="text-muted small mb-4">คุณได้ทำการตรวจสอบเป้าหมายทั้งหมดในรอบนี้เรียบร้อยแล้ว กรุณากด "จบงาน" เพื่อบันทึกข้อมูลและส่งให้หัวหน้าอนุมัติ</p>
                    
                    <div class="d-flex justify-content-center gap-2">
                        <a href="{{ route('inspection.scan', $currentSession->id) }}" class="btn btn-outline-success rounded-pill fw-bold shadow-sm px-4">
                            <i class="bi bi-search me-1"></i> ทบทวนข้อมูล
                        </a>
                        <form action="{{ route('inspection.finish', $currentSession->id) }}" method="POST" class="m-0">
                            @csrf
                            <button type="button" class="btn btn-success rounded-pill fw-bold shadow-sm px-4" onclick="confirmFinishSession(this)">
                                <i class="bi bi-check2-circle me-1"></i> จบงาน (Finish Job)
                            </button>
                        </form>
                    </div>
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
                                                    <img src="{{ $firstLog->employee->profile_image }}" alt="รูปโปรไฟล์ของพนักงาน" class="rounded-circle object-fit-cover" style="width: 40px; height: 40px;">
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
                                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold" style="opacity:0.6;cursor:not-allowed;" onclick="Swal.fire({icon:'info',title:'เริ่มรอบใหม่ก่อน',text:'กรุณากด \"เริ่มการตรวจสอบ\" ด้านล่างเพื่อเริ่มรอบตรวจใหม่ก่อนดำเนินการแก้ไข',confirmButtonText:'รับทราบ',confirmButtonColor:'#0d6efd'})">
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

            @if(isset($activeOtherSessions) && $activeOtherSessions->isNotEmpty())
            <div class="alert alert-warning shadow-sm mb-4 border-0 rounded-4 d-flex align-items-start animate-in animate-in-delay-1" style="background-color: #fff9e6;">
                <div class="bg-warning bg-opacity-25 text-warning rounded-circle p-2 me-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                    <i class="bi bi-people-fill fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-2">มีผู้กำลังดำเนินการตรวจสอบอยู่ขณะนี้ (Active Inspections)</h6>
                    <ul class="mb-2 ps-3 small text-dark">
                        @foreach($activeOtherSessions as $activeSess)
                            <li class="mb-1">
                                <strong>{{ $activeSess->inspector->name ?? 'Unknown' }}</strong> กำลังตรวจ 
                                <strong class="text-primary">{{ $activeSess->department->dept_name ?? 'รวมทั้งหมด' }}</strong> 
                                กะ{{ $activeSess->shift === 'morning' ? 'เช้า' : ($activeSess->shift === 'afternoon' ? 'บ่าย' : 'ดึก') }} 
                                <span class="badge bg-secondary ms-1">รอบที่ {{ $activeSess->round }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="text-muted small" style="font-size: 0.75rem;">
                        <i class="bi bi-info-circle me-1"></i>เพื่อป้องกันการตรวจซ้ำซ้อน กรุณาตรวจสอบให้แน่ใจก่อนเริ่มการตรวจในแผนกและกะเดียวกัน
                    </div>
                </div>
            </div>
            @endif

            <div class="card shadow-sm border-0 rounded-4 animate-in animate-in-delay-2">
                <div class="card-body p-4 p-md-5">
                    <form action="{{ route('inspection.start', $type) }}" method="POST">
                        @csrf
                        <input type="hidden" name="shift" id="selected_shift" value="{{ $currentAutoShift }}">
                        
                        <div class="mb-4" @if($type !== 'personnel') style="display: none;" @endif>
                            <label for="department_id" class="form-label fw-bold text-muted">เลือกแผนก</label>
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
                            <label for="location_filter" class="form-label fw-bold text-primary mb-3">
                                <i class="bi bi-pin-map-fill me-2"></i>เลือกพื้นที่ที่ต้องการตรวจ (Select Location)
                            </label>
                            <div class="position-relative animate-in animate-in-delay-1">
                                <select class="form-select form-select-lg border-2 shadow-sm" id="location_filter" style="border-radius: 12px; padding-left: 2.8rem; background-color: #f8faff; border-color: #e0e8f5; transition: all 0.3s ease; cursor: pointer;" onfocus="this.style.borderColor='#0d6efd'; this.style.boxShadow='0 0 0 0.25rem rgba(13, 110, 253, 0.25)';" onblur="this.style.borderColor='#e0e8f5'; this.style.boxShadow='none';">
                                    <option value="all" selected>-- ทั้งหมด --</option>
                                    {{-- Options will be populated via JS --}}
                                </select>
                                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-primary opacity-75"></i>
                            </div>
                        </div>

                        <!-- Location Overview Section -->
                        <div id="location-overview" class="mb-4 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-4 p-3 rounded-4 bg-light border-0 shadow-sm animate-in animate-in-delay-2">
                                <h6 class="fw-bold text-dark mb-0 d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i class="bi bi-bullseye"></i>
                                    </div>
                                    เป้าหมายการตรวจ (Inspection Targets)
                                </h6>
                                @if($type === 'area' || $type === 'machine')
                                <button type="button" class="btn btn-sm btn-primary bg-gradient rounded-pill px-3 py-1 shadow-sm fw-bold border-0" id="select-all-btn" style="font-size: 0.8rem; transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(13,110,253,0.3)';" onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
                                    <i class="bi bi-check2-all me-1"></i>เลือกทั้งหมด
                                </button>
                                @endif
                            </div>
                            <div class="masonry-grid" id="location-cards">
                                <!-- Cards will be injected here via JS -->
                            </div>
                        </div>

                         <div class="mb-4">
                            <label class="form-label fw-bold text-muted"><i class="bi bi-clock me-1"></i>กะการทำงาน (Shift)</label>
                            <div class="shift-indicator {{ $currentAutoShift == 'night' ? 'shift-night-indicator' : '' }} shadow-sm">
                                <div class="d-flex align-items-center {{ $currentAutoShift == 'night' ? 'shift-night-icon' : 'shift-icon' }}">
                                    @if($currentAutoShift == 'morning') <i class="bi bi-sun-fill"></i>
                                    @else <i class="bi bi-moon-stars-fill"></i>
                                    @endif
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark">
                                        @if($currentAutoShift == 'morning') กะเช้า (Morning)
                                        @else กะดึก (Night)
                                        @endif
                                    </h5>
                                    <small class="text-muted fw-medium">ตรวจสอบด้วยระบบ AI อัจฉริยะ (AI Smart Detected)</small>
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
                            <button type="submit" id="start-session-btn" class="btn btn-primary-custom btn-lg shadow rounded-pill py-3 fs-5 fw-bold pulse-btn">
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

            const shiftInput = document.getElementById('selected_shift');
            const shiftVal = shiftInput ? shiftInput.value : '';

            // Loading state
            overviewContainer.classList.remove('d-none');
            cardsContainer.innerHTML = '<div class="col-12 text-center py-4" style="column-span: all; -webkit-column-span: all;"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">กำลังโหลดข้อมูล...</p></div>';
            fetch(`/inspection/summary/{{ $type }}/${deptId}?shift=${shiftVal}`)
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        currentLocations = data.locations || [];

                        // Populate Location Filter (for Area/Machine)
                        if ('{{ $type }}' !== 'personnel' && locationFilter) {
                            locationFilter.innerHTML = '<option value="" selected disabled>-- กรุณาเลือกพื้นที่ --</option><option value="all">-- ทั้งหมด --</option>';
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
                             cardsContainer.innerHTML = `
                                <div class="col-12 py-5 text-center animate-in" style="column-span: all; -webkit-column-span: all; background: linear-gradient(135deg, #f8faff 0%, #eef2f9 100%); border-radius: 20px; border: 2px dashed #cdd7e5; transition: all 0.3s ease;">
                                    <div class="d-inline-flex align-items-center justify-content-center bg-white shadow-sm rounded-circle mb-4 animate-float" style="width: 80px; height: 80px; transition: transform 0.3s ease; cursor: pointer;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                        <i class="bi bi-map-fill text-primary" style="font-size: 2.5rem;"></i>
                                    </div>
                                    <h5 class="fw-bold text-dark mb-2">ยังไม่ได้เลือกพื้นที่เป้าหมาย</h5>
                                    <p class="text-muted mb-0" style="font-size: 0.95rem;">กรุณาเลือกพื้นที่จากเมนูด้านบน เพื่อเริ่มดำเนินการตรวจสอบ<br><small class="text-secondary">(Please select a location above to view targets)</small></p>
                                </div>
                             `;
                        } else {
                            // For Personnel, render the summary card directly
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
        
        // Removed old shiftInputs logic

        // 4. Render Logic with Progress
        function renderLocations(locations) {
            cardsContainer.innerHTML = '';
            
            if(!locations || locations.length === 0) {
                 overviewContainer.classList.add('d-none');
                 cardsContainer.innerHTML = '<div class="col-12 text-center text-muted py-3" style="column-span: all; -webkit-column-span: all;">ไม่มีข้อมูลจุดประจำการ</div>';
                 return;
            }

            overviewContainer.classList.remove('d-none');
            locations.forEach((loc, index) => {
                const total = loc.employees_count;
                const inspected = loc.inspected_count || 0;
                const hasEmployees = total > 0;
                const opacityClass = hasEmployees ? '' : 'opacity-75';
                const animDelay = (index * 0.05).toFixed(2);
                
                // Progress Logic
                let progressBadge = '';
                if('{{ $type }}' === 'personnel') {
                    if(hasEmployees) {
                        if(inspected >= total) {
                            progressBadge = `<span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 shadow-sm"><i class="bi bi-check-circle-fill me-1"></i>ครบแล้ว (${inspected}/${total})</span>`;
                        } else {
                            progressBadge = `<span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1 shadow-sm"><i class="bi bi-hourglass-split me-1"></i>ความคืบหน้า ${inspected}/${total}</span>`;
                        }
                    } else {
                        progressBadge = `<span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1 shadow-sm">ไม่มีพนักงาน</span>`;
                    }
                } else if ('{{ $type }}' === 'machine') {
                    // Machine inspection style
                    let totalMachines = 0;
                    let inspectedMachines = 0;
                    let noProductionMachines = 0;
                    if(loc.machines && loc.machines.length > 0) {
                        totalMachines = loc.machines.length;
                        inspectedMachines = loc.machines.filter(m => m.is_inspected).length;
                        noProductionMachines = loc.machines.filter(m => m.is_no_production).length;
                    }

                    if(totalMachines === 0) {
                         progressBadge = `<span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill">ไม่มีเครื่องจักร</span>`;
                    } else if(noProductionMachines >= totalMachines) {
                        progressBadge = `<span class="badge bg-secondary text-light rounded-pill"><i class="bi bi-dash-circle me-1"></i>งดใช้งานทั้งหมด</span>`;
                    } else if(inspectedMachines >= totalMachines) {
                        progressBadge = `<span class="badge bg-success bg-opacity-10 text-success rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>ครบแล้ว (${inspectedMachines}/${totalMachines})</span>`;
                    } else if(inspectedMachines > 0) {
                        progressBadge = `<span class="badge bg-warning bg-opacity-10 text-warning rounded-pill"><i class="bi bi-hourglass-split me-1"></i>ตรวจแล้ว ${inspectedMachines}/${totalMachines}</span>`;
                    } else {
                        progressBadge = `<span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill">ยังไม่ตรวจ (${totalMachines} เครื่อง)</span>`;
                    }
                } else {
                    // Area inspection style
                    if(inspected > 0) {
                        if (loc.is_no_production) {
                            progressBadge = `<span class="badge bg-secondary text-light rounded-pill"><i class="bi bi-dash-circle me-1"></i>งดใช้งาน</span>`;
                        } else {
                            progressBadge = `<span class="badge bg-success bg-opacity-10 text-success rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>ตรวจพื้นที่แล้ว</span>`;
                        }
                    } else {
                        progressBadge = `<span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill">ยังไม่ตรวจพื้นที่</span>`;
                    }
                }

                // Machine/Area Selection Logic (Area Mode Only)
                // Machine/Area Selection Logic
                let areaSelectionHtml = '';
                if ('{{ $type }}' === 'area') {
                     // Area Mode: Show ONLY Location (General Area)
                    const isAreaInspected = loc.inspected_count > 0;
                    if (isAreaInspected) {
                        if (loc.is_no_production) {
                            areaSelectionHtml += `<div class="text-muted small ms-2 my-2 fw-medium"><i class="bi bi-dash-circle text-secondary me-1"></i>งดใช้งาน (No Production)</div>`;
                        } else {
                            areaSelectionHtml += `<div class="text-muted small ms-2 my-2 fw-medium"><i class="bi bi-check2-all text-success me-1"></i>ตรวจสอบพื้นที่นี้แล้ว</div>`;
                        }
                    } else {
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
                    }
                } else if ('{{ $type }}' === 'machine') {
                    // Machine Mode: show machines + "ตรวจพื้นที่ทั่วไป" if the location
                    // itself carries area-type checkpoints (floors, walls, ceiling, etc.).
                    // The tab label promises "พื้นที่/เครื่องจักร" so both belong here.
                    let visibleMachines = 0;
                    let machineListHtml = '';

                    // General Area target for this location (loc:X)
                    if (loc.has_area_checkpoints) {
                        const areaTargetId = `target_loc_${loc.id}`;
                        if (loc.area_inspected) {
                            visibleMachines++;
                            const areaBadge = loc.area_no_production
                                ? '<span class="badge bg-secondary text-light"><i class="bi bi-dash-circle me-1"></i>ไม่ได้ใช้งาน</span>'
                                : '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>ตรวจแล้ว</span>';
                            machineListHtml += `
                                <div class="selection-menu">
                                    <div class="form-check p-0">
                                        <input type="checkbox" class="btn-check target-checkbox" disabled>
                                        <label class="btn btn-sm btn-outline-secondary text-muted bg-light border-0 w-100 rounded-3 text-start px-3 mb-2 py-2 d-flex justify-content-between align-items-center border-dashed" style="opacity: 0.7; cursor: not-allowed;">
                                            <span class="fw-medium"><i class="bi bi-layers me-2"></i>ตรวจพื้นที่ทั่วไป (พื้น/ผนัง/เพดาน)</span>
                                            <div class="check-indicator">${areaBadge}</div>
                                        </label>
                                    </div>
                                </div>
                            `;
                        } else {
                            visibleMachines++;
                            machineListHtml += `
                                <div class="selection-menu">
                                    <div class="form-check p-0">
                                        <input type="checkbox" class="btn-check target-checkbox" name="targets[]" value="loc:${loc.id}" id="${areaTargetId}" autocomplete="off">
                                        <label class="btn btn-sm btn-outline-primary w-100 rounded-3 text-start px-3 mb-2 py-2 d-flex justify-content-between align-items-center border-dashed" for="${areaTargetId}">
                                            <span class="fw-medium"><i class="bi bi-layers me-2"></i>ตรวจพื้นที่ทั่วไป (พื้น/ผนัง/เพดาน)</span>
                                            <div class="check-indicator"><i class="bi bi-circle check-box-icon"></i></div>
                                        </label>
                                    </div>
                                </div>
                            `;
                        }
                    }

                    const hasMachines = loc.machines && loc.machines.length > 0;
                    if (hasMachines) {
                        loc.machines.forEach(m => {
                            const isMInspected = m.is_inspected;
                            if (isMInspected) {
                                if (m.is_no_production) {
                                    visibleMachines++;
                                    const mTargetId = `target_machine_${m.id}`;
                                    machineListHtml += `
                                        <div class="selection-menu">
                                            <div class="form-check p-0">
                                                <input type="checkbox" class="btn-check target-checkbox" disabled>
                                                <label class="btn btn-sm btn-outline-secondary text-muted bg-light border-0 w-100 rounded-3 text-start px-3 mb-2 py-2 d-flex justify-content-between align-items-center" style="opacity: 0.6; cursor: not-allowed;">
                                                    <span class="fw-medium"><i class="bi bi-gear-wide-connected me-2"></i>${m.name}</span>
                                                    <div class="check-indicator">
                                                        <span class="badge bg-secondary text-light"><i class="bi bi-dash-circle me-1"></i>งดใช้งาน</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    `;
                                }
                                return; // Skip already inspected machines
                            }

                            visibleMachines++;
                            const mHasCheckpoints = m.has_checkpoints;
                            const mTargetId = `target_machine_${m.id}`;
                            
                            const mDisabledAttr = mHasCheckpoints ? '' : 'disabled';
                            const mLabelClass = mHasCheckpoints
                                ? 'btn-outline-info'
                                : 'btn-outline-secondary text-muted bg-light border-0';

                            const mStatusIcon = mHasCheckpoints
                                ? '<i class="bi bi-circle check-box-icon"></i>'
                                : '<span class="badge bg-secondary text-light">ไม่มีจุดตรวจ</span>';

                            machineListHtml += `
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
                    }

                    if (visibleMachines > 0) {
                        const collapseId = `collapse_loc_${loc.id}`;
                        // Collapse button covers area + machines together.
                        areaSelectionHtml += `
                            <button class="btn btn-sm btn-light w-100 mb-0 border text-dark d-flex justify-content-between align-items-center rounded-3 px-3 py-2 shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                                <span class="fw-bold"><i class="bi bi-list-ul me-2 text-primary"></i> รายการเป้าหมาย (${visibleMachines} รายการ)</span>
                                <i class="bi bi-chevron-expand text-muted"></i>
                            </button>
                            <div class="collapse mt-2" id="${collapseId}">
                                ${machineListHtml}
                            </div>
                        `;
                    } else if (hasMachines) {
                        areaSelectionHtml += `<div class="text-muted small ms-2 my-2 fw-medium"><i class="bi bi-check2-all text-success me-1"></i>ตรวจสอบครบทุกเครื่องแล้ว</div>`;
                    } else if (!loc.has_area_checkpoints) {
                        areaSelectionHtml += `<div class="text-muted small ms-2 my-2 fst-italic">ไม่มีเครื่องจักรในพื้นที่นี้</div>`;
                    }
                }

                const isCurrentShift = loc.is_current_shift || false;
                const shiftValue = String(loc.id).replace('shift_', '');
                
                const selectedShiftInput = document.getElementById('selected_shift');
                const isSelected = selectedShiftInput && selectedShiftInput.value === shiftValue;
                
                const cardBorderStyle = isSelected 
                    ? 'border: 2px solid rgba(59,130,246,1) !important; background: linear-gradient(to right, rgba(59,130,246,0.05), transparent);' 
                    : (isCurrentShift ? 'border: 2px solid rgba(59,130,246,0.5) !important; background: linear-gradient(to right, rgba(59,130,246,0.02), transparent);' : '');
                
                const currentShiftBadge = isCurrentShift 
                    ? '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary rounded-pill px-2 py-1 ms-2" style="font-size:0.65rem;"><i class="bi bi-star-fill me-1"></i>กะปัจจุบัน</span>' 
                    : '';
                    
                const selectedBadge = isSelected
                    ? '<i class="bi bi-check-circle-fill text-primary ms-auto fs-5 selected-badge"></i>'
                    : '';

                const clickHandler = '{{ $type }}' === 'personnel' ? `onclick="selectShift('${shiftValue}', '${loc.id}')"` : '';
                const pointerStyle = '{{ $type }}' === 'personnel' ? 'cursor: pointer;' : '';

                const html = `
                <div class="masonry-item location-card-col mb-3 animate-stagger" data-location-id="${loc.id}" style="animation-delay: ${animDelay}s">
                    <div class="card border-0 shadow-sm rounded-4 hover-elevate ${opacityClass} ${'{{ $type }}' === 'personnel' ? 'shift-card' : ''}" style="${cardBorderStyle} ${pointerStyle}" ${clickHandler}>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold fs-6 mb-0 text-dark d-flex align-items-center w-100">${loc.location_name}${currentShiftBadge}${selectedBadge}</h6>
                                ${progressBadge}
                            </div>
                            
                            ${'{{ $type }}' === 'personnel' ? `
                                <p class="text-muted small mb-3 text-truncate">${loc.description || '-'}</p>
                                <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top border-light">
                                    <small class="text-muted fw-medium"><i class="bi bi-people-fill text-secondary opacity-75 me-1"></i> มีพนักงาน ${total} คน</small>
                                    <small class="${inspected > 0 ? 'text-success fw-bold' : 'text-muted fw-medium'}"><i class="bi bi-check-circle-fill me-1 ${inspected > 0 ? '' : 'text-secondary opacity-50'}"></i>ตรวจแล้ว ${inspected} คน</small>
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
                if (newRoundContainer) newRoundContainer.classList.remove('d-none');
                submitButton.innerHTML = `เริ่มการตรวจสอบ (Start Inspection) <i class="bi bi-arrow-right ms-2"></i>`;
                submitButton.className = 'btn btn-primary-custom btn-lg shadow-sm py-3 fs-5';
            }
        }

        // Add confirmFinishSession for the Finish button
        window.confirmFinishSession = function(btn) {
            Swal.fire({
                title: 'ยืนยันจบงาน?',
                text: "ต้องการจบการตรวจสอบรอบนี้ใช่หรือไม่? หลังจากจบงานแล้วจะไม่สามารถแก้ไขข้อมูลได้",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ยืนยันจบงาน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>กำลังประมวลผล...';
                    btn.closest('form').submit();
                }
            });
        };

        window.selectShift = function(shiftValue, locationId) {
            const hiddenInput = document.getElementById('selected_shift');
            if (hiddenInput && hiddenInput.value !== shiftValue) {
                hiddenInput.value = shiftValue;
                
                // Update styling for all cards
                document.querySelectorAll('.shift-card').forEach(card => {
                    card.style.border = '2px solid transparent';
                    card.style.background = '';
                    const badge = card.querySelector('.selected-badge');
                    if (badge) badge.remove();
                });
                
                // Set styling for selected card
                const selectedCardCol = document.querySelector(`.location-card-col[data-location-id="${locationId}"] .shift-card`);
                if (selectedCardCol) {
                    selectedCardCol.style.border = '2px solid rgba(59,130,246,1)';
                    selectedCardCol.style.setProperty('border', '2px solid rgba(59,130,246,1)', 'important');
                    selectedCardCol.style.background = 'linear-gradient(to right, rgba(59,130,246,0.05), transparent)';
                    
                    const h6 = selectedCardCol.querySelector('h6');
                    if (h6 && !h6.querySelector('.selected-badge')) {
                        h6.insertAdjacentHTML('beforeend', '<i class="bi bi-check-circle-fill text-primary ms-auto fs-5 selected-badge"></i>');
                    }
                }
                
                // Call fetchStats to update inspected numbers for the selected shift
                if (typeof fetchStats === 'function') {
                    // Update location-cards container with loading without destroying layout
                    const cardsContainer = document.getElementById('location-cards');
                    if (cardsContainer) {
                        // Optionally show loading, but fetchStats handles it.
                        // Wait, fetchStats will wipe the cards and re-render them!
                        // That's fine because it will render with the new shift value and isSelected will be true.
                    }
                    fetchStats();
                }
            }
        };
    </script>
    @endpush
                </div>
            </div>
        </div>
    </div>
    @if(isset($currentSession) && $type === 'personnel' && isset($remainingList) && $remainingList->isNotEmpty())
        {{-- Bulk Pass Confirmation Modal --}}
        <div class="modal fade" id="bulkPassModal" tabindex="-1" aria-labelledby="bulkPassModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                    <div class="modal-header bg-success text-white border-0 py-3">
                        <h5 class="modal-title fw-bold" id="bulkPassModalLabel">
                            <i class="bi bi-shield-check me-2"></i>ยืนยัน Bulk Pass
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
                                พนักงานที่เหลืออีก <strong class="text-success fs-4">{{ $remainingCount }}</strong> คน
                                ในกะ<strong>{{ $currentSession->shift === 'morning' ? 'เช้า' : ($currentSession->shift === 'afternoon' ? 'บ่าย' : 'ดึก') }}</strong>
                                จะถูกบันทึกว่า <strong class="text-success">"ผ่าน"</strong> ทุกหัวข้อตรวจโดยอัตโนมัติ
                            </p>
                        </div>
                        <div class="alert alert-warning d-flex align-items-start rounded-3 mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 mt-1 flex-shrink-0"></i>
                            <div class="small">
                                <strong>คำเตือน:</strong> การดำเนินการนี้ไม่สามารถย้อนกลับได้ กรุณาตรวจสอบให้แน่ใจว่าคุณได้เดินตรวจพนักงานทุกคนเรียบร้อยแล้ว
                                และพนักงานที่ไม่ผ่านได้ถูกบันทึกไว้แล้ว
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-3 pt-0 gap-2">
                        <button type="button" class="btn btn-light rounded-pill flex-grow-1 fw-bold" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i> ยกเลิก
                        </button>
                        <form action="{{ route('inspection.bulk-pass', $currentSession->id) }}" method="POST" class="flex-grow-1 m-0">
                            @csrf
                            <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm" id="confirmBulkPassBtn">
                                <i class="bi bi-check-all me-1"></i> ยืนยัน ผ่านทุกคน
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
