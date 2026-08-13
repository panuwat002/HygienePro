<x-app-layout>
    @section('header', 'สิ่งที่ต้องแก้ไข')

    <div class="container-fluid">
        <div class="row g-4">
            <!-- Left Column: Pending Actions -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="fw-bold text-danger mb-0"><i class="bi bi-exclamation-octagon me-2"></i>สิ่งที่ต้องดำเนินการ</h5>
                                <div class="text-muted small mt-1">รายการที่ต้องแก้ไข หรือถูกส่งมอบหมายมา</div>
                            </div>
                            <span class="badge bg-danger rounded-pill">{{ $openActions->count() }}</span>
                        </div>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        @forelse($openActions as $action)
                            <div class="card mb-3 border-0 shadow-sm rounded-4">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="badge {{ $action->status == 'open' ? 'bg-warning text-dark' : ($action->status == 'resolved' ? 'bg-success' : 'bg-primary') }}">
                                                {{ ucfirst($action->status) }}
                                            </span>
                                            @if(in_array($action->status, ['open', 'assigned']))
                                                @php
                                                    $hoursOpen = now()->diffInHours($action->created_at);
                                                @endphp
                                                @if($hoursOpen > 24)
                                                    <span class="badge bg-danger ms-1 animate__animated animate__flash animate__infinite"><i class="bi bi-fire"></i> >24h</span>
                                                @elseif($hoursOpen > 2)
                                                    <span class="badge text-dark ms-1" style="background-color: #fd7e14;"><i class="bi bi-clock-history"></i> >2h</span>
                                                @endif
                                            @endif
                                        </div>
                                        <small class="text-muted"><i class="bi bi-clock me-1"></i>{{ $action->created_at->diffForHumans() }}</small>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="fw-bold text-dark mb-0 pe-2" style="font-size:1.1rem;">{{ $action->log->checkpoint->title ?? 'N/A' }}</h5>
                                        <button class="btn btn-sm btn-light border text-muted px-3 py-1 rounded-pill flex-shrink-0" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInfo{{ $action->id }}" aria-expanded="false">
                                            <i class="bi bi-arrows-expand"></i> ย่อ/ขยาย
                                        </button>
                                    </div>
                                    
                                    <div class="mb-2 text-muted" style="font-size:0.9rem;">
                                        <i class="bi bi-geo-alt-fill me-1"></i> ผู้ถูกตรวจ/พื้นที่:
                                        <span class="text-dark fw-bold">
                                            {{ $action->log->employee->fullname ?? ($action->log->machine->name ?? ($action->log->location->location_name ?? '-')) }}
                                        </span>
                                    </div>
                                    
                                    <div class="collapse" id="collapseInfo{{ $action->id }}">
                                        <div class="p-3 bg-light rounded-3 border-0 mb-3 mt-3">
                                            <small class="text-secondary fw-bold d-block mb-1"><i class="bi bi-search me-1"></i> สิ่งที่พบ (Root Cause)</small>
                                            <div class="text-danger mb-2">{{ $action->root_cause ?? $action->log->correction_action ?? '-' }}</div>
                                            
                                            @if($action->log->photo_path)
                                                <div class="mt-2 mb-3">
                                                    <small class="text-secondary d-block mb-1"><i class="bi bi-camera me-1"></i> ภาพหลักฐานตอนตรวจพบ:</small>
                                                    <img src="/storage/{{ $action->log->photo_path }}" class="rounded-3 img-fluid shadow-sm" style="max-height: 160px; object-fit: cover;">
                                                </div>
                                            @endif
                                            
                                            @if($action->action_taken)
                                                <hr class="my-3 border-secondary opacity-25">
                                                <small class="text-secondary fw-bold d-block mb-1"><i class="bi bi-tools me-1"></i> การแก้ไข (Action Taken)</small>
                                                <div class="text-success mb-2">{{ $action->action_taken }}</div>
                                                
                                                @if($action->preventive_action)
                                                    <small class="text-secondary fw-bold d-block mb-1 mt-2"><i class="bi bi-shield-check me-1"></i> มาตรการป้องกัน (Preventive Action)</small>
                                                    <div class="text-warning-emphasis mb-2">{{ $action->preventive_action }}</div>
                                                @endif
                                                
                                                @if($action->proof_image)
                                                    <div class="mt-2">
                                                        <small class="text-secondary d-block mb-1"><i class="bi bi-camera me-1"></i> ภาพหลักฐานการแก้ไข:</small>
                                                        <img src="/storage/{{ $action->proof_image }}" class="rounded-3 img-fluid shadow-sm" style="max-height: 160px; object-fit: cover;">
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-3 mt-3 pt-3 border-top">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center small text-muted mb-1">
                                                <i class="bi bi-person-circle me-2"></i> แจ้งโดย: {{ $action->escalator->name ?? 'Unknown' }}
                                            </div>
                                            @if($action->assigned_to)
                                            <div class="d-flex align-items-center small text-primary fw-bold">
                                                <i class="bi bi-person-check-fill me-2"></i> รับผิดชอบ: {{ $action->assignee->name ?? 'Unknown' }}
                                            </div>
                                            @if($action->due_date)
                                            <div class="d-flex align-items-center small mt-1 {{ $action->due_date->isPast() ? 'text-danger fw-bold' : 'text-muted' }}">
                                                <i class="bi bi-calendar-event me-2"></i> กำหนดเสร็จ: {{ $action->due_date->format('d/m/Y') }}
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
                                                <a href="{{ route('inspection.verification') }}" class="btn btn-outline-success rounded-pill px-4 shadow-sm w-100">
                                                    <i class="bi bi-box-arrow-up-right me-1"></i> ไปหน้าทวนสอบ (Verify)
                                                </a>
                                            @else
                                                <span class="badge bg-light text-success border border-success p-2 w-100 w-sm-auto text-center">
                                                    <i class="bi bi-hourglass-split"></i> รอ QA ตรวจสอบ
                                                </span>
                                            @endif
                                        @else
                                            <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-sm-auto">
                                                {{-- Assign/Re-assign Button:
                                                     Visible to:
                                                     1. Managers (Level >= 5) & Admins -> Can always assign/re-assign
                                                     2. Anyone -> If status is 'Open' (Can claim or assign)
                                                     
                                                     Hidden for:
                                                     - Supervisors/Staff if status is already 'Assigned' (Cannot re-assign assigned tasks)
                                                --}}
                                                @if($action->status == 'open' || auth()->user()->level >= 5 || auth()->user()->isAdmin())
                                                <button class="btn btn-warning rounded-pill px-3 shadow-sm text-dark flex-fill" onclick="openAssignModal({{ $action->id }}, '{{ $action->assigned_to ?? '' }}', '{{ $action->due_date ? $action->due_date->format('Y-m-d') : '' }}')">
                                                    <i class="bi bi-person-plus-fill me-1"></i> {{ $action->status == 'assigned' ? 'เปลี่ยนคน' : 'มอบหมาย' }}
                                                </button>
                                                @endif
                                                
                                                {{-- Resolve Button --}}
                                                
                                                @if($action->status == 'assigned' && $action->assigned_to == auth()->id())
                                                <button class="btn btn-primary rounded-pill px-3 shadow-sm flex-fill" 
                                                    data-id="{{ $action->id }}"
                                                    data-title="{{ $action->log->checkpoint->title ?? 'N/A' }}"
                                                    data-target="{{ $action->log->employee->fullname ?? ($action->log->machine->name ?? ($action->log->location->location_name ?? '-')) }}"
                                                    data-cause="{{ $action->root_cause ?? $action->log->correction_action ?? '-' }}"
                                                    data-image="{{ $action->log->photo_path ? '/storage/'.$action->log->photo_path : '' }}"
                                                    onclick="openResolveModal(this)">
                                                    <i class="bi bi-tools me-1"></i> ดำเนินการ
                                                </button>
                                                @elseif($action->status == 'open')
                                                {{-- Allow resolve directly if open --}}
                                                <button class="btn btn-primary rounded-pill px-3 shadow-sm flex-fill" 
                                                    data-id="{{ $action->id }}"
                                                    data-title="{{ $action->log->checkpoint->title ?? 'N/A' }}"
                                                    data-target="{{ $action->log->employee->fullname ?? ($action->log->machine->name ?? ($action->log->location->location_name ?? '-')) }}"
                                                    data-cause="{{ $action->root_cause ?? $action->log->correction_action ?? '-' }}"
                                                    data-image="{{ $action->log->photo_path ? '/storage/'.$action->log->photo_path : '' }}"
                                                    onclick="openResolveModal(this)">
                                                    <i class="bi bi-tools me-1"></i> ดำเนินการ
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
                         <h5 class="fw-bold text-success mb-0"><i class="bi bi-check-all me-2"></i>ประวัติที่ดำเนินการแล้ว</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-modern table-responsive w-100">
                            <table class="table table-hover align-middle mb-0 text-nowrap">
                                <thead>
                                    <tr>
                                        <th class="ps-4">รายการ</th>
                                        <th>วิธีที่แก้ไข (Action Taken)</th>
                                        <th>แก้ไขโดย</th>
                                        <th>วันที่</th>
                                        <th class="pe-4 text-center">สถานะ / หลักฐาน</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($completedActions as $action)
                                        <tr>
                                            <td class="ps-4" data-label="รายการ">
                                                <div class="fw-bold small">{{ $action->log->checkpoint->title ?? '-' }}</div>
                                                <div class="x-small text-muted">{{ $action->log->machine->name ?? ($action->log->location->location_name ?? '-') }}</div>
                                                @if($action->root_cause)
                                                    <div class="x-small mt-1 text-muted text-decoration-line-through"><i class="bi bi-bug me-1"></i>สาเหตุ: {{ $action->root_cause }}</div>
                                                @endif
                                            </td>
                                            <td class="small" data-label="วิธีที่แก้ไข">
                                                @if($action->action_taken)
                                                    <span class="text-dark d-block">{{ $action->action_taken }}</span>
                                                    @if($action->preventive_action)
                                                        <div class="x-small text-muted mt-1"><i class="bi bi-shield-check me-1"></i>ป้องกัน: {{ $action->preventive_action }}</div>
                                                    @endif
                                                @else
                                                    <span class="text-muted fst-italic">ไม่ได้บันทึกวิธีแก้ไข</span>
                                                @endif
                                            </td>
                                            <td class="small" data-label="แก้ไขโดย">{{ $action->assignee->name ?? $action->escalator->name }}</td>
                                            <td class="small" data-label="วันที่">
                                                {{ $action->resolved_at ? $action->resolved_at->format('d/m/Y') : '-' }}
                                                @if($action->resolved_at)
                                                    <div class="x-small text-muted mt-1"><i class="bi bi-clock me-1"></i>{{ $action->resolved_at->format('H:i') }} น.</div>
                                                @endif
                                            </td>
                                            <td class="pe-4 text-center" data-label="สถานะ">
                                                @if($action->status === 'closed')
                                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 d-block mb-1"><i class="bi bi-check-circle-fill me-1"></i>Closed</span>
                                                @else
                                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 d-block mb-1"><i class="bi bi-check-circle-fill me-1"></i>{{ ucfirst($action->status) }}</span>
                                                @endif
                                                @if($action->proof_image)
                                                    <button type="button" onclick="openCompletedEvidenceModal('{{ Storage::url($action->proof_image) }}')" class="btn btn-outline-success btn-sm rounded-pill px-2 py-0 btn-evidence-hover w-100 mt-1" style="font-size:0.7rem;">
                                                        <i class="bi bi-images me-1"></i>ดูรูปหลักฐาน
                                                    </button>
                                                @else
                                                    <span class="text-muted x-small d-block mt-1">ไม่มีรูปหลักฐาน</span>
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
