<x-app-layout>
    @section('header', 'สิ่งที่ต้องแก้ไข (Corrective Actions)')

    <div class="container-fluid">
        <div class="row g-4">
            <!-- Left Column: Pending Actions -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="fw-bold text-danger mb-0"><i class="bi bi-exclamation-octagon me-2"></i>สิ่งที่ต้องดำเนินการ (Outstanding)</h5>
                                <div class="text-muted small mt-1">รายการที่ต้องแก้ไข หรือถูกส่งมอบหมายมา</div>
                            </div>
                            <span class="badge bg-danger rounded-pill">{{ $openActions->count() }}</span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        @forelse($openActions as $action)
                            <div class="card mb-3 border bg-light">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <span class="badge {{ $action->status == 'open' ? 'bg-warning text-dark' : ($action->status == 'resolved' ? 'bg-success' : 'bg-primary') }} mb-2">
                                            {{ ucfirst($action->status) }}
                                        </span>
                                        <small class="text-muted">{{ $action->created_at->diffForHumans() }}</small>
                                    </div>
                                    <h6 class="fw-bold">{{ $action->log->checkpoint->title ?? 'N/A' }}</h6>
                                    <div class="small mb-2">
                                        <strong class="text-secondary">ผู้ถูกตรวจ/พื้นที่:</strong> 
                                        <span class="text-dark fw-bold">
                                            {{ $action->log->employee->fullname ?? ($action->log->machine->name ?? ($action->log->location->location_name ?? '-')) }}
                                        </span>
                                    </div>
                                    <div class="p-2 bg-white rounded border mb-3">
                                        <small class="text-muted d-block">สิ่งที่พบ (Root Cause):</small>
                                        <div class="text-danger">{{ $action->root_cause ?? $action->log->correction_action ?? '-' }}</div>
                                        
                                        @if($action->log->photo_path)
                                            <div class="mt-2 mb-3 text-center bg-light p-2 rounded">
                                                <small class="text-muted d-block mb-1">ภาพหลักฐานตอนตรวจพบ:</small>
                                                <img src="/storage/{{ $action->log->photo_path }}" class="rounded img-fluid border" style="max-height: 150px;">
                                            </div>
                                        @endif
                                        
                                        @if($action->action_taken)
                                            <hr class="my-2">
                                            <small class="text-muted d-block">การแก้ไข (Action):</small>
                                            <div class="text-success">{{ $action->action_taken }}</div>
                                            @if($action->proof_image)
                                                <div class="mt-2 text-center">
                                                    <img src="/storage/{{ $action->proof_image }}" class="rounded img-fluid" style="max-height: 150px;">
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="d-flex align-items-center small text-muted mb-1">
                                                <i class="bi bi-person-circle me-1"></i> Escalated by {{ $action->escalator->name ?? 'Unknown' }}
                                            </div>
                                            @if($action->assigned_to)
                                            <div class="d-flex align-items-center small text-primary fw-bold">
                                                <i class="bi bi-person-check-fill me-1"></i> Assigned to: {{ $action->assignee->name ?? 'Unknown' }}
                                            </div>
                                            @if($action->due_date)
                                            <div class="d-flex align-items-center small mt-1 {{ $action->due_date->isPast() ? 'text-danger fw-bold' : 'text-muted' }}">
                                                <i class="bi bi-calendar-event me-1"></i> Due: {{ $action->due_date->format('d/m/Y') }}
                                            </div>
                                            @endif
                                            @endif
                                        </div>
                                        <!-- Actions -->
                                        @if($action->status == 'resolved')
                                            {{-- Close Button Visibility:
                                                 1. Escalator (Creator)
                                                 2. Administration (Admin)
                                                 3. QA Department
                                                 4. Managers (Level >= 5) -> Can close tickets in their dept
                                            --}}
                                            @if(auth()->user()->id == $action->escalated_by 
                                                || auth()->user()->isAdmin() 
                                                || auth()->user()->isQA()
                                                || auth()->user()->level >= 5)
                                                <form action="{{ route('corrective.close') }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="action_id" value="{{ $action->id }}">
                                                    <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm">
                                                        <i class="bi bi-check-double me-1"></i> ปิดงาน (Close)
                                                    </button>
                                                </form>
                                            @else
                                                <span class="badge bg-light text-success border border-success">
                                                    <i class="bi bi-hourglass-split"></i> รอ QA ตรวจสอบ
                                                </span>
                                            @endif
                                        @else
                                            <div class="d-flex gap-2">
                                                {{-- Assign/Re-assign Button:
                                                     Visible to:
                                                     1. Managers (Level >= 5) & Admins -> Can always assign/re-assign
                                                     2. Anyone -> If status is 'Open' (Can claim or assign)
                                                     
                                                     Hidden for:
                                                     - Supervisors/Staff if status is already 'Assigned' (Cannot re-assign assigned tasks)
                                                --}}
                                                @if($action->status == 'open' || auth()->user()->level >= 5 || auth()->user()->isAdmin())
                                                <button class="btn btn-warning btn-sm rounded-pill px-3 shadow-sm text-dark" onclick="openAssignModal({{ $action->id }}, '{{ $action->assigned_to ?? '' }}', '{{ $action->due_date ? $action->due_date->format('Y-m-d') : '' }}')">
                                                    <i class="bi bi-person-plus-fill me-1"></i> {{ $action->status == 'assigned' ? 'เปลี่ยนผู้รับผิดชอบ' : 'มอบหมาย (Assign)' }}
                                                </button>
                                                @endif
                                                
                                                {{-- Resolve Button --}}
                                                
                                                @if($action->status == 'assigned' && $action->assigned_to == auth()->id())
                                                <button class="btn btn-primary btn-sm rounded-pill px-3" 
                                                    data-id="{{ $action->id }}"
                                                    data-title="{{ $action->log->checkpoint->title ?? 'N/A' }}"
                                                    data-target="{{ $action->log->employee->fullname ?? ($action->log->machine->name ?? ($action->log->location->location_name ?? '-')) }}"
                                                    data-cause="{{ $action->root_cause ?? $action->log->correction_action ?? '-' }}"
                                                    data-image="{{ $action->log->photo_path ? '/storage/'.$action->log->photo_path : '' }}"
                                                    onclick="openResolveModal(this)">
                                                    <i class="bi bi-tools me-1"></i> ดำเนินการแก้ไข
                                                </button>
                                                @elseif($action->status == 'open')
                                                {{-- Allow resolve directly if open --}}
                                                <button class="btn btn-primary btn-sm rounded-pill px-3" 
                                                    data-id="{{ $action->id }}"
                                                    data-title="{{ $action->log->checkpoint->title ?? 'N/A' }}"
                                                    data-target="{{ $action->log->employee->fullname ?? ($action->log->machine->name ?? ($action->log->location->location_name ?? '-')) }}"
                                                    data-cause="{{ $action->root_cause ?? $action->log->correction_action ?? '-' }}"
                                                    data-image="{{ $action->log->photo_path ? '/storage/'.$action->log->photo_path : '' }}"
                                                    onclick="openResolveModal(this)">
                                                    <i class="bi bi-tools me-1"></i> ดำเนินการแก้ไข
                                                </button>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle fs-1 mb-2 d-block text-success opacity-50"></i>
                                ไม่มีรายการที่ต้องแก้ไข
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Right Column: History -->
            <div class="col-lg-6">
                {{-- Same History Code --}}
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                         <h5 class="fw-bold text-success mb-0"><i class="bi bi-check-all me-2"></i>ประวัติที่ดำเนินการแล้ว (Completed)</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-modern">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">รายการ</th>
                                        <th>แก้ไขโดย</th>
                                        <th>วันที่</th>
                                        <th class="pe-4 text-center">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($completedActions as $action)
                                        <tr>
                                            <td class="ps-4" data-label="รายการ">
                                                <div class="fw-bold small">{{ $action->log->checkpoint->title ?? '-' }}</div>
                                                <div class="x-small text-muted">{{ $action->log->machine->name ?? '-' }}</div>
                                            </td>
                                            <td class="small" data-label="แก้ไขโดย">{{ $action->assignee->name ?? $action->escalator->name }}</td>
                                            <td class="small" data-label="วันที่">{{ $action->resolved_at ? $action->resolved_at->format('d/m/Y') : '-' }}</td>
                                            <td class="pe-4 text-center" data-label="สถานะ">
                                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">Resolved</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                            <select name="user_id" class="form-select rounded-3 p-2" required>
                                <option value="">-- กรุณาเลือก --</option>
                                @foreach($assignableUsers as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->job_title ?? $u->email }})</option>
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
                            <label class="form-label fw-bold">การดำเนินการแก้ไข (Action Taken):</label>
                            <textarea name="action_taken" class="form-control rounded-3" rows="3" required placeholder="ระบุสิ่งที่ได้ดำเนินการแก้ไข..."></textarea>
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

    @push('scripts')
    <script>
        function openAssignModal(id, assigneeId = '', dueDate = '') {
            document.getElementById('assignActionId').value = id;
            
            let selectUser = document.querySelector('#assignModal select[name="user_id"]');
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
    </script>
    @endpush

</x-app-layout>
