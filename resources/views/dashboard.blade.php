<x-app-layout>
    @section('header', 'แดชบอร์ดสรุปผล (Executive Dashboard)')

    <!-- Welcome Banner Option -->
    <div class="card mb-4">
        <div class="card-body p-4 p-md-5 d-flex align-items-center">
            <div class="me-4 rounded-circle d-flex align-items-center justify-content-center bg-primary text-white" style="width: 60px; height: 60px;">
                <i class="bi bi-person fs-3"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1">สวัสดี, {{ Auth::user()->name }}!</h4>
                <p class="mb-0 text-muted fs-6">
                    <i class="bi bi-calendar3 me-1"></i> {{ \Carbon\Carbon::now()->locale('th')->translatedFormat('j F Y') }}
                    <span class="mx-3 opacity-25">|</span>
                    <i class="bi bi-cloud-sun me-1"></i> กะ: {{ \Carbon\Carbon::now()->format('H') < 12 ? 'เช้า (Morning)' : (\Carbon\Carbon::now()->format('H') < 20 ? 'บ่าย (Afternoon)' : 'กลางคืน (Night)') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small fw-bold text-uppercase">งานตรวจวันนี้</p>
                            <h2 class="fw-bold mb-0 text-dark">{{ $inspectionsToday }}</h2>
                        </div>
                        <div class="text-primary opacity-75">
                            <i class="bi bi-clipboard-check fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small fw-bold text-uppercase">รอทวนสอบ</p>
                            <h2 class="fw-bold mb-0 text-dark">{{ $pendingVerificationCount }}</h2>
                        </div>
                        <div class="text-warning opacity-75">
                            <i class="bi bi-shield-exclamation fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <a href="{{ route('corrective.index') }}" class="text-decoration-none">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1 small fw-bold text-uppercase">สั่งแก้ไขใหม่</p>
                                <h2 class="fw-bold mb-0 text-dark">{{ $recleanCount }}</h2>
                            </div>
                            <div class="text-danger opacity-75">
                                <i class="bi bi-arrow-counterclockwise fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 rounded-4 shadow-sm h-100 custom-card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small fw-bold text-uppercase">อัตราผ่าน</p>
                            <h2 class="fw-bold mb-0 text-dark">{{ $passRate }}%</h2>
                        </div>
                        <div class="text-success opacity-50">
                            <i class="bi bi-graph-up-arrow fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                <table class="table table-borderless align-middle mb-0">
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
                    <a href="{{ route('inspection.verification') }}" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm fw-semibold">ดูทังหมด</a>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover table-borderless align-middle mb-0">
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
                                                    <span class="d-block fw-bold text-dark">{{ $targetName }}</span>
                                                    <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;">{{ $log->checkpoint->title }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($log->result === 'pass')
                                                <span class="fw-semibold text-success small"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: text-top;"></i> ผ่าน</span>
                                            @elseif($log->result === 'fail')
                                                <span class="fw-semibold text-danger small"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: text-top;"></i> ไม่ผ่าน</span>
                                            @else
                                                <span class="fw-semibold text-secondary small"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: text-top;"></i> ไม่มา</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fw-medium text-dark small">{{ $log->session->inspector->name ?? '-' }}</span>
                                        </td>
                                        <td class="text-end pe-3 text-muted small fw-medium">
                                            {{ $log->inspected_at->format('H:i') }} น.
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
                                    stroke-dasharray="339.29" stroke-dashoffset="{{ 339.29 * (1 - ($passRate/100)) }}" 
                                    style="transform: rotate(-90deg); transform-origin: 50% 50%;"></circle>
                        </svg>
                        <div class="position-absolute top-50 start-50 translate-middle">
                            <h3 class="fw-bold mb-0 text-dark">{{ $passRate }}%</h3>
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

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Setup default font for charts
            Chart.defaults.font.family = "'Inter', 'Sarabun', sans-serif";
            Chart.defaults.color = '#64748b';

            // 1. CAR by Department (Bar Chart)
            const deptEl = document.getElementById('carDeptChart');
            if (deptEl) {
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
            if (slaEl) {
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
        });
    </script>
    @endpush
</x-app-layout>
