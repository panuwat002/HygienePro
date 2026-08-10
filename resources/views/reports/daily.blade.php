<x-app-layout>
    @section('header', 'ผลการตรวจสอบรายวัน')

    <div class="py-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold text-primary">
                        <i class="bi bi-file-text me-2"></i> รายงานวันที่: {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
                    </h5>
                    <small class="text-muted">
                        @if($departmentId) แผนก ID: {{ $departmentId }} | @endif
                        @if($shift) กะ: {{ ucfirst($shift) }} @endif
                    </small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
                    </a>
                    <a href="{{ route('reports.export.pdf', array_merge(['date' => $date, 'department_id' => $departmentId, 'shift' => $shift], request()->only(['report_type', 'machine_id', 'orientation']))) }}" 
                       id="exportPdfBtn" 
                       data-base-url="{{ route('reports.export.pdf', array_merge(['date' => $date, 'department_id' => $departmentId, 'shift' => $shift], request()->only(['report_type', 'machine_id', 'orientation']))) }}"
                       target="_blank" 
                       class="btn btn-danger">
                        <i class="bi bi-file-pdf me-1"></i> Export PDF
                    </a>
                </div>
            </div>
            
            <div class="card-body p-0">
                @if($sessions->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-emoji-frown display-4 mb-3 d-block"></i>
                        <p>ไม่พบข้อมูลการตรวจสอบตามเงื่อนไขที่เลือก</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4 py-3" style="width: 50px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAllSessions">
                                        </div>
                                    </th>
                                    <th class="py-3">ข้อมูลการตรวจ</th>
                                    <th class="py-3">ผู้ตรวจสอบ</th>
                                    <th class="py-3 text-center">สถานะ</th>
                                    <th class="py-3">ปัญหาที่พบ (Fail)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sessions as $session)
                                    <tr>
                                        <td class="px-4">
                                            <div class="form-check">
                                                <input class="form-check-input session-checkbox" type="checkbox" value="{{ $session->id }}" id="session-{{ $session->id }}">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold">{{ $session->department->dept_name ?? 'N/A' }}</div>
                                            <div class="small text-muted">
                                                <i class="bi bi-clock"></i> กะ: {{ ucfirst($session->shift) }}
                                            </div>
                                            <div class="small text-muted" style="font-size: 0.75rem;">ID: {{ $session->id }}</div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-2 border" style="width: 32px; height: 32px;">
                                                    <i class="bi bi-person text-secondary"></i>
                                                </div>
                                                <span>{{ $session->inspector->name ?? 'Unknown' }}</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $statusColors = [
                                                    'draft' => 'bg-secondary',
                                                    'in_progress' => 'bg-info text-dark',
                                                    'completed' => 'bg-success',
                                                    'approved' => 'bg-primary',
                                                    'rejected' => 'bg-danger'
                                                ];
                                                $bgInfo = $statusColors[$session->status] ?? 'bg-secondary';
                                            @endphp
                                            <span class="badge {{ $bgInfo }} rounded-pill px-3 mb-2">
                                                {{ ucfirst($session->status) }}
                                            </span>
                                            <br>
                                            <button class="btn btn-sm btn-link text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#details-{{ $session->id }}">
                                                <i class="bi bi-eye"></i> ดูรายละเอียด
                                            </button>
                                        </td>
                                        <td>
                                            @php
                                                $failedLogs = $session->logs->where('result', 'fail');
                                                $fixedLogs = $session->logs->whereNotNull('parent_id');
                                                $passCount = $session->logs->where('result', 'pass')->count();
                                                $totalCount = $session->logs->count();
                                            @endphp
                                            @if($failedLogs->count() > 0)
                                                <div class="text-danger small fw-bold mb-1">
                                                    <i class="bi bi-exclamation-circle-fill me-1"></i> พบปัญหา {{ $failedLogs->count() }} รายการ
                                                </div>
                                                <ul class="mb-0 ps-3 text-danger small">
                                                    @foreach($failedLogs->take(3) as $log)
                                                        <li class="small mb-1">
                                                            <span class="text-danger">&bull;</span> {{ $log->checkpoint_title_snapshot ?? ($log->checkpoint->title ?? 'ไม่ระบุ') }}
                                                            @if($log->checkpoint && $log->checkpoint->category)
                                                                <span class="text-muted" style="font-size: 0.7rem;">({{ $log->checkpoint->category->name }})</span>
                                                            @endif
                                                            @if($log->note) <br><span class="text-muted fst-italic">- {{ $log->note }}</span> @endif
                                                        </li>
                                                    @endforeach
                                                    @if($failedLogs->count() > 3)
                                                        <li class="list-unstyled fst-italic text-muted">+ อีก {{ $failedLogs->count() - 3 }} รายการ (กดดูรายละเอียด)</li>
                                                    @endif
                                                </ul>
                                            @endif
                                            
                                            @if($fixedLogs->count() > 0)
                                                <div class="text-success small fw-bold mt-2 mb-1">
                                                    <i class="bi bi-tools me-1"></i> ดำเนินการแก้ไขแล้ว {{ $fixedLogs->count() }} รายการ
                                                </div>
                                                <ul class="mb-0 ps-3 text-success small">
                                                    @foreach($fixedLogs->take(3) as $log)
                                                        <li class="small mb-1">
                                                            <span class="text-success">&bull;</span> {{ $log->checkpoint_title_snapshot ?? ($log->checkpoint->title ?? 'ไม่ระบุ') }}
                                                            @if($log->correction_action) <br><span class="text-muted fst-italic text-success">- แก้ไข: {{ $log->correction_action }}</span> @endif
                                                        </li>
                                                    @endforeach
                                                    @if($fixedLogs->count() > 3)
                                                        <li class="list-unstyled fst-italic text-muted">+ อีก {{ $fixedLogs->count() - 3 }} รายการ</li>
                                                    @endif
                                                </ul>
                                            @endif
                                            
                                            @if($failedLogs->count() == 0 && $fixedLogs->count() == 0)
                                                <div class="text-success small fw-bold">
                                                    <i class="bi bi-check-circle-fill me-1"></i> ผ่านทั้งหมด ({{ $totalCount }} รายการ)
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                     <!-- Detailed Rows (Collapsible) -->
                                    <tr class="collapse bg-light" id="details-{{ $session->id }}">
                                        <td colspan="5" class="p-3">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-body p-0">
                                                    @php
                                                        // Group logs by their target to get total inspected entities
                                                        $groupedLogs = $session->logs->groupBy(function ($log) {
                                                            if ($log->employee_id) return 'emp_' . $log->employee_id;
                                                            if ($log->machine_id) return 'mac_' . $log->machine_id;
                                                            if ($log->location_id) return 'loc_' . $log->location_id;
                                                            return 'other';
                                                        });
                                                        
                                                        $totalInspected = $groupedLogs->count();
                                                        $failedTargets = 0;
                                                        
                                                        foreach($groupedLogs as $group) {
                                                            if($group->where('result', 'fail')->count() > 0) {
                                                                $failedTargets++;
                                                            }
                                                        }
                                                        
                                                        $passedTargets = $totalInspected - $failedTargets;
                                                    @endphp
                                                    <div class="d-flex justify-content-around align-items-center py-4 bg-light rounded">
                                                        <div class="text-center">
                                                            <div class="text-muted small fw-bold mb-1">ยอดรวมที่ตรวจ (Total)</div>
                                                            <div class="display-6 fw-bold text-primary">{{ $totalInspected }}</div>
                                                            <div class="small text-muted mt-1">คน / จุด / เครื่อง</div>
                                                        </div>
                                                        <div class="border-end h-75 mx-3"></div>
                                                        <div class="text-center">
                                                            <div class="text-muted small fw-bold mb-1">ผ่าน (Pass)</div>
                                                            <div class="display-6 fw-bold text-success">{{ $passedTargets }}</div>
                                                            <div class="small text-muted mt-1">สมบูรณ์</div>
                                                        </div>
                                                        <div class="border-end h-75 mx-3"></div>
                                                        <div class="text-center">
                                                            <div class="text-muted small fw-bold mb-1">ไม่ผ่าน (Fail)</div>
                                                            <div class="display-6 fw-bold text-danger">{{ $failedTargets }}</div>
                                                            <div class="small text-muted mt-1">พบปัญหา</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAllSessions');
            const sessionCheckboxes = document.querySelectorAll('.session-checkbox');
            const exportPdfBtn = document.getElementById('exportPdfBtn');
            const baseUrl = exportPdfBtn.getAttribute('data-base-url');

            function updateExportLink() {
                const selectedIds = Array.from(sessionCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);

                if (selectedIds.length > 0) {
                    const separator = baseUrl.includes('?') ? '&' : '?';
                    exportPdfBtn.href = baseUrl + separator + 'session_ids=' + selectedIds.join(',');
                } else {
                    exportPdfBtn.href = baseUrl;
                }
            }

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    sessionCheckboxes.forEach(cb => {
                        cb.checked = this.checked;
                    });
                    updateExportLink();
                });
            }

            sessionCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    if (!this.checked && selectAllCheckbox) {
                        selectAllCheckbox.checked = false;
                    } else if (selectAllCheckbox) {
                        const allChecked = Array.from(sessionCheckboxes).every(c => c.checked);
                        selectAllCheckbox.checked = allChecked;
                    }
                    updateExportLink();
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
