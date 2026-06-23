<x-app-layout>
    @section('header', 'ทวนสอบผลการตรวจ (Verification)')

    <div class="row">
        <div class="col-12">
            <!-- Level 1: Type Tabs (Main Category) -->
            <div class="mb-4 animate-in">
                <ul class="nav nav-pills nav-pills-modern nav-fill">
                    <li class="nav-item">
                        <a class="nav-link {{ $filterType === 'person' ? 'active' : '' }}" 
                           href="{{ route('inspection.verification', ['date' => $date, 'filter_type' => 'person', 'tab' => 'pending']) }}">
                            <i class="bi bi-person-badge me-2"></i>พนักงาน<span class="d-none d-sm-inline"> (Personnel)</span>
                            @if($typeCounts['person'] > 0)
                                <span class="badge {{ $filterType === 'person' ? 'bg-white text-primary' : 'bg-primary text-white' }} rounded-pill ms-1">{{ $typeCounts['person'] }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $filterType === 'area' ? 'active' : '' }}" 
                           href="{{ route('inspection.verification', ['date' => $date, 'filter_type' => 'area', 'tab' => 'pending']) }}">
                            <i class="bi bi-shop me-2"></i>พื้นที่<span class="d-none d-sm-inline"> (Area)</span>
                            @if($typeCounts['area'] > 0)
                                <span class="badge {{ $filterType === 'area' ? 'bg-white text-primary' : 'bg-primary text-white' }} rounded-pill ms-1">{{ $typeCounts['area'] }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $filterType === 'machine' ? 'active' : '' }}" 
                           href="{{ route('inspection.verification', ['date' => $date, 'filter_type' => 'machine', 'tab' => 'pending']) }}">
                            <i class="bi bi-gear-wide-connected me-2"></i>เครื่องจักร<span class="d-none d-sm-inline"> (Machine)</span>
                            @if($typeCounts['machine'] > 0)
                                <span class="badge {{ $filterType === 'machine' ? 'bg-white text-primary' : 'bg-primary text-white' }} rounded-pill ms-1">{{ $typeCounts['machine'] }}</span>
                            @endif
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Level 2: Status & Date Filter -->
            <div class="row g-3 mb-4 align-items-center">
                <div class="col-md-8">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('inspection.verification', ['date' => $date, 'filter_type' => $filterType, 'tab' => 'pending']) }}" 
                           class="btn rounded-pill px-4 {{ $activeTab === 'pending' ? 'btn-dark shadow-sm' : 'btn-light border text-muted' }}">
                            <i class="bi bi-clock-history me-1"></i> รอทวนสอบ
                            @if($counts['pending'] > 0)
                                <span class="badge bg-danger ms-1 rounded-pill">{{ $counts['pending'] }}</span>
                            @endif
                        </a>
                        <a href="{{ route('inspection.verification', ['date' => $date, 'filter_type' => $filterType, 'tab' => 'reclean']) }}" 
                           class="btn rounded-pill px-4 {{ $activeTab === 'reclean' ? 'btn-warning text-dark shadow-sm fw-bold' : 'btn-light border text-muted' }}">
                            <i class="bi bi-arrow-repeat me-1"></i> สั่งแก้ไข
                            @if($counts['reclean'] > 0)
                                <span class="badge bg-dark text-white ms-1 rounded-pill">{{ $counts['reclean'] }}</span>
                            @endif
                        </a>
                        <a href="{{ route('inspection.verification', ['date' => $date, 'filter_type' => $filterType, 'tab' => 'completed']) }}" 
                           class="btn rounded-pill px-4 {{ $activeTab === 'completed' ? 'btn-success shadow-sm' : 'btn-light border text-muted' }}">
                            <i class="bi bi-check-circle me-1"></i> รออนุมัติ
                            <span class="badge bg-white text-success border ms-1 rounded-pill">{{ $counts['completed'] }}</span>
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <form action="{{ route('inspection.verification') }}" method="GET">
                        <input type="hidden" name="filter_type" value="{{ $filterType }}">
                        <input type="hidden" name="tab" value="{{ $activeTab }}">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-calendar-event"></i></span>
                            <input type="date" class="form-control border-start-0 ps-0" name="date" value="{{ $date }}" onchange="this.form.submit()">
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Section (Desktop) -->
            <div class="card bg-white rounded-4 overflow-hidden d-none d-md-block">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="min-width: 800px;">
                            <thead class="bg-light border-bottom">
                                <tr>
                                    @if($activeTab === 'pending' || $activeTab === 'completed')
                                    <th class="ps-4 py-3" style="width: 50px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAllDesktop" onchange="toggleAllCheckboxes(this)">
                                        </div>
                                    </th>
                                    @endif
                                    <th class="{{ $activeTab === 'pending' ? 'ps-2' : 'ps-4' }} py-3 text-muted fw-bold" style="width: 100px;">เวลา</th>
                                    <th class="py-3 text-muted fw-bold">รอบ/กะ</th> 
                                    <th class="py-3 text-muted fw-bold">รายการตรวจ (Item)</th>
                                    <th class="py-3 text-muted fw-bold">แผนก/พื้นที่</th>
                                    <th class="py-3 text-muted fw-bold text-center">ผลการตรวจ</th>
                                    <th class="py-3 text-muted fw-bold text-center">สถานะทวนสอบ</th> 
                                    <th class="py-3 text-muted fw-bold">ข้อบกพร่อง (Findings)</th>
                                    <th class="pe-4 py-3 text-muted fw-bold text-end">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($groupedInspections as $group)
                                <tr>
                                    @if($activeTab === 'pending' || $activeTab === 'completed')
                                    <td class="ps-4">
                                        <div class="form-check">
                                            <input class="form-check-input item-checkbox" type="checkbox" value="{{ json_encode($group->log_ids) }}" onchange="updateBulkActionUI()">
                                        </div>
                                    </td>
                                    @endif
                                    <td class="{{ ($activeTab === 'pending' || $activeTab === 'completed') ? 'ps-2' : 'ps-4' }} fw-normal text-muted">{{ $group->time }}</td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="badge bg-light text-dark mb-1 border">รอบที่ {{ $group->round }}</span>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">{{ $group->shift }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-3 overflow-hidden border" style="width:40px; height:40px;">
                                                @if($group->image_path)
                                                    <img src="{{ asset('storage/' . $group->image_path) }}" alt="{{ $group->name }}" class="w-100 h-100 object-fit-cover">
                                                @elseif($group->type === 'area')
                                                    <i class="bi bi-layers text-secondary fs-5"></i>
                                                @elseif($group->type === 'machine')
                                                    <i class="bi bi-gear-wide-connected text-secondary fs-5"></i>
                                                @else
                                                    <span class="fw-bold text-secondary">{{ substr($group->name, 0, 1) }}</span>
                                                @endif
                                            </div>
                                            <span class="fw-bold text-dark">{{ $group->name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-secondary">{{ $group->subtext }}</td>
                                    <td class="text-center">
                                        @if($group->status === 'pass')
                                            <span class="badge badge-soft-success px-3 py-2 rounded-pill fw-normal">
                                                <i class="bi bi-check-circle me-1"></i> ผ่าน
                                            </span>
                                        @else
                                            <span class="badge badge-soft-danger px-3 py-2 rounded-pill fw-normal">
                                                <i class="bi bi-x-circle me-1"></i> ไม่ผ่าน
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($group->verification_status === 'approved')
                                            <span class="badge bg-success px-3 py-2 rounded-pill shadow-sm">
                                                <i class="bi bi-check-circle-fill me-1"></i> อนุมัติแล้ว
                                            </span>
                                        @elseif($group->verification_status === 'reclean')
                                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm border border-warning">
                                                <i class="bi bi-arrow-repeat me-1"></i> สั่งแก้ไข
                                            </span>
                                        @elseif($group->is_verified)
                                            <span class="badge bg-primary px-3 py-2 rounded-pill shadow-sm">
                                                <i class="bi bi-shield-check me-1"></i> ยืนยันแล้ว
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted px-3 py-2 rounded-pill border">
                                                <i class="bi bi-hourglass-split me-1"></i> รอยืนยัน
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($group->findings->isEmpty())
                                            <span class="text-muted small">-</span>
                                        @else
                                            @foreach($group->findings as $log)
                                                <div class="mb-2">
                                                    @if($log->verification_status === 'approved' || (isset($log->correctiveAction) && in_array($log->correctiveAction->status, ['resolved', 'closed'])))
                                                        <span class="text-success small fw-bold d-block">
                                                            <i class="bi bi-check-circle-fill me-1"></i> {{ $log->checkpoint?->title ?? 'Unknown' }}
                                                            <span class="badge bg-success bg-opacity-10 text-success border border-success ms-1">แก้ไขแล้ว</span>
                                                        </span>
                                                        <span class="text-muted x-small d-block ps-4 fst-italic strike-through">
                                                            {{ $log->correction_action }}
                                                        </span>
                                                    @else
                                                        <span class="text-danger small fw-bold d-block">• {{ $log->checkpoint?->title ?? 'Unknown' }}</span>
                                                        @if($log->correction_action)
                                                            <span class="text-muted x-small d-block ps-2" style="font-size: 0.75rem;">โน้ต: {{ $log->correction_action }}</span>
                                                        @endif
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    </td>
                                    <td class="pe-4 text-end">
                                        <button class="btn btn-link text-primary text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#detailModal{{ $group->modal_id }}">
                                            <i class="bi bi-eye"></i> รายละเอียด
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="{{ ($activeTab === 'pending' || $activeTab === 'completed') ? 9 : 8 }}" class="text-center py-5 text-muted">
                                        <i class="bi bi-clipboard-x fs-1 d-block mb-3"></i>
                                        ไม่พบข้อมูลการตรวจในวันนี้
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Mobile Card View Section (Mobile) -->
            <div class="d-block d-md-none">
                @forelse($groupedInspections as $group)
                <div class="card border-0 shadow-sm rounded-4 mb-3 position-relative">
                    @if($activeTab === 'pending' || $activeTab === 'completed')
                    <div class="position-absolute top-0 end-0 p-3 z-3">
                        <div class="form-check" style="transform: scale(1.3);">
                            <input class="form-check-input item-checkbox border-secondary shadow-sm" type="checkbox" value="{{ json_encode($group->log_ids) }}" onchange="updateBulkActionUI()" onclick="event.stopPropagation()">
                        </div>
                    </div>
                    @endif
                    <div class="card-body p-3" onclick="new bootstrap.Modal(document.getElementById('detailModal{{ $group->modal_id }}')).show()" style="cursor: pointer;">
                        <div class="d-flex align-items-center mb-2 {{ $activeTab === 'pending' ? 'pe-4' : '' }}">
                            <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-3 overflow-hidden border flex-shrink-0" style="width:48px; height:48px;">
                                @if($group->image_path)
                                    <img src="{{ asset('storage/' . $group->image_path) }}" alt="{{ $group->name }}" class="w-100 h-100 object-fit-cover">
                                @elseif($group->type === 'area')
                                    <i class="bi bi-layers text-secondary fs-4"></i>
                                @elseif($group->type === 'machine')
                                    <i class="bi bi-gear-wide-connected text-secondary fs-4"></i>
                                @else
                                    <span class="fw-bold text-secondary fs-5">{{ substr($group->name, 0, 1) }}</span>
                                @endif
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="fw-bold text-dark mb-0 text-truncate">{{ $group->name }}</h6>
                                    @if($activeTab !== 'pending')
                                        @if($group->status === 'pass')
                                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 x-small flex-shrink-0">
                                                <i class="bi bi-check-circle-fill me-1"></i>ผ่าน
                                            </span>
                                        @else
                                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-1 x-small flex-shrink-0">
                                                <i class="bi bi-x-circle-fill me-1"></i>ไม่ผ่าน
                                            </span>
                                        @endif
                                    @endif
                                </div>
                                <div class="text-muted x-small text-truncate">{{ $group->subtext }}</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center bg-light rounded-3 p-2 mb-2">
                            <div class="d-flex gap-2">
                                <span class="badge bg-white border text-secondary fw-normal">{{ $group->time }}</span>
                                <span class="badge bg-white border text-secondary fw-normal">รอบที่ {{ $group->round }}</span>
                                <span class="badge bg-white border text-secondary fw-normal">{{ $group->shift }}</span>
                            </div>
                            @if(!$group->findings->isEmpty())
                                <small class="text-danger fw-bold"><i class="bi bi-exclamation-circle me-1"></i>{{ $group->findings->count() }} ข้อผิดพลาด</small>
                            @endif
                        </div>

                        <div class="d-grid">
                            @if($group->is_verified)
                                @if($group->verification_status === 'reclean')
                                    <button class="btn btn-warning btn-sm rounded-pill w-100 text-dark fw-bold">
                                        <i class="bi bi-arrow-repeat me-1"></i> สั่งแก้ไขใหม่แล้ว
                                    </button>
                                @else
                                    <button class="btn btn-outline-primary btn-sm rounded-pill w-100 disabled border-primary bg-primary bg-opacity-10 opacity-100">
                                        <i class="bi bi-shield-check me-1"></i> ยืนยันแล้ว
                                    </button>
                                @endif
                            @else
                                <button class="btn btn-primary btn-sm rounded-pill w-100 shadow-sm">
                                    <i class="bi bi-eye me-1"></i> ตรวจสอบ / ยืนยันผล
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-clipboard-x fs-1 d-block mb-3 opacity-50"></i>
                    <p class="mb-0">ไม่พบข้อมูลการตรวจในวันนี้</p>
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
                                    <div>{{ $group->shift }} (รอบที่ {{ $group->round }})</div>
                                </div>
                            </div>
                            <div class="text-start text-sm-end mt-2 mt-sm-0 w-100 w-sm-auto">
                                <div class="d-flex d-sm-block align-items-center justify-content-between">
                                    <span class="badge {{ $group->status === 'pass' ? 'bg-success' : 'bg-danger' }} rounded-pill px-3 py-2 mb-0 mb-sm-1">
                                        {{ $group->status === 'pass' ? 'ผ่าน' : 'ไม่ผ่าน' }}
                                    </span>
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
                                    @if($group->traffic_light === 'green')
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
                                    <div class="d-flex align-items-center justify-content-center">
                                        <div class="fw-bold me-2">{{ $group->hygiene_score }}%</div>
                                        <div class="progress flex-grow-1" style="height: 6px; min-width: 40px;">
                                            <div class="progress-bar {{ $group->hygiene_score >= 90 ? 'bg-success' : ($group->hygiene_score >= 80 ? 'bg-warning' : 'bg-danger') }}" 
                                                 role="progressbar" style="width: {{ $group->hygiene_score }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <h6 class="fw-bold mb-3"><i class="bi bi-list-check me-2 text-primary"></i>รายการที่ตรวจสอบ</h6>
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
                                    @foreach($group->all_logs as $log)
                                    <tr>
                                        <td>{{ $log->checkpoint->title ?? 'N/A' }}</td>
                                        <td class="text-center">
                                            @if($log->result === 'pass')
                                                <span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                                            @elseif($log->verification_status === 'approved')
                                                {{-- Resolved Failure --}}
                                                <span class="text-success" title="แก้ไขแล้ว (Resolved)">
                                                    <i class="bi bi-check-circle-fill"></i>
                                                    <span class="d-block x-small">แก้ไขแล้ว</span>
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

                                                    @if(isset($log->correctiveAction))
                                                        <div class="p-2 rounded bg-light border {{ in_array($log->correctiveAction->status, ['resolved', 'closed']) ? 'border-success bg-opacity-10 bg-success' : 'border-warning' }}">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <span class="badge {{ in_array($log->correctiveAction->status, ['resolved', 'closed']) ? 'bg-success' : 'bg-warning text-dark' }}">
                                                                    {{ ucfirst($log->correctiveAction->status) }}
                                                                </span>
                                                                @if($log->correctiveAction->resolved_at)
                                                                    <small class="text-muted">{{ \Carbon\Carbon::parse($log->correctiveAction->resolved_at)->format('d/m/Y H:i') }}</small>
                                                                @endif
                                                            </div>
                                                            
                                                            @if($log->correctiveAction->action_taken)
                                                                <div class="mb-1">
                                                                    <strong class="text-success">การแก้ไข (After):</strong> {{ $log->correctiveAction->action_taken }}
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
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

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
                        @if(!$group->is_verified)
                            {{-- Supervisor Verification Form --}}
                            <form action="{{ route('inspection.verify') }}" method="POST" class="w-100">
                                @csrf
                                @foreach($group->log_ids as $logId)
                                    <input type="hidden" name="ids[]" value="{{ $logId }}">
                                @endforeach
                                
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
                                </div>
                                @else
                                <input type="hidden" name="status" value="verified">
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

                        @elseif($group->is_approved && !$group->is_acknowledged && $group->status === 'fail' && (Auth::user()->department_id == $group->department_id || Auth::user()->isAdmin()))
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

<script>
function submitReject(modalId, logIds) {
    const comment = document.getElementById('comment_' + modalId)?.value || '';
    
    if (!comment.trim()) {
        alert('กรุณาระบุเหตุผลที่ตีกลับ (Please enter reject reason)');
        return;
    }
    
    // Build form with log IDs
    const idsContainer = document.getElementById('rejectIds');
    idsContainer.innerHTML = '';
    
    logIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        idsContainer.appendChild(input);
    });
    
    document.getElementById('rejectComment').value = comment;
    document.getElementById('rejectForm').submit();
}

