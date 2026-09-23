<x-app-layout>
    @section('header', 'ทวนสอบผล (Verify)')

    @push('styles')
    <style>
        /* Scoped Verification Page Design Tokens */
        .v-page {
            --v-border: #e2e8f0;
            --v-border-subtle: #f1f5f9;
            --v-text-main: #0f172a;
            --v-text-muted: #64748b;
            --v-text-subtle: #94a3b8;
            --v-card-bg: #ffffff;
            --v-table-header-bg: #f8fafc;
        }

        /* KPI Quick Bar */
        .v-kpi-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 0.875rem;
            margin-bottom: 1.25rem;
        }
        .v-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .v-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
        }
        .v-kpi-label {
            font-size: 0.8125rem;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        .v-kpi-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }
        .v-kpi-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        /* Type Segment Switcher (Level 1) */
        .v-type-nav {
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            display: flex;
            gap: 4px;
            border: 1px solid #e2e8f0;
            margin-bottom: 1.25rem;
        }
        .v-type-tab {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1rem;
            border-radius: 9px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .v-type-tab:hover {
            color: #0f172a;
            background: rgba(255, 255, 255, 0.6);
        }
        .v-type-tab.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
        }
        .v-type-badge {
            font-size: 0.75rem;
            padding: 0.15rem 0.6rem;
            border-radius: 9999px;
            font-weight: 600;
        }

        /* Status Tabs (Level 2) */
        .v-status-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1.125rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .v-status-tab:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #cbd5e1;
        }
        .v-status-tab.active-pending {
            background: #0f172a;
            color: #ffffff;
            border-color: #0f172a;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12);
        }
        .v-status-tab.active-reclean {
            background: #d97706;
            color: #ffffff;
            border-color: #d97706;
            box-shadow: 0 2px 6px rgba(217, 119, 6, 0.15);
        }
        .v-status-tab.active-completed {
            background: #059669;
            color: #ffffff;
            border-color: #059669;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.15);
        }

        /* Table Aesthetics */
        .v-table-container {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }
        .v-table {
            margin-bottom: 0;
        }
        .v-table thead th {
            background: #f8fafc;
            color: #475569;
            font-size: 0.8125rem;
            font-weight: 600;
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .v-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.15s ease;
        }
        .v-table tbody tr:last-child {
            border-bottom: none;
        }
        .v-table tbody tr:hover {
            background-color: #f8fafc;
        }
        .v-table tbody td {
            padding: 0.875rem 1rem;
            vertical-align: middle;
            font-size: 0.875rem;
        }

        /* Standardized Design Tokens for Badges */
        .v-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.275rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8125rem;
            font-weight: 500;
            line-height: 1.25;
            white-space: nowrap;
        }
        .v-badge-pass {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .v-badge-fail {
            background: #fff1f2;
            color: #9f1239;
            border: 1px solid #fecdd3;
        }
        .v-badge-resolved {
            background: #fefce8;
            color: #854d0e;
            border: 1px solid #fde047;
        }
        .v-badge-absent {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .v-badge-verified {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            font-weight: 600;
        }
        .v-badge-approved {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #6ee7b7;
            font-weight: 600;
        }
        .v-badge-reclean {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fcd34d;
            font-weight: 600;
        }
        .v-badge-self {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #ffedd5;
            font-size: 0.75rem;
            padding: 0.2rem 0.55rem;
        }

        /* Tactile Action Button */
        .v-btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.875rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #2563eb;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 9999px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }
        .v-btn-action:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(37, 99, 235, 0.1);
        }

        /* Mobile Card Styling */
        .v-mobile-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            transition: transform 0.1s ease;
        }
        .v-mobile-card:active {
            transform: scale(0.99);
        }

        /* Accessible Focus Outline */
        .v-btn-action:focus-visible, .v-status-tab:focus-visible, .v-type-tab:focus-visible {
            outline: 2px solid #2563eb;
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            .v-kpi-card, .v-btn-action, .v-type-tab, .v-status-tab, .v-mobile-card {
                transition: none !important;
                transform: none !important;
            }
        }
    </style>
    @endpush

    <div class="row v-page">
        <div class="col-12">
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <!-- KPI Summary Ribbon -->
            <div class="v-kpi-bar">
                <div class="v-kpi-card">
                    <div>
                        <div class="v-kpi-label">หมวดหมู่ปัจจุบัน</div>
                        <div class="v-kpi-number">{{ $filterType === 'person' ? 'พนักงาน' : 'พื้นที่/เครื่องจักร' }}</div>
                    </div>
                    <div class="v-kpi-icon" style="background: #eff6ff; color: #2563eb;">
                        <i class="bi {{ $filterType === 'person' ? 'bi-people-fill' : 'bi-gear-wide-connected' }}"></i>
                    </div>
                </div>
                <div class="v-kpi-card">
                    <div>
                        <div class="v-kpi-label">รอทวนสอบ (Pending)</div>
                        <div class="v-kpi-number {{ $counts['pending'] > 0 ? 'text-danger' : '' }}">{{ $counts['pending'] }}</div>
                    </div>
                    <div class="v-kpi-icon" style="background: {{ $counts['pending'] > 0 ? '#fff1f2' : '#f8fafc' }}; color: {{ $counts['pending'] > 0 ? '#e11d48' : '#64748b' }};">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="v-kpi-card">
                    <div>
                        <div class="v-kpi-label">สั่งแก้ไข (Re-clean)</div>
                        <div class="v-kpi-number {{ $counts['reclean'] > 0 ? 'text-warning' : '' }}">{{ $counts['reclean'] }}</div>
                    </div>
                    <div class="v-kpi-icon" style="background: {{ $counts['reclean'] > 0 ? '#fffbeb' : '#f8fafc' }}; color: {{ $counts['reclean'] > 0 ? '#d97706' : '#64748b' }};">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                </div>
                <div class="v-kpi-card">
                    <div>
                        <div class="v-kpi-label">รออนุมัติ / ผ่านแล้ว</div>
                        <div class="v-kpi-number text-success">{{ $counts['completed'] }}</div>
                    </div>
                    <div class="v-kpi-icon" style="background: #ecfdf5; color: #059669;">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Level 1: Type Segment Switcher -->
            <div class="v-type-nav">
                <a class="v-type-tab {{ $filterType === 'person' ? 'active' : '' }}"
                   href="{{ route('inspection.verification', ['date' => $date, 'filter_type' => 'person', 'tab' => $activeTab]) }}"
                   aria-label="สลับไปหมวดพนักงาน">
                    <i class="bi bi-person-badge"></i>
                    <span>พนักงาน</span>
                    @if($typeCounts['person'] > 0)
                        <span class="v-type-badge {{ $filterType === 'person' ? 'bg-primary text-white' : '' }}" style="{{ $filterType !== 'person' ? 'background: #e2e8f0; color: #475569;' : '' }}">{{ $typeCounts['person'] }}</span>
                    @endif
                </a>
                <a class="v-type-tab {{ $filterType === 'machine' ? 'active' : '' }}"
                   href="{{ route('inspection.verification', ['date' => $date, 'filter_type' => 'machine', 'tab' => $activeTab]) }}"
                   aria-label="สลับไปหมวดพื้นที่และเครื่องจักร">
                    <i class="bi bi-gear-wide-connected"></i>
                    <span>พื้นที่ / เครื่องจักร</span>
                    @if($typeCounts['machine'] > 0)
                        <span class="v-type-badge {{ $filterType === 'machine' ? 'bg-primary text-white' : '' }}" style="{{ $filterType !== 'machine' ? 'background: #e2e8f0; color: #475569;' : '' }}">{{ $typeCounts['machine'] }}</span>
                    @endif
                </a>
            </div>

            <!-- Level 2: Status Navigation & Date Filter -->
            <div class="row g-2 mb-4 align-items-center">
                <div class="col-12 col-md-7">
                    <div class="d-flex flex-nowrap gap-2 overflow-auto pb-1">
                        <a href="{{ route('inspection.verification', ['date' => $date, 'filter_type' => $filterType, 'tab' => 'pending']) }}"
                           class="v-status-tab {{ $activeTab === 'pending' ? 'active-pending' : '' }}"
                           aria-label="แสดงรายการรอทวนสอบ">
                            <i class="bi bi-clock-history"></i>
                            <span>รอทวนสอบ</span>
                            @if($counts['pending'] > 0)
                                <span class="badge rounded-pill {{ $activeTab === 'pending' ? 'bg-danger text-white' : 'bg-danger-subtle text-danger' }}">{{ $counts['pending'] }}</span>
                            @endif
                        </a>
                        <a href="{{ route('inspection.verification', ['date' => $date, 'filter_type' => $filterType, 'tab' => 'reclean']) }}"
                           class="v-status-tab {{ $activeTab === 'reclean' ? 'active-reclean' : '' }}"
                           aria-label="แสดงรายการสั่งแก้ไข">
                            <i class="bi bi-arrow-repeat"></i>
                            <span>สั่งแก้ไข</span>
                            @if($counts['reclean'] > 0)
                                <span class="badge rounded-pill {{ $activeTab === 'reclean' ? 'bg-dark text-white' : 'bg-warning-subtle text-warning-emphasis' }}">{{ $counts['reclean'] }}</span>
                            @endif
                        </a>
                        <a href="{{ route('inspection.verification', ['date' => $date, 'filter_type' => $filterType, 'tab' => 'completed']) }}"
                           class="v-status-tab {{ $activeTab === 'completed' ? 'active-completed' : '' }}"
                           aria-label="แสดงรายการรออนุมัติหรือผ่านแล้ว">
                            <i class="bi bi-check-circle"></i>
                            <span>รออนุมัติ</span>
                            <span class="badge rounded-pill {{ $activeTab === 'completed' ? 'bg-white text-success' : 'bg-success-subtle text-success' }}">{{ $counts['completed'] }}</span>
                        </a>
                    </div>
                </div>
                <div class="col-12 col-md-5">
                    <form action="{{ route('inspection.verification') }}" method="GET" id="filterForm" data-initial-date="{{ $date }}">
                        <input type="hidden" name="filter_type" value="{{ $filterType }}">
                        <input type="hidden" name="tab" value="{{ $activeTab }}">
                        
                        <div class="input-group rounded-pill overflow-hidden border bg-white shadow-xs" style="border-color: #cbd5e1 !important;">
                            <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="bi bi-calendar-range text-primary"></i></span>
                            <input type="text" class="form-control border-0 fw-semibold bg-white flatpickr-range text-dark py-2" name="date" value="{{ $date }}" placeholder="{{ empty($date) ? 'กำลังแสดงงานค้างทั้งหมด (คลิกเพื่อระบุวัน)' : 'เลือกช่วงวันที่...' }}" readonly style="cursor: pointer; font-size: 0.875rem;" aria-label="เลือกช่วงวันที่">
                            @if(!empty($date))
                            <button type="button" class="btn btn-white border-0 pe-3 text-danger" title="ล้างตัวกรองวันที่" onclick="clearDateFilter()" aria-label="ล้างตัวกรองวันที่">
                                <i class="bi bi-x-circle-fill"></i>
                            </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Section (Desktop) -->
            <div class="v-table-container d-none d-md-block">
                <div class="table-responsive w-100">
                    <table class="table v-table align-middle text-nowrap">
                        <thead>
                            <tr>
                                @if($activeTab === 'pending' || $activeTab === 'completed')
                                <th class="ps-4" style="width: 48px;">
                                    <div class="form-check m-0">
                                        <input class="form-check-input" type="checkbox" id="selectAllDesktop" onchange="toggleAllCheckboxes(this)" aria-label="เลือกทั้งหมด">
                                    </div>
                                </th>
                                @endif
                                <th class="{{ ($activeTab === 'pending' || $activeTab === 'completed') ? 'ps-2' : 'ps-4' }}">วัน-เวลา</th>
                                <th>รอบ/กะ</th> 
                                <th>รายการตรวจ (Item)</th>
                                <th>แผนก/พื้นที่</th>
                                <th class="text-center">ผลการตรวจ</th>
                                <th class="text-center">สถานะทวนสอบ</th> 
                                <th>ข้อบกพร่อง (Findings)</th>
                                <th class="pe-4 text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($groupedInspections as $group)
                            <tr>
                                @if($activeTab === 'pending' || $activeTab === 'completed')
                                <td class="ps-4">
                                    @if($activeTab === 'pending' || !$group->is_approved)
                                    <div class="form-check m-0">
                                        <input class="form-check-input item-checkbox" type="checkbox" value="{{ json_encode($group->log_ids) }}" onchange="updateBulkActionUI()" aria-label="เลือกรายการ {{ $group->name }}">
                                    </div>
                                    @endif
                                </td>
                                @endif
                                <td class="{{ ($activeTab === 'pending' || $activeTab === 'completed') ? 'ps-2' : 'ps-4' }}">
                                    <div class="fw-bold text-dark" style="font-size: 0.875rem;">{{ $group->date }}</div>
                                    <div class="small text-secondary d-flex align-items-center gap-1">
                                        <i class="bi bi-clock" style="font-size: 0.75rem;"></i> {{ $group->time }}
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge rounded-pill bg-light text-dark border align-self-start fw-normal px-2 py-1" style="font-size: 0.75rem;">
                                            รอบที่ {{ $group->round }}
                                        </span>
                                        <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 align-self-start fw-normal px-2 py-1" style="font-size: 0.75rem;">
                                            {{ $group->shift_label ?? $group->shift }}
                                        </span>
                                        @if($group->is_sampling ?? false)
                                            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle align-self-start fw-medium px-2 py-1" style="font-size: 0.75rem;" title="เซสชันนี้เกิดจากการสุ่มตรวจ">
                                                <i class="bi bi-shuffle me-1"></i>สุ่มตรวจ
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-3 d-flex justify-content-center align-items-center me-3 overflow-hidden border flex-shrink-0" style="width:40px; height:40px; background: #f8fafc;">
                                            @if($group->image_path)
                                                <img src="{{ asset('storage/' . $group->image_path) }}" alt="{{ $group->name }}" class="w-100 h-100 object-fit-cover">
                                            @elseif($group->type === 'area')
                                                <i class="bi bi-layers text-secondary fs-5"></i>
                                            @elseif($group->type === 'machine')
                                                <i class="bi bi-gear-wide-connected text-primary fs-5"></i>
                                            @else
                                                <i class="bi bi-person-badge text-primary fs-5"></i>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <span class="fw-bold text-dark d-block text-truncate" style="max-width: 320px;">{{ $group->name }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-secondary fw-medium">{{ $group->subtext }}</span>
                                </td>
                                <td class="text-center">
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
                                    @if($group->status === 'pass')
                                        <span class="v-badge v-badge-pass">
                                            <i class="bi bi-check-circle-fill"></i> ผ่าน
                                        </span>
                                    @elseif($group->status === 'no_production')
                                        <span class="v-badge v-badge-absent">
                                            <i class="bi bi-slash-circle"></i> งดผลิต
                                        </span>
                                    @elseif($group->status === 'absent')
                                        <span class="v-badge v-badge-absent">
                                            <i class="bi bi-person-dash"></i> ขาดงาน
                                        </span>
                                    @else
                                        @if($allResolved)
                                            <span class="v-badge v-badge-resolved" title="ตรวจพบข้อบกพร่องแต่ได้รับการแก้ไขแล้ว">
                                                <i class="bi bi-shield-check"></i> แก้ไขแล้ว
                                            </span>
                                        @else
                                            <span class="v-badge v-badge-fail">
                                                <i class="bi bi-x-circle-fill"></i> ไม่ผ่าน
                                            </span>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(!$group->is_action_required)
                                        <span class="v-badge v-badge-absent">
                                            <i class="bi bi-dash-circle"></i> ไม่ต้องอนุมัติ
                                        </span>
                                    @elseif($group->verification_status === 'approved')
                                        <span class="v-badge v-badge-approved">
                                            <i class="bi bi-check-circle-fill"></i> อนุมัติแล้ว
                                        </span>
                                    @elseif($group->verification_status === 'reclean')
                                        <span class="v-badge v-badge-reclean">
                                            <i class="bi bi-arrow-repeat"></i> สั่งแก้ไข
                                        </span>
                                    @elseif($group->verification_status === 'auto_verified')
                                        <span class="v-badge" style="background: #ecfeff; color: #0891b2; border: 1px solid #a5f3fc; font-weight: 600;">
                                            <i class="bi bi-robot"></i> ผ่านอัตโนมัติ
                                        </span>
                                    @elseif($group->is_verified)
                                        <span class="v-badge v-badge-verified">
                                            <i class="bi bi-shield-check"></i> ยืนยันแล้ว
                                        </span>
                                    @else
                                        <span class="v-badge" style="background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1;">
                                            <i class="bi bi-hourglass-split"></i> รอยืนยัน
                                        </span>
                                    @endif

                                    @if($group->self_verified ?? false)
                                        <span class="v-badge v-badge-self ms-1"
                                              title="ผู้ตรวจและผู้ยืนยันเป็นคนเดียวกัน — ต้องให้ผู้จัดการอนุมัติเป็นลายเซ็นที่สอง">
                                            <i class="bi bi-person-exclamation"></i> ตรวจ+ยืนยันคนเดียวกัน
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($group->findings->isEmpty())
                                        <span class="text-muted small">-</span>
                                    @else
                                        @foreach($group->findings as $log)
                                            <div class="mb-1">
                                                @if($log->verification_status === 'approved' || (isset($log->correctiveAction) && in_array($log->correctiveAction->status, ['resolved', 'closed', 'verified'])))
                                                    <span class="text-success small fw-semibold d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-check-circle-fill"></i> {{ $log->checkpoint?->title ?? 'Unknown' }}
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.7rem;">แก้ไขแล้ว</span>
                                                    </span>
                                                @else
                                                    <span class="text-danger small fw-semibold d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-exclamation-circle-fill"></i> {{ $log->checkpoint?->title ?? 'Unknown' }}
                                                    </span>
                                                    @if($log->correction_action)
                                                        <div class="text-muted ps-3" style="font-size: 0.75rem;">
                                                            {{ Str::limit($log->correction_action, 35) }}
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="v-btn-action" data-bs-toggle="modal" data-bs-target="#detailModal{{ $group->modal_id }}" aria-label="ดูรายละเอียด {{ $group->name }}">
                                        <i class="bi bi-eye"></i>
                                        <span>รายละเอียด</span>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ ($activeTab === 'pending' || $activeTab === 'completed') ? 9 : 8 }}" class="text-center py-5">
                                    <div class="py-4">
                                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                                            <i class="bi bi-clipboard-check text-secondary fs-2"></i>
                                        </div>
                                        @if($activeTab === 'completed')
                                        <div class="fw-bold text-dark fs-6 mb-1">ไม่พบข้อมูลการตรวจในวันที่เลือก</div>
                                        @else
                                        <div class="fw-bold text-dark fs-6 mb-1">ไม่มีงานค้างในระบบ</div>
                                        @endif
                                        <p class="text-secondary small mb-0">
                                            @if($activeTab === 'pending')
                                                ยังไม่มีรายการรอทวนสอบ — รายการจะปรากฏเมื่อผู้ตรวจส่งผลการตรวจเข้ามา
                                            @elseif($activeTab === 'reclean')
                                                ไม่มีรายการสั่งแก้ไขในระบบ
                                            @else
                                                ไม่มีรายการรออนุมัติในวันที่ระบุ
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Card View Section (Mobile) -->
            <div class="d-block d-md-none">
                @if(($activeTab === 'pending' || $activeTab === 'completed') && $groupedInspections->count() > 0)
                <div class="d-flex justify-content-between align-items-center mb-3 bg-white p-3 rounded-4 shadow-xs border" style="border-color: #e2e8f0;">
                    <label class="fw-bold text-dark mb-0" for="selectAllMobile">เลือกทั้งหมด (Select All)</label>
                    <div class="form-check m-0">
                        <input class="form-check-input border-secondary" type="checkbox" id="selectAllMobile" onchange="toggleAllCheckboxes(this)" style="width: 20px; height: 20px;" aria-label="เลือกทั้งหมด">
                    </div>
                </div>
                @endif
                
                @forelse($groupedInspections as $group)
                <div class="v-mobile-card">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-3 d-flex justify-content-center align-items-center me-3 overflow-hidden border flex-shrink-0" style="width:44px; height:44px; background: #f8fafc;">
                            @if($group->image_path)
                                <img src="{{ asset('storage/' . $group->image_path) }}" alt="{{ $group->name }}" class="w-100 h-100 object-fit-cover">
                            @elseif($group->type === 'area')
                                <i class="bi bi-layers text-secondary fs-5"></i>
                            @elseif($group->type === 'machine')
                                <i class="bi bi-gear-wide-connected text-primary fs-5"></i>
                            @else
                                <i class="bi bi-person-badge text-primary fs-5"></i>
                            @endif
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <h6 class="fw-bold text-dark mb-1 text-truncate" style="font-size:0.95rem;">{{ $group->name }}</h6>
                                @if($group->status === 'pass')
                                    <span class="v-badge v-badge-pass" style="font-size: 0.75rem; padding: 0.15rem 0.5rem;">
                                        <i class="bi bi-check-circle-fill"></i> ผ่าน
                                    </span>
                                @elseif($group->status === 'absent')
                                    <span class="v-badge v-badge-absent" style="font-size: 0.75rem; padding: 0.15rem 0.5rem;">
                                        <i class="bi bi-person-dash"></i> ขาดงาน
                                    </span>
                                @elseif($group->status === 'no_production')
                                    <span class="v-badge v-badge-absent" style="font-size: 0.75rem; padding: 0.15rem 0.5rem;">
                                        <i class="bi bi-slash-circle"></i> งดผลิต
                                    </span>
                                @else
                                    <span class="v-badge v-badge-fail" style="font-size: 0.75rem; padding: 0.15rem 0.5rem;">
                                        <i class="bi bi-x-circle-fill"></i> ไม่ผ่าน
                                    </span>
                                @endif
                            </div>
                            <div class="text-secondary small text-truncate">{{ $group->subtext }}</div>
                        </div>
                        @if($activeTab === 'pending' || ($activeTab === 'completed' && !$group->is_approved))
                        <div class="ms-2 ps-1 flex-shrink-0">
                            <input class="form-check-input item-checkbox border-secondary m-0" type="checkbox" value="{{ json_encode($group->log_ids) }}" onchange="updateBulkActionUI()" style="width: 20px; height: 20px;" aria-label="เลือกรายการ {{ $group->name }}">
                        </div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-between align-items-center bg-light rounded-3 p-2 mb-3">
                        <div class="d-flex gap-1 flex-wrap align-items-center">
                            <span class="badge bg-white border text-secondary fw-normal" style="font-size: 0.75rem;"><i class="bi bi-calendar3 me-1"></i>{{ $group->date }} {{ $group->time }}</span>
                            <span class="badge bg-white border text-secondary fw-normal" style="font-size: 0.75rem;">รอบ {{ $group->round }}</span>
                            <span class="badge bg-white border text-secondary fw-normal" style="font-size: 0.75rem;">{{ $group->shift }}</span>
                            @if($group->is_sampling ?? false)
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-medium" style="font-size: 0.75rem;" title="สุ่มตรวจ"><i class="bi bi-shuffle me-1"></i>สุ่มตรวจ</span>
                            @endif
                        </div>
                        @if(!$group->findings->isEmpty())
                            <span class="text-danger fw-bold small"><i class="bi bi-exclamation-circle me-1"></i>{{ $group->findings->count() }} ข้อผิดพลาด</span>
                        @endif
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-outline-primary rounded-pill w-100 fw-semibold d-flex align-items-center justify-content-center gap-2" onclick="new bootstrap.Modal(document.getElementById('detailModal{{ $group->modal_id }}')).show()" style="min-height: 44px;">
                            <i class="bi bi-eye"></i>
                            <span>{{ $group->is_verified ? 'ดูรายละเอียดผลตรวจ' : 'ตรวจสอบ / ยืนยันผล' }}</span>
                        </button>
                    </div>
                </div>
                @empty
                <div class="text-center py-5">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-clipboard-check text-secondary fs-2"></i>
                    </div>
                    @if($activeTab === 'completed')
                    <p class="fw-bold text-dark mb-1">ไม่พบข้อมูลการตรวจในวันที่เลือก</p>
                    @else
                    <p class="fw-bold text-dark mb-1">ไม่มีงานค้างในระบบ</p>
                    @endif
                    <small class="text-secondary">
                        @if($activeTab === 'pending')
                            ยังไม่มีรายการรอทวนสอบ — รายการจะปรากฏเมื่อผู้ตรวจส่งผลการตรวจเข้ามา
                        @elseif($activeTab === 'reclean')
                            ไม่มีรายการสั่งแก้ไข
                        @else
                            ไม่มีรายการรออนุมัติ
                        @endif
                    </small>
                </div>
                @endforelse
            </div>
            
            <!-- Pagination Links -->
            <div class="mt-4 mb-5 d-flex justify-content-center">
                {{ $groupedInspections->appends(request()->query())->links() }}
            </div>
        </div>
    </div>

    <!-- Script to handle dynamic status selection in modals -->
    @push('scripts')
    <script>
        function setApproveStatus(modalId, status) {
            const form = document.querySelector(`#detailModal${modalId} form`);
            if (form) {
                let statusInput = form.querySelector('input[name="status"]');
                if (!statusInput) {
                    statusInput = document.createElement('input');
                    statusInput.type = 'hidden';
                    statusInput.name = 'status';
                    form.appendChild(statusInput);
                }
                statusInput.value = status;
                form.submit();
            }
        }
    </script>
    @endpush

    @push('modals')
    <!-- Modals Section (Outside Table) -->
    @foreach($groupedInspections as $group)
        <div class="modal fade" id="detailModal{{ $group->modal_id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">รายละเอียดการตรวจสอบ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
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
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        @if(!$group->is_action_required)
                            <div class="w-100 d-flex justify-content-end">
                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
                            </div>
                        @elseif(!$group->is_verified)
                            {{-- Supervisor Verification Form --}}
                            <form action="{{ route('inspection.verify') }}" method="POST" class="w-100">
                                @csrf
                                @if($group->status !== 'fail')
                                <input type="hidden" name="status" value="verified">
                                @endif
                                <input type="hidden" name="ids" value="{{ json_encode($group->log_ids) }}">
                                
                                @if($group->status === 'fail')
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">ระบุสิ่งที่ต้องการให้แก้ไขเพิ่มเติม (ถ้ามี):</label>
                                    <textarea name="comment" id="comment_{{ $group->modal_id }}" class="form-control form-control-sm rounded-3" rows="2" placeholder="เช่น ทำความสะอาดพื้นซ้ำ หรือ จัดเก็บอุปกรณ์ให้เป็นระเบียบ..."></textarea>
                                </div>

                                <div class="d-flex justify-content-end gap-2 flex-wrap">
                                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
                                    
                                    {{-- Gap 1: Reject Button --}}
                                    <button type="button" class="btn btn-outline-danger rounded-pill px-4" 
                                            onclick="submitReject('{{ $group->modal_id }}', {{ json_encode($group->log_ids) }})">
                                        <i class="bi bi-x-circle me-2"></i> ตีกลับ (Reject)
                                    </button>
                                    
                                    {{-- Re-clean Button (Using JS for reliable submission) --}}
                                    <button type="button" class="btn btn-warning rounded-pill px-4 shadow-sm text-dark fw-bold"
                                            onclick="submitReclean('{{ $group->modal_id }}', {{ json_encode($group->log_ids) }})">
                                        <i class="bi bi-arrow-repeat me-2"></i> สั่งแก้ไขใหม่ (Order Re-clean)
                                    </button>
                                    
                                    {{-- Verify Shortcut Button --}}
                                    <button type="button" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold"
                                            onclick="submitVerify('{{ $group->modal_id }}', {{ json_encode($group->log_ids) }})">
                                        <i class="bi bi-shield-check me-2"></i> ผ่าน (Verify)
                                    </button>
                                </div>
                                @else
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                                        <i class="bi bi-shield-check me-2"></i> ยืนยันการทวนสอบ (Verify)
                                    </button>
                                </div>
                                @endif
                            </form>
                        
                        @elseif($group->is_verified && !$group->is_approved && (Auth::check() && (Auth::user()->level >= 5 || Auth::user()->isAdmin())))
                            {{-- Manager Approval Form --}}
                            <div class="w-100">
                                <div class="alert alert-info border-0 bg-info bg-opacity-10 mb-3 d-flex align-items-center">
                                    <i class="bi bi-info-circle-fill text-info me-2 fs-5"></i>
                                    <small>รายการนี้ได้รับการทวนสอบแล้ว ต้องการอนุมัติปิดงานหรือไม่?</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">ระบุเหตุผลกรณีต้องการตีกลับ (ถ้ามี):</label>
                                    <textarea id="comment_{{ $group->modal_id }}" class="form-control form-control-sm rounded-3" rows="2" placeholder="เช่น ต้องทำความสะอาดและตรวจสอบซ้ำ..."></textarea>
                                </div>

                                <form action="{{ route('inspection.approve') }}" method="POST">
                                    @csrf
                                    @foreach($group->log_ids as $logId)
                                        <input type="hidden" name="ids[]" value="{{ $logId }}">
                                    @endforeach
                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>

                                        <button type="button" class="btn btn-warning rounded-pill px-4 shadow-sm text-dark fw-bold"
                                                onclick="submitReclean('{{ $group->modal_id }}', {{ json_encode($group->log_ids) }})">
                                            <i class="bi bi-arrow-repeat me-2"></i> ตีกลับ / สั่งแก้ไข (Re-clean)
                                        </button>

                                        <button type="submit" class="btn btn-success rounded-pill px-4 shadow-sm">
                                            <i class="bi bi-check-circle-fill me-2"></i> อนุมัติการตรวจสอบ (Approve)
                                        </button>
                                    </div>
                                </form>
                            </div>

                        @elseif($group->is_approved && !$group->is_acknowledged && $group->status === 'fail' && Auth::check() && (Auth::user()->department_id == $group->department_id || Auth::user()->isAdmin()))
                            {{-- Gap 3: Dept Head Acknowledge Form --}}
                            <div class="w-100">
                                <div class="alert alert-warning border-0 bg-warning bg-opacity-10 mb-3 d-flex align-items-center">
                                    <i class="bi bi-bell-fill text-warning me-2 fs-5"></i>
                                    <small>มีพนักงานในแผนกของคุณที่ไม่ผ่านการตรวจ กรุณากด "รับทราบ" หลังจากตักเตือนแล้ว</small>
                                </div>
                                <form action="{{ route('inspection.acknowledge') }}" method="POST">
                                    @csrf
                                    @foreach($group->log_ids as $logId)
                                        <input type="hidden" name="ids[]" value="{{ $logId }}">
                                    @endforeach
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
                                        <button type="submit" class="btn btn-info rounded-pill px-4 shadow-sm">
                                            <i class="bi bi-check2-circle me-2"></i> รับทราบ / ตักเตือนแล้ว (Acknowledge)
                                        </button>
                                    </div>
                                </form>
                            </div>

                        @elseif($group->is_approved)
                            <div class="w-100 text-end">
                                <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 rounded-pill mb-2">
                                    <i class="bi bi-lock-fill me-1"></i> อนุมัติแล้วโดย {{ $group->approved_by_name }} ({{ $group->approved_at }})
                                </span>
                                @if($group->is_acknowledged)
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info px-3 py-2 rounded-pill mb-2 ms-2">
                                        <i class="bi bi-check2-all me-1"></i> หัวหน้าแผนกรับทราบแล้ว
                                    </span>
                                @endif
                                <button type="button" class="btn btn-light rounded-pill px-4 d-block ms-auto mt-2" data-bs-dismiss="modal">ปิด</button>
                            </div>

                        @else
                            {{-- Verified but not Manager --}}
                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ปิดข้อมูล</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
    @endpush

