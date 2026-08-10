<x-app-layout>
    @section('header', 'แบบฟอร์มตรวจพื้นที่ / เครื่องจักร')

    @push('styles')
    <style>
        /* Modern Segmented Control Styles */
        .segmented-control {
            display: flex;
            background-color: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            width: 160px;
            flex-shrink: 0;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .segmented-control input[type="radio"] {
            display: none;
        }
        .segmented-control label {
            flex: 1;
            text-align: center;
            padding: 6px 0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 700;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            color: #64748b;
            margin: 0;
        }
        .segmented-control input[type="radio"][value="pass"]:checked + label {
            background-color: #10b981;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(16,185,129,0.3), 0 2px 4px -1px rgba(16,185,129,0.2);
        }
        .segmented-control input[type="radio"][value="fail"]:checked + label {
            background-color: #ef4444;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(239,68,68,0.3), 0 2px 4px -1px rgba(239,68,68,0.2);
        }

        /* Compact Cards */
        .checklist-item {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f1f5f9;
        }
        .checklist-item:last-child {
            border-bottom: none;
        }
        .checklist-item:hover {
            background-color: #f8fafc;
        }

        /* Premium Filter Tabs */
        .filter-tab {
            position: relative;
            overflow: hidden;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .filter-tab::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 3px;
            background-color: #ffffff;
            border-radius: 3px 3px 0 0;
            transition: width 0.3s ease;
            opacity: 0.8;
        }
        .filter-tab.active::after {
            width: 40%;
        }
        .filter-tab:hover:not(.active) {
            transform: translateY(-1px);
            background-color: rgba(0,0,0,0.03);
        }

        /* Premium Target Card */
        .premium-card {
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid rgba(0,0,0,0.05) !important;
        }
        .premium-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px -10px rgba(0,0,0,0.1) !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
        }
        .premium-card .accordion-button {
            transition: background-color 0.3s ease;
        }
        .premium-card:hover .accordion-button {
            background-color: #f8fafc !important;
        }

        /* Floating Animation */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .animate-float {
            animation: float 3s ease-in-out infinite;
            display: inline-block;
        }

        /* Premium Header Gradient */
        .premium-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%) !important;
            color: white !important;
            position: relative;
            overflow: hidden;
        }
        .premium-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            transform: rotate(30deg);
            pointer-events: none;
        }
        .premium-header h6, .premium-header h2, .premium-header div {
            position: relative;
            z-index: 1;
        }
    </style>
    @endpush

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-9 px-0 px-md-3">
                @if(session('error'))
                <div class="alert alert-danger mx-2 mx-md-0 mb-4 rounded-4 shadow-sm border-0 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div>
                        <strong>ข้อผิดพลาด!</strong><br>
                        <span class="small">{{ session('error') }}</span>
                    </div>
                </div>
                @endif
                
                <!-- Header -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 premium-header mx-2 mx-md-0">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 text-uppercase mb-1">เซสชันการตรวจพื้นที่ (Bulk Inspection)</h6>
                                <h2 class="fw-bold mb-0">รวมรายการตรวจ {{ count($inspectionData) }} หัวข้อ</h2>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-white text-primary rounded-pill fs-6 px-3 py-2">
                                    <i class="bi bi-building me-1"></i> {{ $department->dept_name }}
                                </span>
                                <div class="mt-2 small text-white-50">
                                    <i class="bi bi-clock me-1"></i> กะ: {{ ucfirst($session->shift) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(!empty($recleanFixMode))
                <div class="alert alert-warning border-0 rounded-4 shadow-sm mb-4 mx-2 mx-md-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>โหมดแก้ไข Re-clean</strong> — บันทึกได้เฉพาะรายการที่ Supervisor สั่งแก้ไขเท่านั้น
                </div>
                @endif

                @php
                    $totalTargets = count($inspectionData);
                    $savedTargets = 0;
                    foreach($inspectionData as $d) {
                        if(count($d->existing_logs) > 0) $savedTargets++;
                    }
                    $pendingTargets = $totalTargets - $savedTargets;
                @endphp

                <!-- Sticky Filter Tabs -->
                <div class="sticky-top pt-2 pb-3 mb-4 z-3 shadow-sm rounded-bottom-4 mx-2 mx-md-0" style="top: 0px; z-index: 1020; background-color: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); border-bottom: 1px solid #e2e8f0;">
                    <div class="px-3 pb-2 pt-1">
                        <div class="nav nav-pills-modern nav-fill" role="tablist">
                            <button class="nav-link filter-tab d-flex justify-content-center align-items-center" data-filter="all" type="button">
                                <i class="bi bi-collection me-2 opacity-75"></i>
                                <span>ทั้งหมด</span>
                                <span class="badge bg-secondary rounded-pill ms-2">{{ $totalTargets }}</span>
                            </button>
                            <button class="nav-link filter-tab d-flex justify-content-center align-items-center" data-filter="pending" type="button">
                                <i class="bi bi-hourglass-split me-2 opacity-75"></i>
                                <span>รอตรวจ</span>
                                <span class="badge bg-danger rounded-pill ms-2" id="badge-pending">{{ $pendingTargets }}</span>
                            </button>
                            <button class="nav-link filter-tab d-flex justify-content-center align-items-center" data-filter="saved" type="button">
                                <i class="bi bi-check-circle-fill me-2 opacity-75"></i>
                                <span>บันทึกแล้ว</span>
                                <span class="badge bg-success rounded-pill ms-2" id="badge-saved">{{ $savedTargets }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="empty-filter-state" class="alert alert-light border border-2 border-dashed rounded-4 mb-4 mx-2 mx-md-0 text-center py-5 d-none">
                    <div class="animate-float mb-3">
                        <i class="bi bi-check2-circle text-success opacity-50" style="font-size: 4rem;"></i>
                    </div>
                    <h5 class="fw-bold text-secondary">ไม่มีรายการในหมวดหมู่นี้</h5>
                    <p class="text-muted small mb-0">คุณทำรายการในหมวดหมู่นี้ครบแล้ว หรือไม่มีข้อมูลให้แสดง</p>
                </div>

                @php
                    $groupedData = collect($inspectionData)->groupBy(function($item) {
                        return $item->location ? $item->location->id : 0;
                    });
                @endphp

                @foreach($groupedData as $locationId => $group)
                    @php
                        $locName    = $group->first()->location ? $group->first()->location->location_name : 'อื่นๆ (Others)';
                        $groupTotal = $group->count();
                        $groupSaved = $group->filter(fn($d) => count($d->existing_logs) > 0)->count();

                        // สถิติแยกประเภท
                        $groupFailed  = $group->filter(fn($d) => $d->existing_logs->contains('result', 'fail'))->count();
                        $groupNoProd  = $group->filter(fn($d) =>
                            $d->existing_logs->contains('result', 'no_production')
                            && !$d->existing_logs->contains('result', 'fail')
                        )->count();
                        $groupPassed  = $groupSaved - $groupFailed - $groupNoProd;

                        // เรียงลำดับ: ไม่ผ่าน → รอตรวจ → N/A → ผ่าน
                        $sortedGroup = $group->sortBy(function($data) {
                            if ($data->existing_logs->contains('result', 'fail'))          return 0;
                            if (count($data->existing_logs) === 0)                         return 1;
                            if ($data->existing_logs->contains('result', 'no_production')) return 2;
                            return 3;
                        })->values();
                    @endphp
                    <div class="location-group mb-4 mx-2 mx-md-0">
                        <div class="location-header d-flex justify-content-between align-items-center bg-white p-3 rounded-4 shadow-sm mb-3 border-start border-4 {{ $groupFailed > 0 ? 'border-danger' : 'border-primary' }}">
                            <div>
                                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-geo-alt-fill {{ $groupFailed > 0 ? 'text-danger' : 'text-primary' }} me-2"></i>{{ $locName }}</h5>
                                <div class="d-flex flex-wrap gap-2 align-items-center mt-1">
                                    <span class="text-muted small">ความคืบหน้า: {{ $groupSaved }}/{{ $groupTotal }}</span>
                                    @if($groupFailed > 0)
                                        <span class="badge bg-danger rounded-pill" style="font-size:0.7rem;"><i class="bi bi-x-circle-fill me-1"></i>ไม่ผ่าน {{ $groupFailed }}</span>
                                    @endif
                                    @if($groupPassed > 0)
                                        <span class="badge bg-success rounded-pill" style="font-size:0.7rem;"><i class="bi bi-check-circle-fill me-1"></i>ผ่าน {{ $groupPassed }}</span>
                                    @endif
                                    @if($groupNoProd > 0)
                                        <span class="badge bg-warning text-dark rounded-pill" style="font-size:0.7rem;"><i class="bi bi-slash-circle me-1"></i>N/A {{ $groupNoProd }}</span>
                                    @endif
                                </div>
                                {{-- Adaptive target strip (revised Fix #6):
                                     - Empty / all-pass / all-no-prod: hide entirely — aggregated counts already say it all.
                                     - Any fail present: render only the fail chips (name-bearing, clickable) so the
                                       inspector can jump straight to the problem.
                                     - Partial progress (no fail): render a compact icon-only dot strip so the
                                       inspector sees which specific targets are done vs. waiting, without a wall
                                       of grey pills eating vertical space. --}}
                                @php
                                    $hideStrip = $groupSaved === 0
                                        || ($groupSaved === $groupTotal && $groupFailed === 0);
                                    $stripMode = null;
                                    if (!$hideStrip) {
                                        $stripMode = $groupFailed > 0 ? 'fail' : 'progress';
                                    }
                                @endphp
                                @if($stripMode === 'fail')
                                <div class="target-fail-strip d-flex flex-wrap gap-1 align-items-center mt-2"
                                     role="group" aria-label="รายการที่ไม่ผ่าน">
                                    @foreach($sortedGroup->filter(fn($t) => $t->existing_logs->contains('result', 'fail')) as $tgt)
                                        @php
                                            $accordionId = 'collapse_' . str_replace(':', '_', "{$tgt->target_type}:{$tgt->target_id}");
                                        @endphp
                                        <a href="#{{ $accordionId }}"
                                           class="badge bg-danger text-white rounded-pill text-decoration-none d-inline-flex align-items-center gap-1"
                                           style="font-size:0.7rem; padding:0.3rem 0.65rem;"
                                           data-bs-toggle="collapse"
                                           data-bs-target="#{{ $accordionId }}"
                                           aria-expanded="false"
                                           aria-controls="{{ $accordionId }}"
                                           title="ไม่ผ่าน: {{ $tgt->name }} — คลิกเพื่อขยายรายละเอียด">
                                            <i class="bi bi-x-lg"></i>
                                            <span>{{ $tgt->name }}</span>
                                        </a>
                                    @endforeach
                                </div>
                                @elseif($stripMode === 'progress')
                                <div class="target-dot-strip d-flex flex-wrap gap-1 align-items-center mt-2"
                                     role="group" aria-label="สถานะรายเป้าหมาย">
                                    @foreach($sortedGroup as $tgt)
                                        @php
                                            $tgtNoProd  = $tgt->existing_logs->contains('result', 'no_production');
                                            $tgtWaiting = count($tgt->existing_logs) === 0;
                                            $tgtPass    = !$tgtNoProd && !$tgtWaiting;

                                            [$dotClass, $dotIcon, $dotTitle] = match(true) {
                                                $tgtWaiting => ['text-secondary opacity-50', 'bi-circle',       "รอตรวจ: {$tgt->name}"],
                                                $tgtNoProd  => ['text-warning',              'bi-slash-circle', "ไม่มีผลิต: {$tgt->name}"],
                                                default     => ['text-success',              'bi-check-circle-fill', "ผ่าน: {$tgt->name}"],
                                            };
                                            $dotAccordionId = 'collapse_' . str_replace(':', '_', "{$tgt->target_type}:{$tgt->target_id}");
                                        @endphp
                                        <a href="#{{ $dotAccordionId }}"
                                           class="target-dot {{ $dotClass }} text-decoration-none"
                                           style="font-size:0.85rem; line-height:1;"
                                           data-bs-toggle="tooltip"
                                           title="{{ $dotTitle }}">
                                            <i class="bi {{ $dotIcon }}"></i>
                                        </a>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            @php
                                $groupTargets = $group->map(function($data) { return "{$data->target_type}:{$data->target_id}"; })->implode(',');
                                $machineTargets = $group->where('target_type', 'machine')->map(function($data) { return "{$data->target_type}:{$data->target_id}"; })->implode(',');
                                $locTargets = $group->where('target_type', 'loc')->map(function($data) { return "{$data->target_type}:{$data->target_id}"; })->implode(',');
                            @endphp
                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold toggle-loc-container" data-bs-toggle="collapse" data-bs-target="#loc_container_{{ $locationId }}" aria-expanded="false" aria-controls="loc_container_{{ $locationId }}">
                                    <span class="toggle-text"><i class="bi bi-chevron-bar-expand me-1"></i> ขยายกลุ่มนี้</span>
                                </button>
                                @if(empty($recleanFixMode))
                                    @if($machineTargets && $locTargets)
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-success fw-bold rounded-pill px-3 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-check-all me-1"></i> เลือกลงผลผ่าน
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                                            <li>
                                                <a class="dropdown-item text-success fw-bold py-2" href="#" onclick="event.preventDefault(); event.stopPropagation(); markRoomPass({{ $locationId }}, '{{ addslashes($locName) }}', '{{ $groupTargets }}')">
                                                    <i class="bi bi-check-all me-2"></i> บันทึก "ผ่าน" รายการที่เหลือ (พื้นที่+เครื่องจักร)
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2" href="#" onclick="event.preventDefault(); event.stopPropagation(); markRoomPass({{ $locationId }}, 'เฉพาะเครื่องจักรใน {{ addslashes($locName) }}', '{{ $machineTargets }}')">
                                                    <i class="bi bi-gear-wide-connected me-2 text-secondary"></i> บันทึก "ผ่าน" เครื่องจักรที่เหลือ
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2" href="#" onclick="event.preventDefault(); event.stopPropagation(); markRoomPass({{ $locationId }}, 'เฉพาะพื้นที่ใน {{ addslashes($locName) }}', '{{ $locTargets }}')">
                                                    <i class="bi bi-layers me-2 text-secondary"></i> บันทึก "ผ่าน" พื้นที่ (ที่ยังไม่ตรวจ)
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    @elseif($machineTargets)
                                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold" onclick="markRoomPass({{ $locationId }}, 'เฉพาะเครื่องจักรใน {{ addslashes($locName) }}', '{{ $groupTargets }}')">
                                        <i class="bi bi-check-all me-1"></i> บันทึก "ผ่าน" เครื่องจักรที่เหลือ
                                    </button>
                                    @elseif($locTargets)
                                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold" onclick="markRoomPass({{ $locationId }}, 'เฉพาะพื้นที่ใน {{ addslashes($locName) }}', '{{ $groupTargets }}')">
                                        <i class="bi bi-check-all me-1"></i> บันทึก "ผ่าน" พื้นที่ (ที่ยังไม่ตรวจ)
                                    </button>
                                    @endif

                                    @php
                                        // Fix #5 UI: "no production, remaining machines only" fits a real case —
                                        // the room is producing but some machines are idle. Only expose it when
                                        // there are machine targets AND at least one is still un-inspected, so
                                        // the button isn't confusing when the group is all-locations or fully done.
                                        $remainingMachineCount = $group
                                            ->where('target_type', 'machine')
                                            ->filter(fn($d) => count($d->existing_logs) === 0)
                                            ->count();
                                    @endphp
                                    @if($machineTargets && $remainingMachineCount > 0)
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-warning fw-bold rounded-pill px-3 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-slash-circle me-1"></i> ไม่มีผลิต
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                                            <li>
                                                <a class="dropdown-item text-warning fw-bold py-2" href="#" onclick="event.preventDefault(); event.stopPropagation(); markRoomNoProduction({{ $locationId }}, '{{ addslashes($locName) }}', '{{ $groupTargets }}')">
                                                    <i class="bi bi-slash-circle me-2"></i> ไม่มีผลิต ทั้งห้อง (เขียนทับข้อมูลเดิม)
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2" href="#" onclick="event.preventDefault(); event.stopPropagation(); markRemainingMachinesNoProduction({{ $locationId }}, '{{ addslashes($locName) }}', '{{ $machineTargets }}', {{ $remainingMachineCount }})">
                                                    <i class="bi bi-gear-wide-connected me-2 text-secondary"></i> ไม่มีผลิต เฉพาะเครื่องที่เหลือ ({{ $remainingMachineCount }} เครื่อง)
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    @else
                                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-bold" onclick="markRoomNoProduction({{ $locationId }}, '{{ $locName }}', '{{ $groupTargets }}')">
                                        <i class="bi bi-slash-circle me-1"></i> ไม่มีผลิต
                                    </button>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <div class="collapse" id="loc_container_{{ $locationId }}">
                        @php
                            // Pin the room's own area target to the top of the group so the
                            // "การตรวจพื้นที่ทำงาน" card is always visible above the machine list,
                            // regardless of the fail/waiting/no-prod sort key. Machines keep the
                            // original ordering underneath.
                            $areaFirstSortedGroup = $sortedGroup->sortBy(fn($d) => $d->target_type === 'loc' ? 0 : 1)->values();
                            $groupHasAreaCard = $areaFirstSortedGroup->contains(fn($d) => $d->target_type === 'loc');
                            $renderedFirstMachineHeader = false;
                        @endphp
                        @foreach($areaFirstSortedGroup as $data)
                        @php
                            $targetKey = "{$data->target_type}:{$data->target_id}";
                            $targetUniqueId = str_replace(':', '_', $targetKey);
                            $totalCp = 0;
                            foreach($data->checkpoints as $cItems) {
                                $totalCp += count($cItems);
                            }
                            $hasSaved = count($data->existing_logs) > 0;
                        @endphp

                        @if($groupHasAreaCard && $data->target_type === 'machine' && !$renderedFirstMachineHeader)
                            <div class="d-flex align-items-center gap-2 mt-4 mb-2 mx-2 mx-md-0">
                                <div class="flex-shrink-0 text-primary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                                    <i class="bi bi-signpost-split me-1"></i>ส่วนที่ 2: การตรวจเครื่องจักร ({{ $areaFirstSortedGroup->where('target_type', 'machine')->count() }})
                                </div>
                                <hr class="flex-grow-1 my-0" style="border-color: rgba(13,110,253,0.2);">
                            </div>
                            @php $renderedFirstMachineHeader = true; @endphp
                        @endif

                        
                        @php
                            // Distinct styling for the area card so it can't be mistaken for a machine.
                            $isAreaCard = $data->target_type === 'loc';
                            $cardWrapClass = $isAreaCard
                                ? 'accordion mb-3 mx-2 mx-md-0 premium-card rounded-4 area-target-card border-start border-4 border-success'
                                : 'accordion mb-3 mx-2 mx-md-0 premium-card rounded-4 bg-white';
                            $iconWrapClass = $isAreaCard
                                ? 'bg-success bg-opacity-10 text-success rounded-circle p-2 me-3 d-flex align-items-center justify-content-center flex-shrink-0'
                                : 'bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3 d-flex align-items-center justify-content-center flex-shrink-0';
                            $iconWrapStyle = $isAreaCard ? 'width: 56px; height: 56px;' : 'width: 48px; height: 48px;';
                            $iconName = $isAreaCard ? 'bi-building-check' : 'bi-gear-wide-connected';
                            $iconSize = $isAreaCard ? 'fs-3' : 'fs-4';
                            $cardTitle = $isAreaCard ? 'การตรวจพื้นที่ทำงาน' : $data->name;
                            $cardSubtext = $isAreaCard
                                ? 'พื้น/ผนัง/เพดาน/สุขอนามัยของ ' . ($data->location->location_name ?? '')
                                : $data->subtext;
                        @endphp
                        <form class="target-form" data-status="{{ $hasSaved ? 'saved' : 'pending' }}" action="{{ route('inspection.area.store', ['session' => $session->id, 'location' => $data->location->id ?? 0]) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="{{ $cardWrapClass }} bg-white" id="accordion_{{ $targetUniqueId }}">
                            <div class="accordion-item border-0 rounded-4 overflow-hidden" data-saved="{{ $hasSaved ? '1' : '0' }}" data-has-fail="{{ ($hasSaved && $data->existing_logs->contains('result', 'fail')) ? '1' : '0' }}">
                                <h2 class="accordion-header" id="heading_{{ $targetUniqueId }}">
                                    @if($isAreaCard)
                                        <div class="px-3 pt-2 pb-0 small fw-bold text-success text-uppercase" style="letter-spacing: 0.5px;">
                                            <i class="bi bi-signpost-split me-1"></i>ส่วนที่ 1: การตรวจพื้นที่
                                        </div>
                                    @endif
                                    <button class="accordion-button collapsed bg-white p-3 p-md-4" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_{{ $targetUniqueId }}" aria-expanded="false" aria-controls="collapse_{{ $targetUniqueId }}">
                                        <div class="d-flex align-items-center w-100 pe-2">
                                            <div class="{{ $iconWrapClass }}" style="{{ $iconWrapStyle }}">
                                                <i class="bi {{ $iconName }} {{ $iconSize }}"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <h5 class="fw-bold mb-0 text-dark text-truncate">{{ $cardTitle }}</h5>
                                                <span class="text-muted small text-truncate d-block"><i class="bi bi-geo-alt me-1"></i>{{ $cardSubtext }}</span>
                                            </div>
                                            <div class="text-end ms-auto mt-0 ps-2">
                                                @if($hasSaved)
                                                    @php
                                                        $badgeText = 'บันทึกแล้ว';
                                                        $badgeColor = 'bg-success';
                                                        if ($data->existing_logs->contains('result', 'no_production')) {
                                                            $badgeText = $data->target_type === 'machine' ? 'บันทึกแล้ว (ไม่มีผลิต)' : 'บันทึกแล้ว (ไม่ได้ใช้งาน)';
                                                            $badgeColor = 'bg-warning text-dark';
                                                        } elseif ($data->existing_logs->contains('result', 'fail')) {
                                                            $badgeText = 'บันทึกแล้ว (มีจุดไม่ผ่าน)';
                                                            $badgeColor = 'bg-danger';
                                                        }
                                                    @endphp
                                                    <span class="badge {{ $badgeColor }} rounded-pill fw-normal px-2 px-md-3 py-1 py-md-2 x-small"><i class="bi bi-{{ $badgeColor === 'bg-danger' ? 'x-circle' : 'check2-circle' }} me-1"></i> {{ $badgeText }}</span>
                                                @else
                                                    <span class="badge bg-secondary text-white rounded-pill fw-normal px-2 px-md-3 py-1 py-md-2 x-small"><i class="bi bi-clock me-1 d-none d-md-inline"></i> รอตรวจ</span>
                                                @endif
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse_{{ $targetUniqueId }}" class="accordion-collapse collapse" aria-labelledby="heading_{{ $targetUniqueId }}" data-bs-parent="#accordion_{{ $targetUniqueId }}">
                                    <div class="accordion-body bg-light bg-opacity-50 p-2 p-md-4">
                                        @php
                                            $isNoProd = $hasSaved && $data->existing_logs->contains('result', 'no_production');
                                        @endphp
                                        <div class="form-check form-switch mb-3 bg-white p-3 rounded-3 shadow-sm border-start border-4 border-warning mx-2 mx-md-0">
                                            <input class="form-check-input ms-0 me-2 no-production-toggle" type="checkbox" role="switch" id="no_prod_{{ $targetUniqueId }}" data-target="{{ $targetUniqueId }}" {{ $isNoProd ? 'checked' : '' }}>
                                            <label class="form-check-label text-warning-emphasis fw-bold" for="no_prod_{{ $targetUniqueId }}">
                                                {{ $data->target_type === 'machine' ? 'ระบุว่าไม่มีการผลิต (No Production)' : 'ระบุว่าไม่มีการใช้งาน (Not in Use)' }}
                                            </label>
                                        </div>
                                        <div id="checkpoints_{{ $targetUniqueId }}" class="checkpoints-container {{ $isNoProd ? 'd-none' : '' }}">
                                        @forelse($data->checkpoints as $categoryName => $items)
                                            <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden border-start border-4 border-primary">
                                                <div class="card-header bg-white py-2 px-3 border-bottom">
                                                    <h6 class="fw-bold text-dark m-0 small">{{ $categoryName ?: 'ทั่วไป' }}</h6>
                                                </div>
                                                <div class="card-body p-0 bg-white">
                                                    @foreach($items as $checkpoint)
                                                        @php
                                                            $existing = $data->existing_logs[$checkpoint->id] ?? null;
                                                            $reclean = $data->reclean_requests[$checkpoint->id] ?? null;
                                                            
                                                            $passChecked = $existing ? $existing->result === 'pass' : (!$reclean); 
                                                            $failChecked = $existing ? $existing->result === 'fail' : ($reclean ? true : false);
                                                            
                                                            $inputId = "input_{$targetUniqueId}_{$checkpoint->id}";
                                                        @endphp
                                                        <div class="p-3 checklist-item">
                                                            @if($reclean)
                                                            <div class="mb-2 p-2 rounded-3 bg-warning bg-opacity-10 border-start border-3 border-warning shadow-sm">
                                                                <div class="fw-bold text-dark x-small mb-1"><i class="bi bi-arrow-repeat me-1"></i> ผู้อนุมัติสั่งแก้ไขใหม่:</div>
                                                                <div class="text-danger x-small fw-medium">{{ $reclean->verification_comment ?? 'กรุณาแก้ไขและถ่ายรูปใหม่' }}</div>
                                                            </div>
                                                            @endif

                                                            <div class="d-flex justify-content-between align-items-center gap-2">
                                                                <div class="pe-1 flex-grow-1 min-w-0">
                                                                    <h6 class="fw-bold mb-1 text-dark" style="font-size: 0.9rem;">{{ $checkpoint->title }}</h6>
                                                                    <p class="text-muted x-small mb-0 text-truncate" style="max-width: 180px;">{{ $checkpoint->description }}</p>
                                                                </div>

                                                                <!-- Modern Segmented Control for Pass/Fail -->
                                                                <div class="segmented-control">
                                                                    <input type="radio" 
                                                                        name="results[targets][{{ $targetKey }}][{{ $checkpoint->id }}]" 
                                                                        id="pass_{{ $inputId }}" 
                                                                        value="pass" 
                                                                        {{ $passChecked ? 'checked' : '' }}
                                                                        onchange="toggleTargetDetails('{{ $targetUniqueId }}', {{ $checkpoint->id }}, 'pass')">
                                                                    <label for="pass_{{ $inputId }}"><i class="bi bi-check-lg"></i> ผ่าน</label>

                                                                    <input type="radio" 
                                                                        name="results[targets][{{ $targetKey }}][{{ $checkpoint->id }}]" 
                                                                        id="fail_{{ $inputId }}" 
                                                                        value="fail" 
                                                                        {{ $failChecked ? 'checked' : '' }}
                                                                        onchange="toggleTargetDetails('{{ $targetUniqueId }}', {{ $checkpoint->id }}, 'fail')">
                                                                    <label for="fail_{{ $inputId }}"><i class="bi bi-x-lg"></i> ไม่ผ่าน</label>
                                                                </div>
                                                            </div>

                                                            <!-- Dynamic Notes/Photos Area (Fades in if fail) -->
                                                            <div id="details_{{ $targetUniqueId }}_{{ $checkpoint->id }}" class="mt-3 p-3 bg-light rounded-3 border-start border-danger border-4 {{ $failChecked ? '' : 'd-none' }}" style="transition: all 0.3s;">
                                                                <div class="mb-2">
                                                                    <label class="form-label x-small fw-bold text-danger">
                                                                        {{ $failChecked ? '📝 สาเหตุที่ไม่ผ่าน / การแก้ไข (Correction)*' : '📝 หมายเหตุ (Optional)' }}
                                                                    </label>
                                                                    <textarea name="notes[{{ $targetKey }}][{{ $checkpoint->id }}]" class="form-control form-control-sm border-danger border-opacity-50" rows="2" placeholder="ระบุรายละเอียด...">{{ $existing ? $existing->correction_action : '' }}</textarea>
                                                                </div>
                                                                <div>
                                                                    <label class="form-label x-small fw-bold text-danger">
                                                                        📸 แนบรูปภาพ <span class="text-danger {{ $failChecked ? '' : 'd-none' }}">*</span>
                                                                    </label>
                                                                    <input type="file" name="photos[{{ $targetKey }}][{{ $checkpoint->id }}]" class="form-control form-control-sm border-danger border-opacity-50" accept="image/*">
                                                                    @if($existing && $existing->photo_path)
                                                                        <div class="mt-2 x-small text-success fw-bold">
                                                                            <i class="bi bi-check-circle-fill me-1"></i> มีรูปภาพเดิมแล้ว
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @empty
                                            <div class="alert alert-light border text-center py-4 rounded-4">
                                                <i class="bi bi-info-circle mb-2 d-block"></i>
                                                ยังไม่มีจุดตรวจสำหรับรายการนี้
                                            </div>
                                        @endforelse
                                        </div>
                                        
                                        <div class="mt-4 pb-2 text-center text-md-end">
                                            <button type="submit" class="btn btn-primary px-5 py-3 rounded-pill shadow fw-bold w-100" style="max-width: 300px; background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); border: none;">
                                                <i class="bi bi-save2 me-2"></i> บันทึกข้อมูลเครื่องนี้
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </form>
                    @endforeach
                    </div>
                    </div>
                @endforeach

                    @if(empty($recleanFixMode))
                    <form action="{{ route('inspection.area.store', ['session' => $session->id, 'location' => $inspectionData[0]->location->id ?? 0]) }}" method="POST">
                        @csrf
                        <div class="card border-0 shadow-lg rounded-4 mb-5 bg-white overflow-hidden mx-2 mx-md-0">
                            <div class="card-body p-4 text-center">
                                <p class="text-muted mb-4 small">คลิก "จบงาน" หลังจากตรวจสอบและบันทึกข้อมูลทุกเครื่องจักรครบถ้วนแล้ว</p>
                                <div class="d-grid gap-3">
                                    <div class="d-flex flex-column flex-md-row gap-2">
                                         <button type="submit" formaction="{{ route('inspection.pause', $session->id) }}" class="btn btn-warning flex-fill py-3 rounded-pill fw-bold border text-dark">
                                            <i class="bi bi-pause-circle me-2"></i> พักการตรวจ (Pause)
                                        </button>
                                        <button type="submit" name="save_action" value="finish" class="btn btn-success flex-fill py-3 rounded-pill fw-bold mt-2 mt-md-0 shadow-sm">
                                             <i class="bi bi-check2-circle me-2"></i> จบงาน (Finish Session)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endif
            </div>
        </div>
    </div>

    <script>
        function toggleTargetDetails(targetId, cpId, status) {
            const container = document.getElementById(`details_${targetId}_${cpId}`);
            if (!container) return;
            
            const label = container.querySelector('label');
            const photoReq = container.querySelector('.text-danger');
            
            // Find inputs within this specific container
            const noteInput = container.querySelector('textarea');
            const photoInput = container.querySelector('input[type="file"]');

            if (status === 'fail') {
                container.classList.remove('d-none');
                // Force a reflow for transition if we add fade in future
                void container.offsetWidth; 
                
                if (label) label.innerText = '📝 สาเหตุที่ไม่ผ่าน / การแก้ไข (Correction)*';
                if (photoReq) photoReq.classList.remove('d-none');
                
                // Add Required
                if (noteInput) noteInput.setAttribute('required', 'required');
                if (photoInput) photoInput.setAttribute('required', 'required');
            } else {
                container.classList.add('d-none'); // Hide when pass is selected
                if (label) label.innerText = '📝 หมายเหตุ (Optional)';
                if (photoReq) photoReq.classList.add('d-none');
                
                // Remove Required
                if (noteInput) noteInput.removeAttribute('required');
                if (photoInput) photoInput.removeAttribute('required');
            }
        }

        (function() {
            // No Production / Not in Use Toggle Logic
            document.querySelectorAll('.no-production-toggle').forEach(toggle => {
                const handleToggle = function(el) {
                    const targetId = el.dataset.target;
                    const container = document.getElementById(`checkpoints_${targetId}`);
                    if (!container) return;
                    
                    const radios = container.querySelectorAll('input[type="radio"]');
                    const detailAreas = container.querySelectorAll('[id^="details_"]');

                    if (el.checked) {
                        container.classList.add('d-none');
                        
                        const processedNames = new Set();
                        radios.forEach(radio => {
                            radio.checked = false;
                            radio.removeAttribute('required');
                            if (!processedNames.has(radio.name)) {
                                processedNames.add(radio.name);
                                const hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = radio.name;
                                hidden.value = 'no_production';
                                hidden.className = `hidden-no-prod-${targetId}`;
                                el.parentElement.appendChild(hidden);
                            }
                        });
                        
                        detailAreas.forEach(da => {
                            da.classList.add('d-none');
                            const textarea = da.querySelector('textarea');
                            const file = da.querySelector('input[type="file"]');
                            if(textarea) textarea.removeAttribute('required');
                            if(file) file.removeAttribute('required');
                        });
                    } else {
                        container.classList.remove('d-none');
                        document.querySelectorAll(`.hidden-no-prod-${targetId}`).forEach(hidden => hidden.remove());
                    }
                };
                
                // Initial run if checked by server
                if (toggle.checked) handleToggle(toggle);
                
                // Event listener
                toggle.addEventListener('change', function() { handleToggle(this); });
            });

            // Filter Tabs Logic
            const filterTabs = document.querySelectorAll('.filter-tab');
            const targetForms = Array.from(document.querySelectorAll('.target-form'));
            const emptyFilterState = document.getElementById('empty-filter-state');
            const sessionId = '{{ $session->id }}';
            const pendingCount = {{ $pendingTargets }};
            
            let currentFilter = 'all';

            function applyFilter(filter) {
                try {
                    currentFilter = filter;
                    
                    // Update tabs UI
                    filterTabs.forEach(tab => {
                        if(tab.dataset.filter === filter) {
                            tab.classList.add('active');
                        } else {
                            tab.classList.remove('active');
                        }
                    });
                    
                    // Filter matching forms
                    const matchingForms = targetForms.filter(form => filter === 'all' || form.dataset.status === filter);
                    
                    // Show/hide forms
                    let visibleCount = 0;
                    targetForms.forEach(form => {
                        form.style.display = 'none'; // hide all first
                    });
                    
                    for (let i = 0; i < matchingForms.length; i++) {
                        matchingForms[i].style.display = 'block';
                        visibleCount++;
                    }
                    
                    // Show/hide empty state
                    if (emptyFilterState) {
                        if (visibleCount === 0) {
                            emptyFilterState.classList.remove('d-none');
                        } else {
                            emptyFilterState.classList.add('d-none');
                        }
                    }
                    
                    // Show/hide location group headers based on visible forms
                    document.querySelectorAll('.location-group').forEach(group => {
                        const visibleForms = Array.from(group.querySelectorAll('.target-form')).filter(f => f.style.display !== 'none');
                        const header = group.querySelector('.location-header');
                        if(header) {
                            if(visibleForms.length > 0) {
                                header.classList.remove('d-none');
                                header.classList.add('d-flex');
                            } else {
                                header.classList.remove('d-flex');
                                header.classList.add('d-none');
                            }
                        }
                    });

                    // Save selection
                    try {
                        sessionStorage.setItem('bulk_filter_' + sessionId, filter);
                    } catch (e) {
                        // Ignore sessionStorage errors (e.g., in incognito or blocked cookies)
                    }
                } catch (e) {
                    console.error("Error applying filter:", e);
                }
            }
            
            filterTabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    applyFilter(this.dataset.filter);
                });
            });
            
            try {
                const savedFilter = sessionStorage.getItem('bulk_filter_' + sessionId);
                if (savedFilter) {
                    applyFilter(savedFilter);
                } else if (pendingCount > 0) {
                    applyFilter('pending');
                } else {
                    applyFilter('all');
                }
            } catch (e) {
                console.error("Error reading sessionStorage:", e);
                // Fallback if sessionStorage fails
                if (pendingCount > 0) {
                    applyFilter('pending');
                } else {
                    applyFilter('all');
                }
            }

            // Auto-expand and scroll to the first unsaved accordion item
            const firstUnsaved = document.querySelector('.accordion-item[data-saved="0"]');
            
            if (firstUnsaved) {
                // Find its collapse target
                const collapseElement = firstUnsaved.querySelector('.accordion-collapse');
                if (collapseElement) {
                    const bsCollapse = new bootstrap.Collapse(collapseElement, { toggle: false });
                    bsCollapse.show();
                    
                    // Smooth scroll to it slightly after the accordion opens
                    setTimeout(() => {
                        const yOffset = -80; // Offset for sticky headers if any
                        const y = firstUnsaved.getBoundingClientRect().top + window.pageYOffset + yOffset;
                        window.scrollTo({top: y, behavior: 'smooth'});
                    }, 400);
                }
            } else {
                // If all are saved, just expand the first one so it's not totally empty
                const firstItem = document.querySelector('.accordion-collapse');
                if (firstItem) {
                    new bootstrap.Collapse(firstItem, { toggle: false }).show();
                }
            }

            const finishBtn = document.querySelector('button[name="save_action"][value="finish"]');
            if (finishBtn) {
                finishBtn.closest('form').addEventListener('submit', function(e) {
                    if (!e.submitter || e.submitter.value !== 'finish') return;
                    const unsaved = document.querySelectorAll('.accordion-item[data-saved="0"]');
                    if (unsaved.length > 0) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'ยังมี ' + unsaved.length + ' รายการที่ยังไม่บันทึก',
                            text: 'กรุณาบันทึกข้อมูลให้ครบก่อนกด จบงาน',
                            confirmButtonText: 'กลับไปบันทึก',
                            confirmButtonColor: '#d97706'
                        }).then(() => {
                            // Scroll to the first unsaved item after dismissing alert
                            const target = unsaved[0];
                            if(target) {
                                const collapseElement = target.querySelector('.accordion-collapse');
                                if (collapseElement) {
                                    new bootstrap.Collapse(collapseElement, { toggle: false }).show();
                                }
                                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        });
                    }
                });
            }
        })();

        // Expand/Collapse Location Container
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.location-group .collapse').forEach(function(collapseEl) {
                collapseEl.addEventListener('hide.bs.collapse', function() {
                    if (this.id.startsWith('loc_container_')) {
                        const btn = document.querySelector(`[data-bs-target="#${this.id}"] .toggle-text`);
                        if (btn) btn.innerHTML = '<i class="bi bi-chevron-bar-expand me-1"></i> ขยายกลุ่มนี้';
                    }
                });
                collapseEl.addEventListener('show.bs.collapse', function() {
                    if (this.id.startsWith('loc_container_')) {
                        const btn = document.querySelector(`[data-bs-target="#${this.id}"] .toggle-text`);
                        if (btn) btn.innerHTML = '<i class="bi bi-chevron-bar-contract me-1"></i> ย่อกลุ่มนี้';
                    }
                });
            });
        });
    </script>
    
    <!-- Prevent Accidental Exit -->
    <script>
        let isIntentionalNav = false;

        document.addEventListener('DOMContentLoaded', function() {
            // All forms are intentional
            document.querySelectorAll('form').forEach(f => {
                f.addEventListener('submit', () => isIntentionalNav = true);
            });

            // Back button is intentional
            const backBtn = document.querySelector('a[href*="dashboard"]');
            if (backBtn) {
                backBtn.addEventListener('click', () => isIntentionalNav = true);
            }

            // ส่วนที่ 3: Auto-expand สำหรับรายการ "ไม่ผ่าน"
            // รายการที่ผลออกมา "ไม่ผ่าน" จะเปิด Accordion ค้างไว้ให้เห็นทันที
            document.querySelectorAll('.accordion-item[data-has-fail="1"]').forEach(item => {
                const collapseEl = item.querySelector('.accordion-collapse');
                const btn = item.querySelector('.accordion-button');
                if (!collapseEl || !btn) return;

                // เปิด Accordion โดยไม่ trigger animation
                collapseEl.classList.add('show');
                btn.classList.remove('collapsed');
                btn.setAttribute('aria-expanded', 'true');
            });
        });

        window.addEventListener('beforeunload', function (e) {
            if (!isIntentionalNav) {
                e.preventDefault();
                e.returnValue = ''; // Standard behavior requires truthy value
            }
        });

        function hasUnsavedChanges(locationId) {
            let unsavedNames = [];
            let selector = '.accordion-item[data-saved="0"]';
            if (locationId) {
                selector = `#loc_container_${locationId} ${selector}`;
            }
            const unsavedItems = document.querySelectorAll(selector);
            unsavedItems.forEach(item => {
                const checkedRadios = item.querySelectorAll('input[type="radio"]:checked');
                if (checkedRadios.length > 0) {
                    let titleEl = item.querySelector('.accordion-button h5');
                    if (titleEl) {
                        unsavedNames.push(titleEl.innerText.trim());
                    } else {
                        unsavedNames.push('รายการที่ไม่ทราบชื่อ');
                    }
                }
            });
            return unsavedNames;
        }

        async function submitUnsavedForms(locationId) {
            const unsavedForms = document.querySelectorAll(`#loc_container_${locationId} form.target-form[data-status="pending"]`);
            if (unsavedForms.length === 0) return true;

            const combinedFormData = new FormData();
            combinedFormData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            
            let hasData = false;
            let locationInputAppended = false;

            unsavedForms.forEach(form => {
                const formData = new FormData(form);
                for (let [key, value] of formData.entries()) {
                    if (key !== '_token') {
                        if (value instanceof File && value.size === 0) continue;
                        
                        // Use the action URL of the first form to get the location ID if needed, 
                        // but they all point to the same location.
                        combinedFormData.append(key, value);
                        if (key.startsWith('results[targets]') || key.startsWith('no_production')) {
                            hasData = true;
                        }
                    }
                }
            });

            if (!hasData) return true;

            try {
                // Use the first form's action URL
                const targetUrl = unsavedForms[0].action;
                const response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: combinedFormData
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Validation error');
                }
                return true;
            } catch (error) {
                Swal.fire('ข้อผิดพลาดในการบันทึกข้อมูลที่เลือกไว้', error.message, 'error');
                return false;
            }
        }

        function markRoomNoProduction(locationId, locName, targetsQuery) {
            executeMarkRoomNoProduction(locationId, locName, targetsQuery);
        }

        function executeMarkRoomNoProduction(locationId, locName, targetsQuery) {
            Swal.fire({
                title: 'ยืนยันไม่มีการผลิตทั้งห้อง?',
                html: `ระบบจะทำการเปลี่ยนสถานะทุกรายการใน <b>${locName}</b> ให้เป็น <b>"ไม่มีผลิต"</b> (ข้อมูลเดิมจะถูกเขียนทับ) ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-circle"></i> ยืนยัน "ไม่มีผลิต" ทั้งห้อง',
                cancelButtonText: 'ยกเลิก'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังบันทึก...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Loop Engineering Fix: Do NOT call submitUnsavedForms here!
                    // Calling it submits all pending forms as 'pass' first, creating a race condition
                    // where if bulk-no-production fails or gets delayed, they get stuck as 'pass'.
                    // bulk-no-production endpoint already handles creating/overwriting all logs to 'no_production'.

                    // 1. Call the bulk endpoint directly
                    fetch(`{{ url('/inspection/area') }}/{{ $session->id }}/${locationId}/bulk-no-production`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            session_id: '{{ $session->id }}',
                            location_id: locationId,
                            targets_query: targetsQuery
                        })
                    })
                    .then(response => {
                        if(!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'สำเร็จ!',
                                text: data.message,
                                icon: 'success',
                                confirmButtonText: 'ตกลง',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('ข้อผิดพลาด', data.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง', 'error');
                    });
                }
            });
        }

        // Fix #5 UI: bulk 'no production' for ONLY the machines that still have no log.
        // Does not touch the location's own area checkpoints or the machines that were
        // already inspected — non-destructive counterpart to markRoomNoProduction.
        function markRemainingMachinesNoProduction(locationId, locName, machineTargetsQuery, remainingCount) {
            Swal.fire({
                title: 'ไม่มีผลิต — เฉพาะเครื่องที่เหลือ?',
                html: `ระบบจะทำเครื่องหมาย <b>"ไม่มีผลิต"</b> ให้เฉพาะเครื่องจักรใน <b>${locName}</b> ที่ยังไม่ตรวจ (${remainingCount} เครื่อง)<br><br><small class="text-muted">เครื่องที่ตรวจไปแล้วและจุดตรวจของห้องจะไม่ถูกแตะต้อง</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-circle"></i> ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (!result.isConfirmed) return;

                Swal.fire({
                    title: 'กำลังบันทึก...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                fetch(`{{ url('/inspection/area') }}/{{ $session->id }}/${locationId}/bulk-no-production-remaining`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ targets_query: machineTargetsQuery })
                })
                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        Swal.fire({
                            title: 'สำเร็จ!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonText: 'ตกลง',
                            confirmButtonColor: '#28a745'
                        }).then(() => window.location.reload());
                    } else {
                        Swal.fire('ข้อผิดพลาด', data.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('ข้อผิดพลาด', 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง', 'error');
                });
            });
        }

        function markRoomPass(locationId, locName, targetsQuery) {
            executeMarkRoomPass(locationId, locName, targetsQuery);
        }

        function executeMarkRoomPass(locationId, locName, targetsQuery) {
            Swal.fire({
                title: 'ยืนยันการจัดการรายการที่เหลือ?',
                html: `ระบบจะทำการ <b>บันทึกข้อมูลที่คุณเพิ่งเลือกไว้บนหน้าจอ</b> ให้เสร็จสิ้นก่อน<br><br>จากนั้นจะปรับสถานะเฉพาะรายการที่ยังไม่ได้ตรวจใน <b>${locName}</b> ให้เป็น <b>"ผ่าน"</b> ใช่หรือไม่?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-lg"></i> ยืนยันให้ "ผ่าน" เฉพาะรายการที่เหลือ',
                cancelButtonText: 'ยกเลิก'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังบันทึก...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // 1. Submit any unsaved selections first
                    const saveSuccess = await submitUnsavedForms(locationId);
                    if (!saveSuccess) return; // Stop if validation failed

                    // 2. Call the bulk pass endpoint
                    fetch(`{{ url('/inspection/area') }}/{{ $session->id }}/${locationId}/bulk-pass`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            session_id: '{{ $session->id }}',
                            location_id: locationId,
                            targets_query: targetsQuery
                        })
                    })
                    .then(response => {
                        if(!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'บันทึกสำเร็จ',
                                text: `บันทึกรายการใน ${locName} เรียบร้อยแล้ว`,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                isIntentionalNav = true; // allow reload without warning
                                window.location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error occurred');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง', 'error');
                    });
                }
            });
        }
    </script>
</x-app-layout>