function submitReclean(modalId, logIds) {
    const comment = document.getElementById('comment_' + modalId)?.value || '';
    // Comment is optional for reclean
    
    // Build form with log IDs
    const idsContainer = document.getElementById('recleanIds');
    idsContainer.innerHTML = '';
    
    logIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        idsContainer.appendChild(input);
    });
    
    document.getElementById('recleanComment').value = comment;
    
    console.log('Submitting Re-clean with IDs:', logIds, 'Comment:', comment);
    document.getElementById('recleanForm').submit();
}

function toggleAllCheckboxes(source) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateBulkActionUI();
}

function updateBulkActionUI() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const bulkBar = document.getElementById('bulkActionBar');
    const countSpan = document.getElementById('selectedCount');
    
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
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    if (checkboxes.length === 0) return;

    if (!confirm(`คุณแน่ใจหรือไม่ที่จะยืนยันรายการที่เลือกจำนวน ${checkboxes.length} รายการ?`)) {
        return;
    }

    const idsContainer = document.getElementById('bulkVerifyIds');
    idsContainer.innerHTML = '';
    
    checkboxes.forEach(cb => {
        try {
            const ids = JSON.parse(cb.value); // Parse the array of log_ids
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                idsContainer.appendChild(input);
            });
        } catch(e) {
            console.error("Error parsing checkbox value", e);
        }
    });

    document.getElementById('bulkVerifyForm').submit();
}

