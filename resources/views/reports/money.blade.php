<x-app-layout>
    @section('header', 'รายงานมูลค่าความเสียหาย (Money Report)')

    <div class="py-4">
        <div class="container-fluid">
            <!-- Filter Header -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                <div class="card-body p-4">
                    <form action="{{ route('reports.money') }}" method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted small">ประจำเดือน (Month)</label>
                            <input type="month" name="month" value="{{ $month }}" class="form-control form-control-lg rounded-3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted small">แผนก (Department)</label>
                            <select name="department_id" class="form-select form-select-lg rounded-3">
                                <option value="">-- รวมทุกแผนก (All Departments) --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ $departmentId == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->dept_code }} - {{ $dept->dept_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-4 flex-grow-1 shadow-sm">
                                <i class="bi bi-search me-1"></i> ค้นหา
                            </button>
                            <a href="{{ route('reports.money.pdf', ['month' => $month, 'department_id' => $departmentId]) }}" target="_blank" class="btn btn-danger btn-lg rounded-pill px-4 shadow-sm">
                                <i class="bi bi-file-pdf me-1"></i> PDF
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 bg-danger bg-opacity-10 border border-danger border-opacity-25 h-100">
                        <div class="card-body p-4 d-flex align-items-center">
                            <div class="bg-danger text-white rounded-circle p-3 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px;">
                                <i class="bi bi-currency-dollar fs-2"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-bold text-uppercase d-block mb-1">มูลค่าความสูญเสียประเมินรวม (Total Financial Loss)</small>
                                <h2 class="fw-bold text-danger mb-0">฿{{ number_format($totalLoss, 2) }}</h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 bg-warning bg-opacity-10 border border-warning border-opacity-25 h-100">
                        <div class="card-body p-4 d-flex align-items-center">
                            <div class="bg-warning text-dark rounded-circle p-3 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px;">
                                <i class="bi bi-exclamation-triangle-fill fs-2"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-bold text-uppercase d-block mb-1">จำนวนจุดบกพร่องที่เกิดความเสียหาย (Failed Defects)</small>
                                <h2 class="fw-bold text-dark mb-0">{{ number_format($logs->count()) }} รายการ</h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 bg-info bg-opacity-10 border border-info border-opacity-25 h-100">
                        <div class="card-body p-4 d-flex align-items-center">
                            <div class="bg-info text-white rounded-circle p-3 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px;">
                                <i class="bi bi-pie-chart-fill fs-2"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-bold text-uppercase d-block mb-1">เฉลี่ยต่อรายการ (Avg Loss per Defect)</small>
                                <h2 class="fw-bold text-info mb-0">฿{{ $logs->count() > 0 ? number_format($totalLoss / $logs->count(), 2) : '0.00' }}</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department Breakdowns & Data Table -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white py-3 border-0">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-building me-2 text-primary"></i>มูลค่าความเสียหายแยกตามแผนก</h6>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush rounded-bottom-4">
                                @forelse($deptLosses as $dName => $amt)
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-4 py-3">
                                        <span class="fw-bold text-dark">{{ $dName }}</span>
                                        <span class="badge bg-danger bg-opacity-10 text-danger fs-6 px-3 py-2 rounded-pill">฿{{ number_format($amt, 2) }}</span>
                                    </li>
                                @empty
                                    <li class="list-group-item text-center text-muted py-4">ไม่มีข้อมูลความเสียหาย</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white py-3 border-0">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-tags me-2 text-primary"></i>มูลค่าความเสียหายแยกตามหมวดปัญหา</h6>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush rounded-bottom-4">
                                @forelse($categoryLosses as $catName => $amt)
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-4 py-3">
                                        <span class="fw-bold text-dark">{{ $catName }}</span>
                                        <span class="badge bg-danger bg-opacity-10 text-danger fs-6 px-3 py-2 rounded-pill">฿{{ number_format($amt, 2) }}</span>
                                    </li>
                                @empty
                                    <li class="list-group-item text-center text-muted py-4">ไม่มีข้อมูลความเสียหาย</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail Logs Table -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-check me-2 text-primary"></i>รายละเอียดรายการความเสียหาย</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">วัน-เวลา</th>
                                <th>แผนก</th>
                                <th>เป้าหมาย</th>
                                <th>หัวข้อที่ตก (Defect)</th>
                                <th>หมวดหมู่</th>
                                <th class="text-end pe-4">มูลค่าความเสียหาย (THB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                @php
                                    $target = $log->employee ? $log->employee->fullname : ($log->machine ? $log->machine->name : ($log->location ? $log->location->location_name : '-'));
                                    $lossAmt = ($log->correctiveAction && $log->correctiveAction->financial_loss > 0)
                                        ? (float) $log->correctiveAction->financial_loss
                                        : ((float) ($log->checkpoint->default_cost_impact ?? 500));
                                @endphp
                                <tr>
                                    <td class="ps-4 small text-muted">{{ $log->inspected_at ? $log->inspected_at->format('d/m/Y H:i') : '-' }}</td>
                                    <td class="fw-bold text-dark">{{ $log->session->department->dept_name ?? '-' }}</td>
                                    <td>{{ $target }}</td>
                                    <td class="text-danger fw-bold">{{ $log->checkpoint->title ?? '-' }}</td>
                                    <td><span class="badge bg-light text-secondary border">{{ $log->checkpoint->category->name ?? 'ทั่วไป' }}</span></td>
                                    <td class="text-end pe-4 fw-bold text-danger">฿{{ number_format($lossAmt, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">ไม่พบข้อมูลความเสียหายตามเงื่อนไขที่เลือก</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
