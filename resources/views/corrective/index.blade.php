<x-app-layout>
    @section('header', 'สิ่งที่ต้องแก้ไข')

    @push('styles')
    <style>
        .impeccable-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.04), 0 2px 8px rgba(0, 0, 0, 0.02);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            background: #ffffff;
        }
        .impeccable-header-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .icon-danger {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #ef4444;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.1);
        }
        .icon-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #10b981;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
        }
        .task-item {
            border: 1px solid #f1f5f9;
            border-radius: 16px;
            background: #ffffff;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .task-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: #ef4444;
            opacity: 0.7;
        }
        .task-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06);
            border-color: #e2e8f0;
        }
        .task-item::before:hover {
            opacity: 1;
        }
        .premium-btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
            transition: all 0.2s;
        }
        .premium-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
            color: white;
        }
        .premium-btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
            transition: all 0.2s;
        }
        .premium-btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
            color: white;
        }
        .modern-table {
            border-collapse: separate;
            border-spacing: 0;
        }
        .modern-table thead th {
            background: #f8fafc;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 1rem 1.5rem;
            border-bottom: 2px solid #e2e8f0;
        }
        .modern-table tbody td {
            padding: 1.25rem 1.5rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .modern-table tbody tr:hover td {
            background: #f8fafc;
        }
        .modern-table tbody tr:last-child td {
            border-bottom: none;
        }
        .badge-soft-success {
            background-color: #dcfce7;
            color: #166534;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.35rem 0.75rem;
        }
        .badge-soft-warning {
            background-color: #fef3c7;
            color: #92400e;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.35rem 0.75rem;
        }
    </style>
    @endpush

    <div class="container-fluid py-4" style="background-color: #f8fafc; min-height: calc(100vh - 70px);">
        <!-- Dashboard Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-6">
                <div class="impeccable-card h-100 p-3 p-md-4 text-center">
                    <h6 class="text-muted fw-bold mb-2">ปัญหาสะสมทั้งหมด</h6>
                    <h2 class="fw-bolder text-dark mb-0">{{ $stats['total'] }}</h2>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="impeccable-card h-100 p-3 p-md-4 text-center" style="border-bottom: 4px solid #f59e0b;">
                    <h6 class="text-muted fw-bold mb-2">กำลังดำเนินการ</h6>
                    <h2 class="fw-bolder text-warning mb-0">{{ $stats['open'] }}</h2>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="impeccable-card h-100 p-3 p-md-4 text-center" style="border-bottom: 4px solid #10b981;">
                    <h6 class="text-muted fw-bold mb-2">แก้ไขแล้ว (รอตรวจ)</h6>
                    <h2 class="fw-bolder text-success mb-0">{{ $stats['resolved'] }}</h2>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="impeccable-card h-100 p-3 p-md-4 text-center" style="border-bottom: 4px solid #ef4444;">
                    <h6 class="text-muted fw-bold mb-2">เกินกำหนด (Overdue)</h6>
                    <h2 class="fw-bolder text-danger mb-0">{{ $stats['overdue'] }}</h2>
                </div>
            </div>
        </div>

        @if(isset($aiTagsTrend) && $aiTagsTrend->isNotEmpty())
        <!-- AI Smart Tags Trend -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="impeccable-card p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="impeccable-header-icon me-3" style="background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); color: #9333ea; box-shadow: 0 4px 12px rgba(147, 51, 234, 0.1); width: 40px; height: 40px; font-size: 1.2rem;">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">AI วิเคราะห์แนวโน้มปัญหา (Smart Tags)</h5>
                            <div class="text-muted small">Top 5 ปัญหาที่พบบ่อยที่สุดจากประวัติการแจ้ง CAR ทั้งหมด</div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($aiTagsTrend as $tag => $count)
                            <div class="d-inline-flex align-items-center bg-light border rounded-pill px-3 py-1 shadow-sm">
                                <span class="fw-bold" style="color: #475569;">#{{ $tag }}</span>
                                <span class="badge bg-secondary rounded-pill ms-2">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="row g-4">
            <!-- Left Column: Pending Actions -->
            <div class="col-lg-6">
                <div class="impeccable-card h-100">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="impeccable-header-icon icon-danger me-3">
                                    <i class="bi bi-exclamation-lg"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark mb-0">สิ่งที่ต้องดำเนินการ</h5>
                                    <div class="text-muted small mt-1">รายการที่ต้องแก้ไข หรือถูกส่งมอบหมายมา</div>
                                </div>
                            </div>
                            <span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm fs-6">{{ $openActions->count() }}</span>
                        </div>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        @forelse($openActions as $action)
                            <div class="task-item mb-2 shadow-sm">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <!-- Left Side: Content -->
                                        <div class="flex-grow-1 pe-2">
                                            <div class="d-flex align-items-center mb-1 flex-wrap gap-2">
                                                <h6 class="fw-bolder text-dark mb-0" style="font-size:1.05rem; letter-spacing: -0.01em;">{{ $action->log->checkpoint->title ?? 'N/A' }}</h6>
                                                <span class="badge {{ $action->status == 'open' ? 'bg-warning text-dark' : ($action->status == 'resolved' ? 'bg-success' : 'bg-primary') }}" style="font-size: 0.7rem; padding: 0.35em 0.65em;">
                                                    {{ ucfirst($action->status) }}
                                                </span>
                                                @if(in_array($action->status, ['open', 'assigned']))
                                                    {{-- Carbon 3 defaults $absolute to false (Carbon 2 defaulted to true), so
                                                         now()->diffInHours($past) is negative and both badges below were dead.
                                                         Diff forward from created_at to get a positive age. --}}
                                                    @php $hoursOpen = $action->created_at->diffInHours(now()); @endphp
                                                    @if($hoursOpen > 24)
                                                        <span class="badge bg-danger animate__animated animate__flash animate__infinite" style="font-size: 0.65rem;"><i class="bi bi-fire"></i> >24h</span>
                                                    @elseif($hoursOpen > 2)
                                                        <span class="badge text-dark" style="background-color: #fd7e14; font-size: 0.65rem;"><i class="bi bi-clock-history"></i> >2h</span>
                                                    @endif
                                                @endif
                                            </div>
                                            
                                            <div class="text-muted small mb-2 d-flex align-items-center flex-wrap gap-2">
                                                <span class="d-inline-flex align-items-center" style="max-width: 100%;">
                                                    <i class="bi bi-geo-alt-fill text-secondary me-1"></i> 
                                                    <span class="text-dark fw-semibold text-truncate" style="max-width: 180px;">
                                                        {{ $action->log->employee->fullname ?? ($action->log->machine->name ?? ($action->log->location->location_name ?? '-')) }}
                                                    </span>
                                                </span>
                                                <span class="text-light-subtle">|</span>
                                                <span class="d-inline-flex align-items-center" style="max-width: 100%;">
                                                    <i class="bi bi-person-circle text-secondary me-1"></i> แจ้ง: 
                                                    <span class="text-truncate ms-1" style="max-width: 120px;">
                                                        {{ $action->escalator->name ?? 'Unknown' }}
                                                    </span>
                                                </span>
                                            </div>
                                            
                                            @if($action->assigned_to)
                                            <div class="d-flex flex-wrap align-items-center gap-3" style="font-size: 0.85rem;">
                                                <span class="text-primary fw-bold d-inline-flex align-items-center" style="max-width: 100%;">
                                                    <i class="bi bi-person-check-fill me-1"></i> รับผิดชอบ: 
                                                    <span class="text-truncate ms-1" style="max-width: 150px;">
                                                        {{ $action->assignee->name ?? 'Unknown' }}
                                                    </span>
                                                </span>
                                                @if($action->due_date)
                                                <span class="{{ $action->due_date->isPast() ? 'text-danger fw-bold' : 'text-muted' }}">
                                                    <i class="bi bi-calendar-event me-1"></i> เสร็จ: {{ $action->due_date->format('d/m/y') }}
                                                </span>
                                                @endif
                                            </div>
                                            @endif
                                        </div>
                                        
                                        <!-- Right Side: Time & Toggle -->
                                        <div class="text-end flex-shrink-0 ms-2">
                                            <div class="text-muted fw-medium mb-2" style="font-size: 0.75rem;"><i class="bi bi-clock me-1"></i>{{ $action->created_at->diffForHumans(null, true, true) }}</div>
                                            <button class="btn btn-sm border px-2 py-1 rounded text-secondary" style="background-color: #f8fafc; font-size: 0.75rem;" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInfo{{ $action->id }}" aria-expanded="false">
                                                <i class="bi bi-list-ul"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Collapsed Info -->
                                    <div class="collapse" id="collapseInfo{{ $action->id }}">
                                        <div class="p-3 mt-2 rounded-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.85rem;">
                                            <div class="d-flex align-items-center mb-1">
                                                <i class="bi bi-search text-danger me-2"></i>
                                                <span class="fw-bold text-slate-700" style="color: #334155;">สิ่งที่พบ (Root Cause)</span>
                                            </div>
                                            <div class="text-danger mb-2 ps-3 border-start border-danger border-2 ms-1 py-1">{{ $action->root_cause ?? $action->log->correction_action ?? '-' }}</div>
                                            
                                            @if($action->log->photo_path)
                                                <div class="mt-2 mb-2 ms-3">
                                                    <img src="/storage/{{ $action->log->photo_path }}" class="rounded-2 img-fluid" style="max-height: 100px; object-fit: cover; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                </div>
                                            @endif
                                            
                                            @if($action->action_taken)
                                                <hr class="my-2 border-secondary opacity-10">
                                                <div class="d-flex align-items-center mb-1">
                                                    <i class="bi bi-tools text-success me-2"></i>
                                                    <span class="fw-bold text-slate-700" style="color: #334155;">การแก้ไข (Action Taken)</span>
                                                </div>
                                                <div class="text-success mb-2 ps-3 border-start border-success border-2 ms-1 py-1">{{ $action->action_taken }}</div>
                                                
                                                @if($action->preventive_action)
                                                    <div class="d-flex align-items-center mb-1 mt-2">
                                                        <i class="bi bi-shield-check text-warning me-2"></i>
                                                        <span class="fw-bold text-slate-700" style="color: #334155;">ป้องกัน (Preventive)</span>
                                                    </div>
                                                    <div class="mb-2 ps-3 border-start border-warning border-2 ms-1 py-1" style="color: #b45309;">{{ $action->preventive_action }}</div>
                                                @endif
                                                
                                                @if($action->proof_image)
                                                    <div class="mt-2 ms-3">
                                                        <img src="/storage/{{ $action->proof_image }}" class="rounded-2 img-fluid" style="max-height: 100px; object-fit: cover; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="d-flex gap-2 mt-3 pt-2 border-top border-light">
                                        @if($action->status == 'resolved')
                                            @if(auth()->user()->id == $action->escalated_by 
                                                || auth()->user()->isAdmin() 
                                                || auth()->user()->isQA()
                                                || auth()->user()->level >= 5)
                                                <a href="{{ route('inspection.verification') }}" class="premium-btn-success btn btn-sm flex-grow-1 px-3 py-1">
                                                    <i class="bi bi-check2-circle me-1"></i> ทวนสอบงาน
                                                </a>
                                            @else
                                                <span class="badge badge-soft-success w-100 py-1 d-flex align-items-center justify-content-center" style="font-size:0.8rem;">
                                                    <i class="bi bi-hourglass-split me-1"></i> รอ QA ตรวจสอบ
                                                </span>
                                            @endif
                                        @else
                                            @if($action->status == 'open' || auth()->user()->level >= 5 || auth()->user()->isAdmin())
                                            <button class="btn btn-sm text-dark px-3 fw-medium" style="background-color: #fef3c7; border: 1px solid #fde68a;" onclick="openAssignModal({{ $action->id }}, '{{ $action->assigned_to ?? '' }}', '{{ $action->due_date ? $action->due_date->format('Y-m-d') : '' }}')">
                                                <i class="bi bi-person-plus text-warning"></i> {{ $action->status == 'assigned' ? 'เปลี่ยนคน' : 'มอบหมาย' }}
                                            </button>
                                            @endif
                                            
                                            @if(($action->status == 'assigned' && $action->assigned_to == auth()->id()) || $action->status == 'open')
                                            <button class="premium-btn-primary btn btn-sm px-3 flex-grow-1" 
                                                data-id="{{ $action->id }}"
                                                data-title="{{ $action->log->checkpoint->title ?? 'N/A' }}"
                                                data-target="{{ $action->log->employee->fullname ?? ($action->log->machine->name ?? ($action->log->location->location_name ?? '-')) }}"
                                                data-cause="{{ $action->root_cause ?? $action->log->correction_action ?? '-' }}"
                                                data-image="{{ $action->log->photo_path ? '/storage/'.$action->log->photo_path : '' }}"
                                                onclick="openResolveModal(this)">
                                                ดำเนินการแก้ไข <i class="bi bi-arrow-right-short ms-1"></i>
                                            </button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5">
                                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                    <i class="bi bi-check-lg fs-1 text-success"></i>
                                </div>
                                <h5 class="fw-bold text-dark">ยอดเยี่ยม! ไม่มีรายการตกค้าง</h5>
                                <p class="text-muted small">คุณเคลียร์งานที่ต้องแก้ไขทั้งหมดเรียบร้อยแล้ว</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Right Column: History -->
            <div class="col-lg-6">
                <div class="impeccable-card h-100">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <div class="d-flex align-items-center">
                            <div class="impeccable-header-icon icon-success me-3">
                                <i class="bi bi-check2-all"></i>
                            </div>
                            <h5 class="fw-bolder text-dark mb-0" style="letter-spacing: -0.02em;">ประวัติที่ดำเนินการแล้ว</h5>
                        </div>
                    </div>
                    <div class="card-body p-0 mt-4">
                        <div class="table-responsive w-100">
                            <table class="table modern-table mb-0 w-100">
                                <thead>
                                    <tr>
                                        <th class="ps-4">รายการ</th>
                                        <th>สาเหตุ & วิธีที่แก้ไข (Root Cause & Action)</th>
                                        <th>แก้ไขโดย</th>
                                        <th class="pe-4 text-center">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($completedActions as $action)
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark">{{ $action->log->checkpoint->title ?? '-' }}</div>
                                                <div class="small text-muted mt-1"><i class="bi bi-geo-alt me-1"></i>{{ $action->log->machine->name ?? ($action->log->location->location_name ?? '-') }}</div>
                                            </td>
                                            <td>
                                                @if($action->root_cause)
                                                    <div class="text-danger fw-medium mb-1" style="font-size: 0.85rem;"><i class="bi bi-search text-danger me-1"></i>สาเหตุ: {{ $action->root_cause }}</div>
                                                @endif
                                                @if($action->action_taken)
                                                    <div class="text-success fw-medium" style="font-size: 0.85rem;"><i class="bi bi-wrench-adjustable text-success me-1"></i>แก้ไข: {{ $action->action_taken }}</div>
                                                    @if($action->preventive_action)
                                                        <div class="mt-2 d-inline-flex align-items-center bg-warning bg-opacity-10 text-warning px-2 py-1 rounded" style="font-size: 0.75rem; font-weight: 500;">
                                                            <i class="bi bi-shield-check me-1"></i> ป้องกัน: {{ $action->preventive_action }}
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="text-muted fst-italic">ไม่ได้บันทึกวิธีแก้ไข</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark">{{ $action->assignee->name ?? $action->escalator->name }}</div>
                                                <div class="small text-muted mt-1">{{ $action->resolved_at ? $action->resolved_at->format('d/m/Y H:i') : '-' }}</div>
                                            </td>
                                            <td class="pe-4 text-center">
                                                @if($action->status === 'closed')
                                                    <span class="badge badge-soft-success d-inline-flex align-items-center mb-2"><i class="bi bi-check-circle-fill me-1"></i>Closed</span>
                                                @else
                                                    <span class="badge badge-soft-success d-inline-flex align-items-center mb-2"><i class="bi bi-check-circle-fill me-1"></i>{{ ucfirst($action->status) }}</span>
                                                @endif
                                                @if($action->proof_image)
                                                    <button type="button" onclick="openCompletedEvidenceModal('{{ Storage::url($action->proof_image) }}')" class="btn btn-light btn-sm rounded-pill w-100 text-secondary border-0" style="font-size:0.75rem; font-weight: 500; background-color: #f1f5f9;">
                                                        <i class="bi bi-image me-1"></i> รูปหลักฐาน
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted border-0">
                                                <i class="bi bi-inbox fs-1 mb-2 d-block text-secondary opacity-50"></i>
                                                ยังไม่มีประวัติการดำเนินการ
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('modals')
    <!-- Assign Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <form action="{{ route('corrective.assign') }}" method="POST">
                    @csrf
                    <input type="hidden" name="action_id" id="assignActionId">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-warning-emphasis">มอบหมายผู้รับผิดชอบ (Assign Task)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                         <div class="mb-3">
                            <label class="form-label fw-bold">เลือกพนักงาน (Assignee):</label>
                            <select name="assigned_to" class="form-select rounded-3 p-2" required>
                                <option value="">-- กรุณาเลือก --</option>
                                @foreach($assignableUsers as $u)
                                    <option value="{{ $u->id }}">
                                        {{ $u->name }} @if($u->job_title) ({{ \Illuminate\Support\Str::limit($u->job_title, 20) }}) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">กำหนดเสร็จภายใน (Due Date):</label>
                            <input type="date" name="due_date" class="form-control rounded-3" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d', strtotime('+1 day')) }}">
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" class="btn btn-warning rounded-pill px-4 w-100">
                            <i class="bi bi-person-check-fill me-2"></i> บันทึกการมอบหมาย
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Resolve Modal -->
    <div class="modal fade" id="resolveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <form action="{{ route('corrective.resolve') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="action_id" id="resolveActionId">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-primary">บันทึกการแก้ไข (Resolution)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <!-- Defect Details -->
                        <div class="bg-light p-3 rounded-3 mb-4 border border-danger border-opacity-25">
                            <h6 class="fw-bold text-danger mb-2" id="modalTitle">Loading...</h6>
                            <div class="small mb-2">
                                <span class="text-muted">ผู้ถูกตรวจ/พื้นที่:</span> 
                                <span class="fw-bold" id="modalTarget">...</span>
                            </div>
                            <div class="small text-muted mb-1">ปัญหาที่พบ (Root Cause):</div>
                            <div class="alert alert-white border text-danger py-2 px-3 mb-2" id="modalCause">
                                ...
                            </div>
                            <!-- Evidence Image Container -->
                            <div id="modalEvidenceContainer" class="d-none mt-2 text-center">
                                <small class="text-secondary d-block mb-1">หลักฐาน (Evidence):</small>
                                <img id="modalEvidenceImg" src="" class="img-fluid rounded border" style="max-height: 150px;">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">การแก้ไขเบื้องต้น (Correction Action):</label>
                            <textarea name="action_taken" class="form-control rounded-3" rows="2" required placeholder="ระบุสิ่งที่ได้ดำเนินการแก้ไขหน้างาน..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">มาตรการป้องกัน (Preventive Action):</label>
                            <textarea name="preventive_action" class="form-control rounded-3 border-warning" rows="2" required placeholder="ระบุวิธีป้องกันไม่ให้เกิดปัญหานี้ซ้ำ..."></textarea>
                            <small class="text-muted">เช่น เปลี่ยนอะไหล่, อบรมพนักงานใหม่, ปรับปรุงตารางทำความสะอาด</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">รูปภาพหลังแก้ไข (Proof Image):</label>
                            <input type="file" name="proof_image" class="form-control" accept="image/*" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" class="btn btn-success rounded-pill px-4 w-100">
                            <i class="bi bi-check-circle-fill me-2"></i> บันทึกเสร็จสิ้น
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Evidence Modal -->
    <div class="modal fade" id="evidenceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 border-0 shadow-lg bg-transparent">
                <div class="modal-header border-0 pb-0 position-absolute top-0 end-0 z-3">
                    <button type="button" class="btn-close btn-close-white bg-dark p-2 m-2 rounded-circle shadow" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 text-center">
                    <img id="evidenceModalImg" src="" class="img-fluid rounded-4 shadow-lg w-100" alt="Evidence Image">
                </div>
            </div>
        </div>
    </div>
    @endpush

    @push('scripts')
    <script>
        function openAssignModal(id, assigneeId = '', dueDate = '') {
            document.getElementById('assignActionId').value = id;
            
            let selectUser = document.querySelector('#assignModal select[name="assigned_to"]');
            if (assigneeId) {
                selectUser.value = assigneeId;
            } else {
                selectUser.value = '';
            }
            
            let inputDate = document.querySelector('#assignModal input[name="due_date"]');
            if (dueDate) {
                inputDate.value = dueDate;
            } else {
                let tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                inputDate.value = tomorrow.toISOString().split('T')[0];
            }

            new bootstrap.Modal(document.getElementById('assignModal')).show();
        }
        
        function openResolveModal(button) {
            // Get data from button attributes
            const id = button.getAttribute('data-id');
            const title = button.getAttribute('data-title');
            const target = button.getAttribute('data-target');
            const cause = button.getAttribute('data-cause');
            const image = button.getAttribute('data-image');

            // Populate Modal
            document.getElementById('resolveActionId').value = id;
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalTarget').textContent = target;
            document.getElementById('modalCause').textContent = cause;
            
            // Handle Evidence Image
            const imgContainer = document.getElementById('modalEvidenceContainer');
            const imgEl = document.getElementById('modalEvidenceImg');
            
            if (image) {
                imgEl.src = image;
                imgContainer.classList.remove('d-none');
            } else {
                imgContainer.classList.add('d-none');
            }

            new bootstrap.Modal(document.getElementById('resolveModal')).show();
        }

        function openCompletedEvidenceModal(imageUrl) {
            document.getElementById('evidenceModalImg').src = imageUrl;
            new bootstrap.Modal(document.getElementById('evidenceModal')).show();
        }
    </script>
    @endpush

</x-app-layout>