{{-- Gap 1: Hidden Reject Form --}}
<form id="rejectForm" action="{{ route('inspection.reject') }}" method="POST" style="display: none;">
    @csrf
    <div id="rejectIds"></div>
    <input type="hidden" name="comment" id="rejectComment">
</form>

{{-- Hidden Re-clean Form --}}
<form id="recleanForm" action="{{ route('inspection.verify') }}" method="POST" style="display: none;">
    @csrf
    <div id="recleanIds"></div>
    <input type="hidden" name="status" value="reclean">
    <input type="hidden" name="comment" id="recleanComment">
</form>

{{-- Hidden Verify Form --}}
<form id="verifyForm" action="{{ route('inspection.verify') }}" method="POST" style="display: none;">
    @csrf
    <div id="verifyIds"></div>
    <input type="hidden" name="status" value="verified">
    <input type="hidden" name="comment" id="verifyComment">
</form>

<script>
function submitReject(modalId, logIds) {
    const comment = document.getElementById('comment_' + modalId)?.value || '';
    
    if (!comment.trim()) {
        alert('กรุณาระบุเหตุผลที่ตีกลับ (Please enter reject reason)');
        return;
    }
    
    // Build form with log IDs as JSON string
    const idsContainer = document.getElementById('rejectIds');
    idsContainer.innerHTML = '';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'ids';
    input.value = JSON.stringify(logIds);
    idsContainer.appendChild(input);
    
    document.getElementById('rejectComment').value = comment;
    document.getElementById('rejectForm').submit();
}

