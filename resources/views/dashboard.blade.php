<x-app-layout>
    @section('header', 'แดชบอร์ดสรุปผล')

    <!-- Welcome Banner Option -->
    <div class="card mb-4">
        {{-- p-md-5 gave this banner more height than the five stat cards below it
             put together, for one line of greeting. --}}
        <div class="card-body p-3 p-md-4 d-flex align-items-center">
            <div class="me-3 rounded-circle d-flex align-items-center justify-content-center bg-primary text-white flex-shrink-0" style="width: 48px; height: 48px;">
                <i class="bi bi-person fs-5"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-1">สวัสดี, {{ Auth::user()->name }}!</h5>
                <p class="mb-0 text-muted fs-6">
                    <i class="bi bi-calendar3 me-1"></i> {{ \Carbon\Carbon::now()->locale('th')->translatedFormat('j F Y') }}
                    <span class="mx-3 opacity-25">|</span>
                    <i class="bi bi-cloud-sun me-1"></i> กะ: {{ \Carbon\Carbon::now()->format('H') < 12 ? 'เช้า (Morning)' : (\Carbon\Carbon::now()->format('H') < 20 ? 'บ่าย (Afternoon)' : 'กลางคืน (Night)') }}
                </p>
            </div>
        </div>
    </div>

    {{-- 🔔 Random Audit Alert (For Supervisors/Managers) --}}
    @if(isset($todayAudits) && $todayAudits->isNotEmpty())
    <div class="card mb-4 border-0 shadow-sm overflow-hidden glass-card" style="border-left: 5px solid #f59e0b !important;">
        <div class="card-body p-4" style="background: linear-gradient(135deg, rgba(255,251,235,0.8) 0%, rgba(255,255,255,0.9) 100%);">
            <div class="d-flex align-items-start">
                <div class="me-3 flex-shrink-0 position-relative">
                    <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm position-relative z-1" style="width: 56px; height: 56px; background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                        <i class="bi bi-shield-exclamation text-white fs-4"></i>
                    </div>
                    <div class="position-absolute top-0 start-0 w-100 h-100 rounded-circle" style="background: var(--hygiene-warning); animation: ripple 2s infinite ease-out; z-index: 0;"></div>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center mb-2">
                        <h5 class="fw-bold text-warning-emphasis mb-0 me-2" style="color: #b45309 !important;"><i class="bi bi-bullseye me-1"></i> ภารกิจสุ่มตรวจวันนี้ (Random Audit Required)</h5>
                        <span class="badge rounded-pill shadow-sm" style="background-color: var(--hygiene-warning); animation: pulse 2s infinite;">
                            {{ $todayAudits->count() }} แผนก
                        </span>
                    </div>
                    <p class="text-muted mb-3 small">ระบบได้สุ่มเลือกให้คุณลงไปสุ่มตรวจพนักงาน เครื่องจักร และพื้นที่ ของแผนกด้านล่าง เพื่อ Cross-check ผลการตรวจแบบรวบยอด</p>
                    
                    <div class="row g-3">
                        @foreach($todayAudits as $audit)
                        <div class="col-12">
                            <div class="d-flex flex-column flex-md-row align-items-md-center p-3 bg-white rounded-3 border shadow-sm hover-translate-right" style="cursor: pointer;">
                                <div class="d-flex align-items-center mb-3 mb-md-0 me-md-4">
                                    <div class="me-3">
                                        <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                            <i class="bi bi-building fs-4" style="color: var(--hygiene-warning);"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark fs-5">{{ $audit->department->dept_name ?? 'Unknown' }}</h6>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>{{ $audit->shift_label }}
                                            <span class="mx-1">•</span>
                                            <i class="bi bi-people me-1"></i>สุ่ม {{ $audit->sample_size }} คน
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="d-flex flex-wrap gap-2 ms-md-auto">
                                    <a href="{{ route('inspection.dashboard', 'personnel') }}?is_sampling=1" class="btn btn-sm rounded-pill px-3 shadow-sm fw-bold text-primary bg-primary bg-opacity-10 border-0 btn-hover-primary">
                                        <i class="bi bi-people-fill me-1"></i>ตรวจพนักงาน
                                    </a>
                                    <a href="{{ route('inspection.dashboard', 'machine') }}?is_sampling=1" class="btn btn-sm rounded-pill px-3 shadow-sm fw-bold text-info bg-info bg-opacity-10 border-0 btn-hover-info">
                                        <i class="bi bi-gear-wide-connected me-1"></i>ตรวจเครื่องจักร
                                    </a>
                                    <a href="{{ route('inspection.area.bulk', $audit->department_id) }}?is_sampling=1" class="btn btn-sm rounded-pill px-3 shadow-sm fw-bold text-success bg-success bg-opacity-10 border-0 btn-hover-success">
                                        <i class="bi bi-geo-alt-fill me-1"></i>ตรวจพื้นที่
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
        }
    </style>
    @endif

    <!-- Summary Cards -->
    <div class="row g-3 g-xl-4 mb-4">
        {{-- Card 1: งานตรวจวันนี้ --}}
        <div class="col-xl col-md-6">
            <div class="card h-100 position-relative summary-stat-card glass-card">
                <div class="position-absolute top-0 start-0 bottom-0" style="width: 4px; background: var(--hygiene-primary); border-radius: 4px 0 0 4px;"></div>
                <div class="card-body p-3 ps-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.08em;">งานตรวจวันนี้</p>
                            <h2 class="fw-bold mb-0 text-dark" style="font-size: 1.75rem; line-height: 1;">{{ $inspectionsToday }}</h2>
                        </div>
                        <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 44px; height: 44px; background: var(--hygiene-primary-soft);">
                            <i class="bi bi-clipboard-check" style="font-size: 1.15rem; color: var(--hygiene-primary);"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: รอทวนสอบ --}}
        <div class="col-xl col-md-6">
            <div class="card h-100 position-relative summary-stat-card glass-card">
                <div class="position-absolute top-0 start-0 bottom-0" style="width: 4px; background: var(--hygiene-warning); border-radius: 4px 0 0 4px;"></div>
                <div class="card-body p-3 ps-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.08em;">รอทวนสอบ</p>
                            <h2 class="fw-bold mb-0 text-dark" style="font-size: 1.75rem; line-height: 1;">{{ $pendingVerificationCount }}</h2>
                        </div>
                        <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 44px; height: 44px; background: var(--hygiene-warning-soft);">
                            <i class="bi bi-shield-exclamation" style="font-size: 1.15rem; color: var(--hygiene-warning);"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2b: รออนุมัติ — only for whoever can actually sign it off.
             A QA manager's whole job lives behind this number, and until it was
             here the dashboard only showed them somebody else's queue. --}}
        @can('approve')
        <div class="col-xl col-md-6">
            {{-- Not one link: the total is 225 but the two categories are reviewed
                 separately, and a single link had to pick one, landing a manager
                 on 50 of the 225 they had just read. Each number opens its own. --}}
            <div class="card h-100 position-relative summary-stat-card glass-card">
                <div class="position-absolute top-0 start-0 bottom-0" style="width: 4px; background: var(--hygiene-success); border-radius: 4px 0 0 4px;"></div>
                <div class="card-body p-3 ps-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.08em;">รออนุมัติ</p>
                            <h2 class="fw-bold mb-0 text-dark" style="font-size: 1.75rem; line-height: 1;">{{ $awaitingApprovalCount }}</h2>
                        </div>
                        <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 44px; height: 44px; background: var(--hygiene-success-soft);">
                            <i class="bi bi-hourglass-split" style="font-size: 1.15rem; color: var(--hygiene-success);"></i>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-1 mt-2 pt-2 border-top">
                        <a href="{{ route('inspection.verification', ['filter_type' => 'person', 'tab' => 'awaiting_approval']) }}"
                           class="text-decoration-none d-inline-flex align-items-center gap-1 text-muted"
                           style="font-size: 0.7rem;"
                           aria-label="ดูงานตรวจพนักงานที่รออนุมัติ {{ $awaitingApprovalPersonCount }} รายการ">
                            <i class="bi bi-people"></i>พนักงาน
                            <span class="fw-bold text-dark">{{ $awaitingApprovalPersonCount }}</span>
                        </a>
                        <span class="text-black-50" style="font-size: 0.7rem;">·</span>
                        <a href="{{ route('inspection.verification', ['filter_type' => 'machine', 'tab' => 'awaiting_approval']) }}"
                           class="text-decoration-none d-inline-flex align-items-center gap-1 text-muted"
                           style="font-size: 0.7rem;"
                           aria-label="ดูงานตรวจพื้นที่และเครื่องจักรที่รออนุมัติ {{ $awaitingApprovalAreaCount }} รายการ">
                            <i class="bi bi-gear-wide-connected"></i>พื้นที่/เครื่องจักร
                            <span class="fw-bold text-dark">{{ $awaitingApprovalAreaCount }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endcan

        {{-- Card 3: สั่งแก้ไขใหม่ --}}
        <div class="col-xl col-md-6">
            <a href="{{ route('corrective.index') }}" class="text-decoration-none">
                <div class="card h-100 position-relative summary-stat-card glass-card">
                    <div class="position-absolute top-0 start-0 bottom-0" style="width: 4px; background: var(--hygiene-danger); border-radius: 4px 0 0 4px;"></div>
                    <div class="card-body p-3 ps-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted mb-1 small fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.08em;">สั่งแก้ไขใหม่</p>
                                <h2 class="fw-bold mb-0 text-dark" style="font-size: 1.75rem; line-height: 1;">{{ $recleanCount }}</h2>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 44px; height: 44px; background: var(--hygiene-danger-soft);">
                                <i class="bi bi-arrow-counterclockwise" style="font-size: 1.15rem; color: var(--hygiene-danger);"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Card 4: อัตราผ่าน --}}
        <div class="col-xl col-md-6">
            <div class="card h-100 position-relative summary-stat-card glass-card">
                <div class="position-absolute top-0 start-0 bottom-0" style="width: 4px; background: var(--hygiene-success); border-radius: 4px 0 0 4px;"></div>
                <div class="card-body p-3 ps-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.08em;">อัตราผ่าน</p>
                            <h2 class="fw-bold mb-0 text-dark" style="font-size: 1.75rem; line-height: 1;">{{ $passRate }}%</h2>
                        </div>
                        <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 44px; height: 44px; background: var(--hygiene-success-soft);">
                            <i class="bi bi-graph-up-arrow" style="font-size: 1.15rem; color: var(--hygiene-success);"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Sessions (Admin View) -->
    @if(isset($activeSessions) && $activeSessions->isNotEmpty())
    <div class="card border-0 rounded-4 shadow-sm mb-4 bg-white position-relative overflow-hidden">
        <div class="position-absolute top-0 start-0 bottom-0 bg-warning" style="width: 5px;"></div>
        <div class="card-header bg-white border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="bg-warning bg-opacity-10 text-warning rounded p-2 me-3">
                    <i class="bi bi-broadcast fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">ผู้ที่กำลังดำเนินการตรวจสอบอยู่ขณะนี้ (Active Inspections)</h5>
                </div>
            </div>
        </div>
        <div class="card-body p-4 pt-3">
            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0 text-nowrap">
                    <thead class="bg-light text-muted small text-uppercase rounded-3">
                        <tr>
                            <th class="ps-3 rounded-start">ผู้ตรวจ</th>
                            <th>ประเภท</th>
                            <th>แผนก</th>
                            <th>กะ / รอบ</th>
                            <th class="text-center">เวลาเริ่ม</th>
                            <th class="text-end pe-3 rounded-end">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activeSessions as $activeSess)
                        <tr class="border-bottom border-light">
                            <td class="ps-3 py-3" data-label="ผู้ตรวจ">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-2 text-primary fw-bold" style="width:36px; height:36px;">
                                        {{ Str::upper(substr($activeSess->inspector->name ?? 'U', 0, 1)) }}
                                    </div>
                                    <span class="fw-bold text-dark">{{ $activeSess->inspector->name ?? 'Unknown' }}</span>
                                </div>
                            </td>
                            <td data-label="ประเภท">
                                @if($activeSess->type === 'personnel')
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded px-2 py-1"><i class="bi bi-people-fill me-1"></i>พนักงาน</span>
                                @elseif($activeSess->type === 'machine')
                                    <span class="badge bg-info bg-opacity-10 text-info rounded px-2 py-1"><i class="bi bi-gear-wide-connected me-1"></i>เครื่องจักร</span>
                                @else
                                    <span class="badge bg-success bg-opacity-10 text-success rounded px-2 py-1"><i class="bi bi-geo-alt-fill me-1"></i>พื้นที่</span>
                                @endif
                            </td>
                            <td data-label="แผนก">
                                <span class="fw-medium text-dark">{{ $activeSess->department->dept_name ?? 'รวมทั้งหมด' }}</span>
                            </td>
                            <td data-label="กะ / รอบ">
                                <div>
                                    <span class="fw-medium">{{ $activeSess->shift_label }}</span>
                                    <span class="badge bg-secondary ms-1">รอบ {{ $activeSess->round }}</span>
                                </div>
                            </td>
                            <td class="text-center" data-label="เวลาเริ่ม">
                                <span class="text-muted small"><i class="bi bi-clock me-1"></i>{{ $activeSess->created_at->format('H:i') }} น.</span>
                                @php
                                    $hoursDiff = $activeSess->created_at->diffInHours(now());
                                @endphp
                                @if($hoursDiff >= 2)
                                    <br><span class="badge bg-danger mt-1" style="font-size: 0.65rem;">นานเกินไป ({{ $hoursDiff }} ชม.)</span>
                                @endif
                            </td>
                            <td class="text-end pe-3" data-label="จัดการ">
                                <form action="{{ route('inspection.finish', $activeSess->id) }}" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการปิดรอบนี้? ระบบจะทำการสรุปยอดและเปลี่ยนสถานะเป็นเสร็จสิ้นทันที');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-sm" title="บังคับปิดรอบการตรวจนี้">
                                        <i class="bi bi-stop-circle me-1"></i> บังคับปิดรอบ
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Today's Scheduled Inspections -->
    @if(isset($scheduledTasks) && $scheduledTasks->isNotEmpty())
    <div class="card border-0 rounded-4 shadow-sm mb-4 bg-white position-relative overflow-hidden">
        <div class="position-absolute top-0 start-0 bottom-0 bg-info" style="width: 5px;"></div>
        <div class="card-header bg-white border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="bg-info bg-opacity-10 text-info rounded p-2 me-3">
                    <i class="bi bi-calendar-check-fill fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">ตารางตรวจวันนี้ (Today's Schedule)</h5>
                </div>
            </div>
            <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm fw-semibold">
                จัดการตาราง
            </a>
        </div>
        <div class="card-body p-4 pt-3">
            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0 text-nowrap">
                    <thead class="bg-light text-muted small text-uppercase rounded-3">
                        <tr>
                            <th class="ps-3 rounded-start">งาน</th>
                            <th>เป้าหมาย</th>
                            <th>แผนก</th>
                            <th>เวลา</th>
                            <th class="text-center rounded-end">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($scheduledTasks as $task)
                        <tr class="border-bottom border-light">
                            <td class="ps-3 py-3">
                                <span class="fw-bold text-dark">{{ $task['schedule']->title }}</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    @if($task['schedule']->targetable_type === 'App\Models\Machine')
                                        <span class="badge bg-info bg-opacity-10 text-info rounded px-2 py-1 me-2"><i class="bi bi-gear-wide-connected"></i></span>
                                    @else
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded px-2 py-1 me-2"><i class="bi bi-geo-alt-fill"></i></span>
                                    @endif
                                    <span class="fw-medium">{{ $task['schedule']->targetable->name ?? $task['schedule']->targetable->location_name ?? '-' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted">{{ $task['schedule']->department->dept_name ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border px-2 py-1"><i class="bi bi-clock me-1"></i>{{ $task['formatted_window'] }}</span>
                            </td>
                            <td class="text-center">
                                @if($task['status'] === 'completed')
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>เสร็จแล้ว</span>
                                @elseif($task['status'] === 'missed')
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2"><i class="bi bi-x-circle-fill me-1"></i>พลาด</span>
                                @else
                                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-2"><i class="bi bi-hourglass-split me-1"></i>รอดำเนินการ</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div class="row g-4 mb-4">
        <!-- Recent Activity -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="bi bi-list-columns-reverse me-2 text-primary"></i>กิจกรรมการตรวจล่าสุด</h5>
                    <a href="{{ route('inspection.verification') }}" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm fw-semibold">ดูทั้งหมด</a>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover table-borderless align-middle mb-0 text-nowrap">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-3 rounded-start">เป้าหมาย & เช็คพอยต์</th>
                                    <th>ผลลัพธ์</th>
                                    <th>ผู้ตรวจ</th>
                                    <th class="text-end pe-3 rounded-end">เวลา</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentLogs as $log)
                                    @php
                                        $targetName = $log->employee->fullname ?? ($log->machine->name ?? ($log->location->location_name ?? 'N/A'));
                                    @endphp
                                    <tr class="border-bottom" style="border-color: var(--hygiene-border-light);">
                                        <td class="ps-3 py-2">
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <span class="d-block fw-bold text-dark">
                                                        {{ $targetName }}
                                                        @if($log->session->is_sampling ?? false)
                                                            <span class="badge bg-warning text-dark border border-warning border-opacity-50 ms-1 fw-normal" style="font-size: 0.65rem;" title="เกิดจากการสุ่มตรวจ">
                                                                <i class="bi bi-shuffle"></i> สุ่ม
                                                            </span>
                                                        @endif
                                                    </span>
                                                    <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;">{{ $log->checkpoint->title }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($log->result === 'pass')
                                                <span class="fw-semibold text-success small"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: text-top;"></i> ผ่าน</span>
                                            @elseif($log->result === 'fail')
                                                @if($log->correctiveAction && in_array($log->correctiveAction->status, ['verified', 'closed']))
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill" title="ได้รับการแก้ไขและตรวจสอบแล้ว">
                                                        <i class="bi bi-check-circle-fill me-1"></i> แก้ไขแล้ว
                                                    </span>
                                                @else
                                                    <span class="fw-semibold text-danger small"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: text-top;"></i> ไม่ผ่าน</span>
                                                @endif
                                            @else
                                                <span class="fw-semibold text-secondary small"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: text-top;"></i> ไม่มา</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fw-medium text-dark small">{{ $log->session->inspector->name ?? '-' }}</span>
                                        </td>
                                        <td class="text-end pe-3 text-muted small fw-medium">
                                            @if($log->inspected_at->isToday())
                                                {{ $log->inspected_at->format('H:i') }} น.
                                            @else
                                                <div class="d-flex flex-column align-items-end">
                                                    <span>{{ $log->inspected_at->format('d/m/Y') }}</span>
                                                    <span style="font-size: 0.65rem;">{{ $log->inspected_at->format('H:i') }} น.</span>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="text-muted opacity-50 mb-3"><i class="bi bi-inbox fs-1"></i></div>
                                            <p class="text-muted fw-semibold mb-0">ยังไม่มีประวัติการตรวจในขณะนี้</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Progress (Sleeker Radial) -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center text-center">
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-1">Hygiene Index</h6>
                        <p class="text-muted small">คะแนนภาพรวมของเดือนนี้</p>
                    </div>
                    
                    <div class="position-relative d-inline-block mb-3">
                        <svg width="120" height="120" viewBox="0 0 120 120">
                            <!-- Background Circle -->
                            <circle cx="60" cy="60" r="54" fill="none" class="text-light" stroke="currentColor" stroke-width="8"></circle>
                            <!-- Progress Circle -->
                            <circle cx="60" cy="60" r="54" fill="none" class="text-success" stroke="currentColor" stroke-width="8" stroke-linecap="round" 
                                    stroke-dasharray="339.29" stroke-dashoffset="{{ 339.29 * (1 - ($monthlyPassRate/100)) }}" 
                                    style="transform: rotate(-90deg); transform-origin: 50% 50%;"></circle>
                        </svg>
                        <div class="position-absolute top-50 start-50 translate-middle">
                            <h3 class="fw-bold mb-0 text-dark">{{ $monthlyPassRate }}%</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CAR Section -->
    <div class="row g-4 mb-4">
        <!-- CAR Stats -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-bar-chart-fill me-2 text-danger"></i>สถิติใบสั่งแก้ไข (CAR)</h6>
                    <span class="badge bg-danger rounded-pill px-3 py-2 fw-semibold shadow-sm text-white">ข้อมูลเดือนนี้</span>
                </div>
                <div class="card-body p-4">
                    <div class="position-relative w-100 d-flex align-items-center justify-content-center" style="height: 220px;">
                        @if($totalCars > 0)
                            <canvas id="carDeptChart"></canvas>
                        @else
                            <div class="text-center py-4 text-muted w-100">
                                <i class="bi bi-graph-up text-secondary opacity-25 fs-1 mb-2 d-block animate-float"></i>
                                <span class="small fw-medium">ยังไม่มีข้อมูลสถิติใบสั่งแก้ไข (CAR) ในเดือนนี้</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- SLA Stats -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0 p-4 pb-0 text-center">
                    <h6 class="fw-bold mb-0 text-dark">สถานะติดตาม CAR</h6>
                </div>
                <div class="card-body p-4 d-flex flex-column justify-content-center">
                    <div class="text-center mb-3">
                        <h2 class="fw-black text-dark mb-0" style="font-size: 3rem;">{{ $totalCars }}</h2>
                        <span class="text-muted fw-semibold small">ใบสั่งทั้งหมด</span>
                    </div>
                    <div class="position-relative w-100 d-flex align-items-center justify-content-center" style="height: 180px;">
                        @if($totalCars > 0)
                            <canvas id="carSlaChart"></canvas>
                        @else
                            <div class="text-center py-3 text-muted">
                                <div class="icon-circle bg-success bg-opacity-10 text-success mx-auto mb-2" style="width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-shield-check fs-4"></i>
                                </div>
                                <span class="small fw-bold text-success d-block">ไม่มีใบสั่งแก้ไขค้างในระบบ</span>
                                <small class="text-muted" style="font-size: 0.75rem;">ทุกจุดผ่านเกณฑ์สุขอนามัยดีเยี่ยม 🟢</small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Insights Section -->
    @if(isset($aiTagCounts) && $aiTagCounts->isNotEmpty())
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden">
                <div class="card-header bg-primary bg-opacity-10 border-0 p-4 pb-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary text-white rounded p-2 me-3 shadow-sm">
                            <i class="bi bi-robot fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-primary">สถิติปัญหาที่พบบ่อย (AI Problem Insights)</h5>
                            <small class="text-muted">วิเคราะห์แนวโน้มปัญหาจาก AI ประจำเดือนนี้</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4 pt-4">
                    <div class="row align-items-center">
                        <div class="col-lg-7 mb-4 mb-lg-0">
                            <div class="position-relative w-100 d-flex align-items-center justify-content-center" style="height: 300px;">
                                <canvas id="aiInsightsChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-trophy text-warning me-2"></i>5 อันดับปัญหาที่พบบ่อยสุด</h6>
                            <div class="list-group list-group-flush border-0">
                                @foreach($aiTagCounts as $tag => $count)
                                <div class="list-group-item bg-transparent px-0 py-3 border-light d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill me-3" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                                            {{ $loop->iteration }}
                                        </span>
                                        <span class="fw-medium text-dark">{{ $tag }}</span>
                                    </div>
                                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm">
                                        <i class="bi bi-exclamation-triangle text-warning me-1"></i> {{ $count }} ครั้ง
                                    </span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Setup default font for charts
            Chart.defaults.font.family = "'Inter', 'Sarabun', sans-serif";
            Chart.defaults.color = '#64748b';

            // 1. CAR by Department (Bar Chart)
            const deptEl = document.getElementById('carDeptChart');
            if (deptEl && typeof Chart !== 'undefined') {
                const ctxDept = deptEl.getContext('2d');
                new Chart(ctxDept, {
                    type: 'bar',
                    data: {
                        labels: {!! json_encode($carsByDept->keys()) !!},
                        datasets: [{
                            label: 'จำนวนใบ CAR',
                            data: {!! json_encode($carsByDept->values()) !!},
                            backgroundColor: '#ef4444',
                            hoverBackgroundColor: '#dc2626',
                            borderRadius: 6,
                            barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { 
                                beginAtZero: true, 
                                ticks: { stepSize: 1, precision: 0 },
                                grid: { borderDash: [4, 4], color: '#e2e8f0', drawBorder: false }
                            },
                            x: { 
                                grid: { display: false, drawBorder: false } 
                            }
                        },
                        plugins: { 
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.9)',
                                padding: 12,
                                titleFont: { size: 14 },
                                bodyFont: { size: 14 },
                                cornerRadius: 8,
                            }
                        }
                    }
                });
            }

            // 2. SLA Status (Doughnut)
            const slaEl = document.getElementById('carSlaChart');
            if (slaEl && typeof Chart !== 'undefined') {
                const ctxSla = slaEl.getContext('2d');
                new Chart(ctxSla, {
                    type: 'doughnut',
                    data: {
                        labels: ['เสร็จทันเวลา', 'ล่าช้า', 'กำลังดำเนินการ'],
                        datasets: [{
                            data: [{{ $onTimeCount }}, {{ $overdueCount }}, {{ $inProgressCount }}],
                            backgroundColor: ['#22c55e', '#ef4444', '#f59e0b'],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%',
                        plugins: {
                            legend: { 
                                position: 'bottom', 
                                labels: { usePointStyle: true, padding: 20, font: { size: 13, weight: '500' } } 
                            },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.9)',
                                padding: 12,
                                cornerRadius: 8,
                            }
                        }
                    }
                });
            }

            // 3. AI Insights (Doughnut)
            @if(isset($aiTagCounts) && $aiTagCounts->isNotEmpty())
            const aiEl = document.getElementById('aiInsightsChart');
            if (aiEl && typeof Chart !== 'undefined') {
                const ctxAi = aiEl.getContext('2d');
                new Chart(ctxAi, {
                    type: 'doughnut',
                    data: {
                        labels: {!! json_encode($aiTagCounts->keys()) !!},
                        datasets: [{
                            data: {!! json_encode($aiTagCounts->values()) !!},
                            backgroundColor: [
                                '#3b82f6', // blue-500
                                '#8b5cf6', // violet-500
                                '#ec4899', // pink-500
                                '#f59e0b', // amber-500
                                '#10b981', // emerald-500
                                '#64748b'  // slate-500
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '65%',
                        plugins: {
                            legend: { 
                                position: 'right', 
                                labels: { 
                                    usePointStyle: true, 
                                    padding: 20, 
                                    font: { size: 14, weight: '500', family: "'Inter', 'Sarabun', sans-serif" } 
                                } 
                            },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.9)',
                                padding: 12,
                                cornerRadius: 8,
                                bodyFont: { size: 14 }
                            }
                        }
                    }
                });
            }
            @endif
        });
    </script>
    @endpush
</x-app-layout>
