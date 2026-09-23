{{--
    The body of one verification card's detail modal.

    Rendered on demand by InspectionController::verificationDetail(), not with
    the page: the page shows twenty cards, and rendering twenty of these inline
    meant building the markup of every log in twenty rounds - 5,620 of them on
    the UAT database - so that all but one could stay hidden.
--}}
                        <div class="d-flex flex-wrap flex-sm-nowrap align-items-center mb-3 p-3 bg-light rounded-4">
                            <div class="rounded-circle bg-white d-flex justify-content-center align-items-center me-3 shadow-sm overflow-hidden flex-shrink-0" style="width:60px; height:60px; border: 2px solid white;">
                                @if($group->image_path)
                                    <img src="{{ asset('storage/' . $group->image_path) }}" class="w-100 h-100 object-fit-cover">
                                @elseif($group->type === 'area')
                                    <i class="bi bi-layers fs-3 text-secondary"></i>
                                @elseif($group->type === 'machine')
                                    <i class="bi bi-gear-wide-connected fs-3 text-secondary"></i>
                                @else
                                    <i class="bi bi-person fs-3 text-secondary"></i>
                                @endif
                            </div>
                            <div class="flex-grow-1 w-100 mt-2 mt-sm-0">
                                <h5 class="fw-bold mb-1 text-break lh-sm">{{ $group->name }}</h5>
                                <div class="text-muted small lh-1">
                                    <div class="mb-1">{{ $group->subtext }}</div>
                                    <div>
                                        {{ $group->shift }} (รอบที่ {{ $group->round }})
                                        @if($group->is_sampling ?? false)
                                            <span class="badge bg-warning text-dark border border-warning border-opacity-50 ms-1" title="เซสชันนี้เกิดจากการสุ่มตรวจ">
                                                <i class="bi bi-shuffle me-1"></i>สุ่มตรวจ
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-start text-sm-end mt-2 mt-sm-0 w-100 w-sm-auto">
                                <div class="d-flex d-sm-block align-items-center justify-content-between">
                                    @php
                                        $allResolved = false;
                                        if ($group->status === 'fail' && $group->findings->count() > 0) {
                                            $allResolved = true;
                                            foreach($group->findings as $log) {
                                                $isResolved = $log->verification_status === 'approved' || (isset($log->correctiveAction) && in_array($log->correctiveAction->status, ['resolved', 'closed', 'verified']));
                                                if (!$isResolved) {
                                                    $allResolved = false;
                                                    break;
                                                }
                                            }
                                        }
                                    @endphp
                                    @if($group->status === 'no_production')
                                        <span class="badge bg-secondary rounded-pill px-3 py-2 mb-0 mb-sm-1">งดผลิต</span>
                                    @elseif($group->status === 'absent')
                                        <span class="badge bg-secondary rounded-pill px-3 py-2 mb-0 mb-sm-1">ขาดงาน</span>
                                    @else
                                        @if($group->status === 'pass')
                                            <span class="badge bg-success rounded-pill px-3 py-2 mb-0 mb-sm-1">ผ่าน</span>
                                        @elseif($allResolved)
                                            <span class="badge badge-soft-warning border border-warning text-dark rounded-pill px-3 py-2 mb-0 mb-sm-1">
                                                <i class="bi bi-check-circle-fill me-1 text-success"></i>แก้ไขแล้ว
                                            </span>
                                        @else
                                            <span class="badge bg-danger rounded-pill px-3 py-2 mb-0 mb-sm-1">ไม่ผ่าน</span>
                                        @endif
                                    @endif
                                    <div class="small text-muted ms-2 ms-sm-0">{{ $group->date }} {{ $group->time }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Employee Performance Summary (Only for person) -->
                        @if($group->type === 'person')
                        <div class="row g-2 mb-4">
                            <div class="col-md-4">
                                <div class="p-2 border rounded-3 bg-white text-center shadow-sm">
                                    <div class="small text-muted mb-1">สถานะวันนี้</div>
                                    @if($group->traffic_light === 'grey')
                                        {{-- Nothing was actually assessed: everyone was absent, or the
                                             line was not running. Showing "Excellent" here is what made
                                             a shift of no-shows look like a perfect round. --}}
                                        <span class="badge bg-secondary rounded-pill px-3 w-100">ไม่มีการตรวจ ⚪</span>
                                    @elseif($group->traffic_light === 'green')
                                        <span class="badge bg-success rounded-pill px-3 w-100">Excellent 🟢</span>
                                    @elseif($group->traffic_light === 'yellow')
                                        <span class="badge bg-warning text-dark rounded-pill px-3 w-100">Watch List 🟡</span>
                                    @else
                                        <span class="badge bg-danger rounded-pill px-3 w-100">Critical 🔴</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded-3 bg-white text-center shadow-sm">
                                    <div class="small text-muted mb-1">สะสมเดือนนี้</div>
                                    <div class="fw-bold text-danger">{{ $group->monthly_failures }} ครั้ง</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded-3 bg-white text-center shadow-sm">
                                    <div class="small text-muted mb-1">Hygiene Score</div>
                                    @if(is_null($group->hygiene_score))
                                        <div class="fw-bold text-muted">—</div>
                                        <div class="small text-muted">ไม่มีรายการที่ตรวจจริง</div>
                                    @else
                                    <div class="d-flex align-items-center justify-content-center">
                                        <div class="fw-bold me-2">{{ $group->hygiene_score }}%</div>
                                        <div class="progress flex-grow-1" style="height: 6px; min-width: 40px;">
                                            <div class="progress-bar {{ $group->hygiene_score >= 90 ? 'bg-success' : ($group->hygiene_score >= 80 ? 'bg-warning' : 'bg-danger') }}"
                                                 role="progressbar" style="width: {{ $group->hygiene_score }}%"></div>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        @if(!$group->is_action_required)
                            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
                                <div class="card-body text-center p-5">
                                    <i class="bi bi-slash-circle text-secondary mb-3" style="font-size: 3rem;"></i>
                                    <h5 class="fw-bold text-dark">
                                        {{ $group->status === 'no_production' ? 'งดการผลิต/ไม่ได้ใช้งาน ทั้งหมด' : 'พนักงานขาดงาน ทั้งหมด' }}
                                    </h5>
                                    <p class="text-muted mb-0">รายการนี้ไม่จำเป็นต้องมีการตรวจสอบหรือยืนยันผล</p>
                                </div>
                            </div>
                        @else

                        <h6 class="fw-bold mb-3"><i class="bi bi-list-check me-2 text-primary"></i>รายการที่ตรวจสอบ</h6>

                        @if($group->type === 'person')
                        {{-- Personnel: Accordion grouped by employee --}}
                        @php
                            $logsByEmployee = collect($group->all_logs)->groupBy('employee_id');
                        @endphp
                        <div class="accordion" id="accordion_{{ $group->modal_id }}">
                            @foreach($logsByEmployee as $empId => $empLogs)
                            @php
                                $emp = $empLogs->first()->employee;
                                $empName = $emp->fullname ?? $emp->name ?? 'Unknown';
                                $empFailed = $empLogs->where('result', 'fail')->count();
                                // An employee who did not come to work has zero failures, so the
                                // old `$empFailed === 0` test labelled them "ผ่าน (10 ข้อ)" —
                                // crediting them with passing checkpoints nobody assessed.
                                $empAbsent = $empLogs->isNotEmpty()
                                    && $empLogs->every(fn($l) => $l->result === 'absent');
                                $empPassed = !$empAbsent && $empFailed === 0;
                                $collapseId = 'collapse_' . $group->modal_id . '_' . $empId;
                            @endphp
                            <div class="accordion-item border-0 mb-2 rounded-3 shadow-sm overflow-hidden">
                                <h2 class="accordion-header">
                                    <button class="accordion-button {{ $empPassed ? '' : '' }} collapsed py-2 px-3" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" 
                                            aria-expanded="false" aria-controls="{{ $collapseId }}"
                                            style="font-size: 0.9rem; background-color: {{ $empAbsent ? '#f8f9fa' : ($empPassed ? '#f0fdf4' : '#fef2f2') }};">
                                        <div class="d-flex align-items-center justify-content-between w-100 me-2">
                                            <div class="d-flex align-items-center">
                                                @if($emp && $emp->profile_image)
                                                    <img src="{{ $emp->profile_image }}" alt="" class="rounded-circle me-2" style="width:28px;height:28px;object-fit:cover;">
                                                @else
                                                    <div class="rounded-circle bg-white d-flex align-items-center justify-content-center me-2" style="width:28px;height:28px;">
                                                        <i class="bi bi-person-fill text-muted small"></i>
                                                    </div>
                                                @endif
                                                <span class="fw-semibold">{{ $empName }}</span>
                                            </div>
                                            <div>
                                                @if($empAbsent)
                                                    <span class="badge bg-secondary rounded-pill px-2 py-1" style="font-size:0.7rem;">
                                                        <i class="bi bi-person-dash-fill me-1"></i>ไม่มาทำงาน
                                                    </span>
                                                @elseif($empPassed)
                                                    <span class="badge bg-success rounded-pill px-2 py-1" style="font-size:0.7rem;">
                                                        <i class="bi bi-check-circle-fill me-1"></i>ผ่าน ({{ $empLogs->count() }} ข้อ)
                                                    </span>
                                                @else
                                                    <span class="badge bg-danger rounded-pill px-2 py-1" style="font-size:0.7rem;">
                                                        <i class="bi bi-x-circle-fill me-1"></i>ไม่ผ่าน {{ $empFailed }} ข้อ
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="{{ $collapseId }}" class="accordion-collapse collapse" data-bs-parent="#accordion_{{ $group->modal_id }}">
                                    <div class="accordion-body p-0">
                                        <table class="table table-sm align-middle mb-0">
                                            <tbody>
                                                @foreach($empLogs as $log)
                                                <tr>
                                                    <td class="ps-3">{{ $log->checkpoint->title ?? 'N/A' }}</td>
                                                    <td class="text-center" style="width: 80px;">
                                                        @if($log->result === 'pass')
                                                            <span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                                                        @elseif($log->verification_status === 'approved' || (isset($log->correctiveAction) && in_array($log->correctiveAction->status, ['resolved', 'closed', 'verified'])))
                                                            <span class="text-success" title="แก้ไขแล้ว (Resolved)">
                                                                <i class="bi bi-check-circle-fill"></i>
                                                                <span class="d-block x-small">แก้ไขแล้ว</span>
                                                            </span>
                                                        @elseif($log->result === 'absent' || $log->result === 'no_production')
                                                            <span class="text-secondary" title="ไม่ได้ปฏิบัติงาน/งดผลิต">
                                                                <i class="bi bi-slash-circle-fill"></i>
                                                                <span class="d-block x-small">ไม่มีผลิต</span>
                                                            </span>
                                                        @else
                                                            <span class="text-danger"><i class="bi bi-x-circle-fill"></i></span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($log->result === 'fail')
                                                            <div class="small">
                                                                <div class="mb-2">
                                                                    <strong class="text-danger">สิ่งที่พบ (Before):</strong> {{ $log->correction_action ?? '-' }}
                                                                    @if($log->photo_path)
                                                                        <div class="mt-1 text-primary clickable" onclick="window.open('{{ asset('storage/' . $log->photo_path) }}', '_blank')">
                                                                            <i class="bi bi-image me-1"></i> ดูรูปภาพสิ่งที่พบ
                                                                        </div>
                                                                    @endif
                                                                </div>

                                                                @if(isset($log->correctiveAction) && !empty($log->correctiveAction->ai_tags))
                                                                    <div class="mb-2">
                                                                        @foreach($log->correctiveAction->ai_tags as $tag)
                                                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary rounded-pill shadow-sm me-1">
                                                                                <i class="bi bi-robot me-1"></i>{{ $tag }}
                                                                            </span>
                                                                        @endforeach
                                                                    </div>
                                                                @endif

                                                                @if(isset($log->correctiveAction))
                                                                    <div class="p-2 rounded bg-light border {{ in_array($log->correctiveAction->status, ['resolved', 'closed', 'verified']) ? 'border-success bg-opacity-10 bg-success' : 'border-warning' }}">
                                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                                            <span class="badge {{ in_array($log->correctiveAction->status, ['resolved', 'closed', 'verified']) ? 'bg-success' : 'bg-warning text-dark' }}">
                                                                                {{ ucfirst($log->correctiveAction->status) }}
                                                                            </span>
                                                                            @if($log->correctiveAction->resolved_at)
                                                                                <small class="text-muted">{{ \Carbon\Carbon::parse($log->correctiveAction->resolved_at)->format('d/m/Y H:i') }}</small>
                                                                            @endif
                                                                        </div>
                                                                        
                                                                        @if($log->correctiveAction->action_taken)
                                                                            <div class="mb-1">
                                                                                <strong class="text-success">การแก้ไขเบื้องต้น (After):</strong> {{ $log->correctiveAction->action_taken }}
                                                                            </div>
                                                                        @endif

                                                                        @if($log->correctiveAction->preventive_action)
                                                                            <div class="p-2 mt-1 rounded bg-warning bg-opacity-15 border border-warning text-dark">
                                                                                <strong class="text-warning-emphasis"><i class="bi bi-shield-check me-1"></i>มาตรการป้องกัน (Preventive Action):</strong> {{ $log->correctiveAction->preventive_action }}
                                                                            </div>
                                                                        @endif

                                                                        @if($log->correctiveAction->proof_image)
                                                                            <div class="mt-1 text-success clickable" onclick="window.open('{{ asset('storage/' . $log->correctiveAction->proof_image) }}', '_blank')">
                                                                                <i class="bi bi-card-image me-1"></i> ดูรูปภาพหลังแก้ไข
                                                                            </div>
                                                                        @endif
                                                                        
                                                                        @if(!$log->correctiveAction->action_taken && !$log->correctiveAction->proof_image)
                                                                            <span class="text-muted fst-italic">รอการแก้ไข...</span>
                                                                        @endif
                                                                        
                                                                        @if(in_array($log->correctiveAction->status, ['assigned', 'open']) && $log->correctiveAction->assignee)
                                                                            <div class="mt-2 pt-2 border-top border-warning border-opacity-25 small">
                                                                                <div class="text-primary"><i class="bi bi-person-check-fill me-1"></i> <strong>มอบหมายให้:</strong> {{ $log->correctiveAction->assignee->name }}</div>
                                                                                <div class="text-muted mt-1" style="font-size: 0.75rem;"><i class="bi bi-clock me-1"></i> มอบหมายเมื่อ {{ $log->correctiveAction->assigned_at ? $log->correctiveAction->assigned_at->format('d/m/Y H:i') : $log->correctiveAction->created_at->format('d/m/Y H:i') }}</div>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @elseif($log->result === 'absent' || $log->result === 'no_production')
                                                            <span class="text-muted small">N/A (ไม่ได้ปฏิบัติงาน/งดผลิต)</span>
                                                        @else
                                                            <span class="text-muted small">-</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        {{-- Area/Machine: Keep original flat table --}}
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>หัวข้อการตรวจ</th>
                                        <th class="text-center" style="width: 100px;">ผล</th>
                                        <th>หมายเหตุ/การแก้ไข</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $processedLogs = [];
                                        $logsByMachine = collect($group->all_logs)->groupBy('machine_id');
                                        
                                        foreach($logsByMachine as $machineId => $mLogs) {
                                            foreach($mLogs as $l) {
                                                $l->is_collapsed_machine = false;
                                                $processedLogs[] = $l;
                                            }
                                        }
                                        
                                        $areaLogs = collect($processedLogs)->filter(fn($l) => empty($l->machine_id) && (!isset($l->is_collapsed_machine) || !$l->is_collapsed_machine));
                                        $machineLogs = collect($processedLogs)->filter(fn($l) => !empty($l->machine_id) || (isset($l->is_collapsed_machine) && $l->is_collapsed_machine));

                                        $finalLogs = [];

                                        // Area Section
                                        if ($areaLogs->count() > 0) {
                                            $finalLogs[] = (object) ['is_section_header' => true, 'type' => 'info', 'title' => 'การตรวจสอบพื้นที่ (Area Inspection)', 'icon' => 'bi-geo-alt-fill'];
                                            
                                            // แยกรายการที่ fail จริงๆ (ยังไม่ถูกแก้ไข) vs fail แต่ถูกแก้ไขแล้ว (CAR resolved)
                                            $areaFailed = $areaLogs->filter(function($l) {
                                                if (!isset($l->result) || $l->result !== 'fail') return false;
                                                // ถ้ามี CAR resolved/closed/verified → ถือว่าแก้ไขแล้ว ไม่นับว่า fail
                                                if (isset($l->correctiveAction) && in_array($l->correctiveAction->status, ['resolved', 'closed', 'verified'])) return false;
                                                if ($l->verification_status === 'approved' || $l->verification_status === 'auto_verified') return false;
                                                return true;
                                            })->values();
                                            $areaPassed = $areaLogs->filter(function($l) {
                                                if ($l->result === 'no_production') return false; // N/A is handled separately
                                                if (!isset($l->result) || $l->result !== 'fail') return true; // pass
                                                // fail แต่แก้ไขแล้ว → ย้ายมาอยู่ฝั่ง passed
                                                if (isset($l->correctiveAction) && in_array($l->correctiveAction->status, ['resolved', 'closed', 'verified'])) return true;
                                                if ($l->verification_status === 'approved' || $l->verification_status === 'auto_verified') return true;
                                                return false;
                                            })->values();
                                            $areaNoProd = $areaLogs->filter(fn($l) => $l->result === 'no_production')->values();

                                            if ($areaFailed->count() > 0) {
                                                $finalLogs[] = (object) ['is_section_header' => true, 'type' => 'fail', 'title' => 'รายการที่ไม่ผ่าน (Failed Items)', 'indent' => true];
                                                $finalLogs = array_merge($finalLogs, $areaFailed->all());
                                            }
                                            if ($areaPassed->count() > 0) {
                                                $finalLogs[] = (object) ['is_section_header' => true, 'type' => 'pass', 'title' => 'รายการที่ผ่าน (Passed)', 'indent' => true];
                                                $finalLogs = array_merge($finalLogs, $areaPassed->all());
                                            }
                                            if ($areaNoProd->count() > 0) {
                                                $finalLogs[] = (object) ['is_section_header' => true, 'type' => 'na', 'title' => 'รายการที่ไม่มีการผลิต (N/A) - ไม่ต้องทวนสอบ', 'indent' => true];
                                                $finalLogs = array_merge($finalLogs, $areaNoProd->all());
                                            }
                                        }

                                        // Machine Section
                                        if ($machineLogs->count() > 0) {
                                            $finalLogs[] = (object) ['is_section_header' => true, 'type' => 'info', 'title' => 'การตรวจสอบเครื่องจักร/อุปกรณ์ (Machine Inspection)', 'icon' => 'bi-gear-fill'];
                                            
                                            // แยกรายการที่ fail จริงๆ vs fail แต่ถูกแก้ไขแล้ว (CAR resolved)
                                            $machineFailed = $machineLogs->filter(function($l) {
                                                if (!isset($l->result) || $l->result !== 'fail') return false;
                                                if (isset($l->correctiveAction) && in_array($l->correctiveAction->status, ['resolved', 'closed', 'verified'])) return false;
                                                if ($l->verification_status === 'approved' || $l->verification_status === 'auto_verified') return false;
                                                return true;
                                            })->values();
                                            $machinePassed = $machineLogs->filter(function($l) {
                                                if ($l->result === 'no_production') return false; // N/A is handled separately
                                                if (!isset($l->result) || $l->result !== 'fail') return true;
                                                if (isset($l->correctiveAction) && in_array($l->correctiveAction->status, ['resolved', 'closed', 'verified'])) return true;
                                                if ($l->verification_status === 'approved' || $l->verification_status === 'auto_verified') return true;
                                                return false;
                                            })->values();
                                            $machineNoProd = $machineLogs->filter(fn($l) => $l->result === 'no_production')->values();

                                            if ($machineFailed->count() > 0) {
                                                $finalLogs[] = (object) ['is_section_header' => true, 'type' => 'fail', 'title' => 'รายการที่ไม่ผ่าน (Failed Items)', 'indent' => true];
                                                $finalLogs = array_merge($finalLogs, $machineFailed->all());
                                            }
                                            if ($machinePassed->count() > 0) {
                                                $finalLogs[] = (object) ['is_section_header' => true, 'type' => 'pass', 'title' => 'รายการที่ผ่าน (Passed)', 'indent' => true];
                                                $finalLogs = array_merge($finalLogs, $machinePassed->all());
                                            }
                                            if ($machineNoProd->count() > 0) {
                                                $finalLogs[] = (object) ['is_section_header' => true, 'type' => 'na', 'title' => 'รายการที่ไม่มีการผลิต (N/A) - ไม่ต้องทวนสอบ', 'indent' => true];
                                                $finalLogs = array_merge($finalLogs, $machineNoProd->all());
                                            }
                                        }
                                    @endphp
                                    @foreach($finalLogs as $log)
                                    @if(isset($log->is_section_header) && $log->is_section_header)
                                        @if($log->type === 'info')
                                        <tr style="background: #f1f5f9;">
                                            <td colspan="3" class="fw-bold py-2 text-dark" style="font-size: 0.95rem; border-bottom: 2px solid #cbd5e1;">
                                                <i class="bi {{ $log->icon }} text-primary me-2"></i>{{ $log->title }}
                                            </td>
                                        </tr>
                                        @elseif($log->type === 'na')
                                        <tr style="background: #f8fafc;">
                                            <td colspan="3" class="fw-bold py-2 text-secondary {{ isset($log->indent) && $log->indent ? 'ps-4' : '' }}" style="font-size: 0.85rem;">
                                                <i class="bi bi-slash-circle me-2"></i>{{ $log->title }}
                                            </td>
                                        </tr>
                                        @else
                                        <tr style="background: {{ $log->type === 'fail' ? '#fff1f2' : '#ecfdf5' }};">
                                            <td colspan="3" class="fw-bold py-2 {{ $log->type === 'fail' ? 'text-danger' : 'text-success' }} {{ isset($log->indent) && $log->indent ? 'ps-4' : '' }}" style="font-size: 0.85rem;">
                                                <i class="bi {{ $log->type === 'fail' ? 'bi-x-circle-fill' : 'bi-check-circle-fill' }} me-2"></i>{{ $log->title }}
                                            </td>
                                        </tr>
                                        @endif
                                        @continue
                                    @endif
                                    <tr>
                                        @if(isset($log->is_collapsed_machine) && $log->is_collapsed_machine)
                                            <td>
                                                <span class="badge bg-secondary mb-1">[{{ $log->machine_name }}]</span><br>
                                                <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>งดการผลิต (รวม {{ $log->count }} รายการ)</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="text-secondary" title="ไม่มีการผลิต/ไม่ได้ใช้งาน">
                                                    <i class="bi bi-slash-circle-fill"></i>
                                                    <span class="d-block x-small">ไม่มีผลิต</span>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted small">N/A (ไม่มีการผลิต/ไม่ได้ใช้งาน)</span>
                                            </td>
                                        @else
                                        <td>
                                            @if($log->machine_id)
                                                <span class="badge bg-secondary mb-1">[{{ $log->machine->name ?? 'อุปกรณ์/เครื่องจักร' }}]</span><br>
                                            @endif
                                            {{ $log->checkpoint->title ?? 'N/A' }}
                                        </td>
                                        <td class="text-center">
                                            @if($log->result === 'pass')
                                                <span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                                            @elseif($log->verification_status === 'approved' || (isset($log->correctiveAction) && in_array($log->correctiveAction->status, ['resolved', 'closed', 'verified'])))
                                                <span class="text-success" title="แก้ไขแล้ว (Resolved)">
                                                    <i class="bi bi-check-circle-fill"></i>
                                                    <span class="d-block x-small">แก้ไขแล้ว</span>
                                                </span>
                                            @elseif($log->result === 'no_production' || $log->result === 'absent')
                                                <span class="text-secondary" title="ไม่มีการผลิต/ไม่ได้ใช้งาน">
                                                    <i class="bi bi-slash-circle-fill"></i>
                                                    <span class="d-block x-small">ไม่มีผลิต</span>
                                                </span>
                                            @else
                                                <span class="text-danger"><i class="bi bi-x-circle-fill"></i></span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($log->result === 'fail')
                                                <div class="small">
                                                    <div class="mb-2">
                                                        <strong class="text-danger">สิ่งที่พบ (Before):</strong> {{ $log->correction_action ?? '-' }}
                                                        @if($log->photo_path)
                                                            <div class="mt-1 text-primary clickable" onclick="window.open('{{ asset('storage/' . $log->photo_path) }}', '_blank')">
                                                                <i class="bi bi-image me-1"></i> ดูรูปภาพสิ่งที่พบ
                                                            </div>
                                                        @endif
                                                    </div>

                                                    @if(isset($log->correctiveAction) && !empty($log->correctiveAction->ai_tags))
                                                        <div class="mb-2">
                                                            @foreach($log->correctiveAction->ai_tags as $tag)
                                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary rounded-pill shadow-sm me-1">
                                                                    <i class="bi bi-robot me-1"></i>{{ $tag }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    @if(isset($log->correctiveAction))
                                                        <div class="p-2 rounded bg-light border {{ in_array($log->correctiveAction->status, ['resolved', 'closed', 'verified']) ? 'border-success bg-opacity-10 bg-success' : 'border-warning' }}">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <span class="badge {{ in_array($log->correctiveAction->status, ['resolved', 'closed', 'verified']) ? 'bg-success' : 'bg-warning text-dark' }}">
                                                                    {{ ucfirst($log->correctiveAction->status) }}
                                                                </span>
                                                                @if($log->correctiveAction->resolved_at)
                                                                    <small class="text-muted">{{ \Carbon\Carbon::parse($log->correctiveAction->resolved_at)->format('d/m/Y H:i') }}</small>
                                                                @endif
                                                            </div>
                                                            
                                                            @if($log->correctiveAction->action_taken)
                                                                <div class="mb-1">
                                                                    <strong class="text-success">การแก้ไขเบื้องต้น (After):</strong> {{ $log->correctiveAction->action_taken }}
                                                                </div>
                                                            @endif

                                                            @if($log->correctiveAction->preventive_action)
                                                                <div class="mb-1">
                                                                    <strong class="text-warning-emphasis">มาตรการป้องกัน (Preventive):</strong> {{ $log->correctiveAction->preventive_action }}
                                                                </div>
                                                            @endif

                                                            @if($log->correctiveAction->proof_image)
                                                                <div class="mt-1 text-success clickable" onclick="window.open('{{ asset('storage/' . $log->correctiveAction->proof_image) }}', '_blank')">
                                                                    <i class="bi bi-card-image me-1"></i> ดูรูปภาพหลังแก้ไข
                                                                </div>
                                                            @endif
                                                            
                                                            @if(!$log->correctiveAction->action_taken && !$log->correctiveAction->proof_image)
                                                                <span class="text-muted fst-italic">รอการแก้ไข...</span>
                                                            @endif
                                                            
                                                            @if(in_array($log->correctiveAction->status, ['assigned', 'open']) && $log->correctiveAction->assignee)
                                                                <div class="mt-2 pt-2 border-top border-warning border-opacity-25 small">
                                                                    <div class="text-primary"><i class="bi bi-person-check-fill me-1"></i> <strong>มอบหมายให้:</strong> {{ $log->correctiveAction->assignee->name }}</div>
                                                                    <div class="text-muted mt-1" style="font-size: 0.75rem;"><i class="bi bi-clock me-1"></i> มอบหมายเมื่อ {{ $log->correctiveAction->assigned_at ? $log->correctiveAction->assigned_at->format('d/m/Y H:i') : $log->correctiveAction->created_at->format('d/m/Y H:i') }}</div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @elseif($log->result === 'no_production' || $log->result === 'absent')
                                                <span class="text-muted small">N/A (ไม่มีการผลิต/ไม่ได้ใช้งาน)</span>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        @endif
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                        
                        @endif

                        <div class="mt-4 p-3 border rounded-4" style="background-color: #f8f9fa;">
                            <div class="d-flex justify-content-between align-items-center mb-0">
                                <div>
                                    <div class="small text-muted">ผู้ตรวจ (Inspector)</div>
                                    <div class="fw-bold text-dark"><i class="bi bi-person-badge me-1"></i> {{ $group->inspector_name }}</div>
                                </div>
                                @if($group->is_verified)
                                <div class="text-end">
                                    <div class="small text-muted">ทวนสอบแล้วโดย</div>
                                    <div class="fw-bold text-primary"><i class="bi bi-patch-check-fill me-1"></i> {{ $group->verifier_name }}</div>
                                </div>
                                @endif
                            </div>

                            @if($group->is_verified)
                            <div class="mt-3 pt-3 border-top">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-clock-history text-muted mt-1"></i>
                                    <div class="small">
                                        <div class="fw-bold mb-1">ประวัติการดำเนินการ (Verification Log):</div>
                                        <div>
                                            @if($group->verification_status === 'reclean')
                                                <span class="badge bg-warning text-dark me-1"><i class="bi bi-arrow-repeat me-1"></i> สั่งแก้ไข/ทำความสะอาดใหม่</span>
                                            @elseif($group->verification_status === 'auto_verified')
                                                <span class="badge bg-info text-white me-1"><i class="bi bi-robot me-1"></i> ผ่านการอนุมัติอัตโนมัติ</span>
                                            @else
                                                <span class="badge bg-success me-1"><i class="bi bi-check-all me-1"></i> ยืนยันความถูกต้องเรียบร้อย</span>
                                            @endif
                                            <span class="text-muted">เมื่อ {{ $group->verified_at }}</span>
                                        </div>
                                        @if($group->verification_comment)
                                            <div class="mt-2 text-dark bg-white p-2 rounded border border-warning border-opacity-25">
                                                <strong>บันทึกจากผู้ทวนสอบ:</strong> {{ $group->verification_comment }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