function submitReclean(modalId, logIds) {
    const comment = document.getElementById('comment_' + modalId)?.value || '';
    // Comment is optional for reclean
    
    // Build form with log IDs as JSON string
    const idsContainer = document.getElementById('recleanIds');
    idsContainer.innerHTML = '';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'ids';
    input.value = JSON.stringify(logIds);
    idsContainer.appendChild(input);
    
    document.getElementById('recleanComment').value = comment;
    
    console.log('Submitting Re-clean with IDs:', logIds, 'Comment:', comment);
    document.getElementById('recleanForm').submit();
}

function submitVerify(modalId, logIds) {
    const comment = document.getElementById('comment_' + modalId)?.value || '';
    
    // Build form with log IDs as JSON string
    const idsContainer = document.getElementById('verifyIds');
    idsContainer.innerHTML = '';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'ids';
    input.value = JSON.stringify(logIds);
    idsContainer.appendChild(input);
    
    document.getElementById('verifyComment').value = comment;
    
    console.log('Submitting Verify with IDs:', logIds, 'Comment:', comment);
    document.getElementById('verifyForm').submit();
}

function getVisibleCheckboxes(selector) {
    return Array.from(document.querySelectorAll(selector)).filter(cb => cb.offsetParent !== null);
}

function toggleAllCheckboxes(source) {
    const checkboxes = getVisibleCheckboxes('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateBulkActionUI();
}

function updateBulkActionUI() {
    const checkboxes = getVisibleCheckboxes('.item-checkbox:checked');
    const bulkBar = document.getElementById('bulkActionBar');
    const countSpan = document.getElementById('selectedCount');
    if (!bulkBar || !countSpan) return;

    if (checkboxes.length > 0) {
        countSpan.textContent = checkboxes.length;
        bulkBar.style.display = 'flex';
        bulkBar.classList.replace('d-none', 'd-flex');
    } else {
        bulkBar.classList.replace('d-flex', 'd-none');
        bulkBar.style.display = 'none';
        const selectAll = document.getElementById('selectAllDesktop');
        if (selectAll) selectAll.checked = false;
    }
}

function submitBulkVerify() {
    const checkboxes = getVisibleCheckboxes('.item-checkbox:checked');
    if (checkboxes.length === 0) return;

    if (!confirm(`คุณแน่ใจหรือไม่ที่จะยืนยันรายการที่เลือกจำนวน ${checkboxes.length} รายการ?`)) {
        return;
    }

    const idsContainer = document.getElementById('bulkVerifyIds');
    idsContainer.innerHTML = '';
    
    let allIds = [];
    checkboxes.forEach(cb => {
        try {
            const ids = JSON.parse(cb.value); // Parse the array of log_ids
            allIds = allIds.concat(ids);
        } catch(e) {
            console.error("Error parsing checkbox value", e);
        }
    });
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'ids';
    input.value = JSON.stringify(allIds);
    idsContainer.appendChild(input);

    document.getElementById('bulkVerifyForm').submit();
}

function submitBulkApprove() {
    const checkboxes = getVisibleCheckboxes('.item-checkbox:checked');
    if (checkboxes.length === 0) return;

    if (!confirm(`คุณแน่ใจหรือไม่ที่จะอนุมัติรายการที่เลือกจำนวน ${checkboxes.length} รายการ?`)) {
        return;
    }

    const idsContainer = document.getElementById('bulkApproveIds');
    idsContainer.innerHTML = '';

    let allIds = [];
    checkboxes.forEach(cb => {
        try {
            const ids = JSON.parse(cb.value);
            allIds = allIds.concat(ids);
        } catch(e) {
            console.error("Error parsing checkbox value", e);
        }
    });

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'ids';
    input.value = JSON.stringify(allIds);
    idsContainer.appendChild(input);

    document.getElementById('bulkApproveForm').submit();
}

// Format numbers for mobile checkboxes initially if needed
document.addEventListener('DOMContentLoaded', () => {
    updateBulkActionUI();
});
</script>

{{-- Floating Bulk Action Bar --}}
<div id="bulkActionBar" class="fixed-bottom p-3 flex-row align-items-center justify-content-between z-3 d-none v-bulk-bar">
    <div class="d-flex align-items-center gap-2">
        <span class="badge rounded-pill bg-primary px-3 py-2 fs-6 fw-bold shadow-xs" id="selectedCount">0</span>
        <span class="text-dark fw-semibold">รายการที่เลือก</span>
    </div>
    <div class="d-flex gap-2">
        @if($activeTab === 'pending')
        <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold d-inline-flex align-items-center gap-1" onclick="submitBulkVerify()">
            <i class="bi bi-check2-all"></i> ยืนยันทั้งหมด
        </button>
        @elseif($activeTab === 'completed')
        @can('approve')
        <button type="button" class="btn btn-success rounded-pill px-4 shadow-sm fw-semibold d-inline-flex align-items-center gap-1" onclick="submitBulkApprove()">
            <i class="bi bi-patch-check-fill"></i> อนุมัติทั้งหมด
        </button>
        @endcan
        @endif
    </div>
</div>

@push('scripts')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr(".flatpickr-range", {
            mode: "range",
            dateFormat: "Y-m-d",
            locale: "th",
            onClose: function(selectedDates, dateStr, instance) {
                const form = document.getElementById('filterForm');
                const initialDate = form.getAttribute('data-initial-date');
                if (dateStr !== initialDate) {
                    form.submit();
                }
            }
        });
    });

    function clearDateFilter() {
        // Prevent infinite loop by clearing and submitting immediately
        const input = document.querySelector('.flatpickr-range');
        input.value = '';
        document.getElementById('filterForm').submit();
    }
</script>
@endpush

{{-- Hidden Bulk Verify Form (Supervisor) --}}
<form id="bulkVerifyForm" action="{{ route('inspection.verify') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="status" value="verified">
    <div id="bulkVerifyIds"></div>
</form>

{{-- Hidden Bulk Approve Form (Manager) --}}
<form id="bulkApproveForm" action="{{ route('inspection.approve') }}" method="POST" style="display: none;">
    @csrf
    <div id="bulkApproveIds"></div>
</form>

{{-- Hidden Forms for Modal Actions --}}
<form id="rejectForm" action="{{ route('inspection.reject') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="comment" id="rejectComment" value="">
    <div id="rejectIds"></div>
</form>

<form id="recleanForm" action="{{ route('inspection.verify') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="status" value="reclean">
    <input type="hidden" name="comment" id="recleanComment" value="">
    <div id="recleanIds"></div>
</form>

<form id="verifyForm" action="{{ route('inspection.verify') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="status" value="verified">
    <input type="hidden" name="comment" id="verifyComment" value="">
    <div id="verifyIds"></div>
</form>

</x-app-layout>