function submitBulkApprove() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    if (checkboxes.length === 0) return;

    if (!confirm(`คุณแน่ใจหรือไม่ที่จะอนุมัติรายการที่เลือกจำนวน ${checkboxes.length} รายการ?`)) {
        return;
    }

    const idsContainer = document.getElementById('bulkApproveIds');
    idsContainer.innerHTML = '';

    checkboxes.forEach(cb => {
        try {
            const ids = JSON.parse(cb.value);
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                idsContainer.appendChild(input);
            });
        } catch(e) {
            console.error("Error parsing checkbox value", e);
        }
    });

    document.getElementById('bulkApproveForm').submit();
}

// Format numbers for mobile checkboxes initially if needed
document.addEventListener('DOMContentLoaded', () => {
    updateBulkActionUI();
});
</script>

{{-- Floating Bulk Action Bar --}}
<div id="bulkActionBar" class="fixed-bottom bg-white border-top p-3 shadow-lg flex-row align-items-center justify-content-between z-3 d-none">
    <div class="d-flex align-items-center">
        <span class="fs-5 fw-bold text-primary me-2" id="selectedCount">0</span>
        <span class="text-secondary fw-semibold">รายการที่เลือก</span>
    </div>
    <div class="d-flex gap-2">
        @if($activeTab === 'pending')
        <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="submitBulkVerify()">
            <i class="bi bi-check2-all me-1"></i> ยืนยันทั้งหมด
        </button>
        @elseif($activeTab === 'completed')
        <button type="button" class="btn btn-success rounded-pill px-4 shadow-sm" onclick="submitBulkApprove()">
            <i class="bi bi-patch-check-fill me-1"></i> อนุมัติทั้งหมด
        </button>
        @endif
    </div>
</div>

{{-- Hidden Bulk Verify Form (Supervisor) --}}
<form id="bulkVerifyForm" action="{{ route('inspection.verify') }}" method="POST" style="display: none;">
    @csrf
    <div id="bulkVerifyIds"></div>
    <input type="hidden" name="status" value="verified">
</form>

{{-- Hidden Bulk Approve Form (Manager) --}}
<form id="bulkApproveForm" action="{{ route('inspection.approve') }}" method="POST" style="display: none;">
    @csrf
    <div id="bulkApproveIds"></div>
</form>

</x-app-layout>
