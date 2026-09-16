<x-app-layout>
    @section('header', 'รายงานและวิเคราะห์ผล')

    <style>
        .report-hero-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f766e 100%);
            border-radius: 1.25rem;
            box-shadow: 0 15px 30px -10px rgba(15, 23, 42, 0.3);
            position: relative;
            overflow: hidden;
        }
        .report-hero-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(20, 184, 166, 0.25) 0%, transparent 70%);
            pointer-events: none;
        }
        .glass-card {
            background: #ffffff;
            border-radius: 1rem;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.04), 0 2px 6px -2px rgba(0, 0, 0, 0.02);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px -5px rgba(0, 0, 0, 0.08), 0 4px 10px -2px rgba(0, 0, 0, 0.04);
            border-color: rgba(20, 184, 166, 0.3);
        }
        .icon-badge {
            width: 48px;
            height: 48px;
            border-radius: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .form-control-custom, .form-select-custom {
            border-radius: 0.65rem;
            border: 1px solid #cbd5e1;
            padding: 0.55rem 0.85rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background-color: #f8fafc;
        }
        .form-control-custom:focus, .form-select-custom:focus {
            background-color: #ffffff;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
        }
        .btn-gradient-primary {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            color: #ffffff;
            border: none;
            border-radius: 0.65rem;
            font-weight: 600;
            padding: 0.65rem 1.25rem;
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
            transition: all 0.2s ease;
        }
        .btn-gradient-primary:hover {
            background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(13, 148, 136, 0.35);
        }
        .btn-gradient-emerald {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            color: #ffffff;
            border: none;
            border-radius: 0.65rem;
            font-weight: 600;
            padding: 0.65rem 1.25rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
            transition: all 0.2s ease;
        }
        .btn-gradient-emerald:hover {
            background: linear-gradient(135deg, #047857 0%, #065f46 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
        }
        .btn-gradient-danger {
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            color: #ffffff;
            border: none;
            border-radius: 0.65rem;
            font-weight: 600;
            padding: 0.65rem 1.25rem;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
            transition: all 0.2s ease;
        }
        .btn-gradient-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.35);
        }
        .chart-container-responsive {
            position: relative;
            width: 100%;
            height: 250px;
        }
    </style>

    <div class="py-4">
        <!-- Hero Header Banner -->
        <div class="report-hero-card p-4 p-md-5 mb-4 text-white">
            <div class="row align-items-center position-relative z-1">
                <div class="col-lg-8 mb-3 mb-lg-0">
                    <span class="badge bg-teal-500 text-white px-3 py-1 mb-2 rounded-pill fw-semibold" style="background-color: rgba(20, 184, 166, 0.3); backdrop-filter: blur(4px);">
                        <i class="bi bi-graph-up-arrow me-1"></i> Analytics & Reporting Center
                    </span>
                    <h2 class="fw-bold text-white mb-2 fs-3 fs-md-2">ศูนย์วิเคราะห์ผล & ออกรายงานสรุป</h2>
                    <p class="text-slate-300 mb-0 opacity-90 fs-6 fs-md-6">
                        ติดตามแนวโน้มข้อบกพร่อง วิเคราะห์จุดเฝ้าระวัง และออกรายงานสรุปมาตรฐานสำหรับผู้บริหารและ QA
                    </p>
                </div>
                <div class="col-lg-4 d-flex justify-content-lg-end gap-2">
                    <div class="bg-white bg-opacity-10 p-3 rounded-4 backdrop-blur text-center flex-fill" style="backdrop-filter: blur(8px); min-width: 110px;">
                        <div class="fs-4 fw-bold text-teal-300 text-warning">{{ number_format($recurringAreas->sum('count')) }}</div>
                        <div class="small opacity-75">จุดเฝ้าระวัง</div>
                    </div>
                    <div class="bg-white bg-opacity-10 p-3 rounded-4 backdrop-blur text-center flex-fill" style="backdrop-filter: blur(8px); min-width: 110px;">
                        <div class="fs-4 fw-bold text-amber-300 text-danger">{{ number_format($commonDefects->sum('count')) }}</div>
                        <div class="small opacity-75">ข้อบกพร่อง</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Insights Banner -->
        @if(!empty($aiInsight))
        <div class="glass-card p-4 mb-4" style="border-left: 5px solid #0d9488 !important; background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);">
            <div class="d-flex align-items-start">
                <div class="icon-badge bg-teal-600 text-white me-3 shadow-sm" style="background-color: #0d9488;">
                    <i class="bi bi-robot fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-teal-900 mb-1 d-flex align-items-center gap-2">
                        AI Executive Insights 🧠
                        <span class="badge bg-teal-200 text-teal-800 rounded-pill small font-normal" style="font-size: 0.7rem; background-color: #99f6e4; color: #115e59;">อัปเดตอัตโนมัติ</span>
                    </h6>
                    <p class="mb-0 text-teal-950 opacity-90" style="font-size: 0.95rem; line-height: 1.5;">{{ $aiInsight }}</p>
                </div>
            </div>
        </div>
        @endif

        <!-- Main Dashboard Section -->
        <div class="row g-4 mb-5">
            <!-- Left: Trend Chart (8 Cols) -->
            <div class="col-lg-8">
                <div class="glass-card h-100 p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="fw-bold mb-0 text-slate-800 fs-5"><i class="bi bi-activity text-teal-600 me-2"></i>แนวโน้มการพบปัญหารายเดือน</h6>
                            <small class="text-muted">สถิติจำนวนจุดที่ไม่ผ่านการตรวจ (Failures) ประจำเดือนปัจจุบัน</small>
                        </div>
                        <span class="badge bg-light text-secondary rounded-pill px-3 py-1 border border-secondary">Real-Time Data</span>
                    </div>
                    <div class="chart-container-responsive mt-2">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Right: Quick Analytics Breakdown (4 Cols) -->
            <div class="col-lg-4">
                <div class="glass-card h-100 p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0 text-slate-800 fs-5"><i class="bi bi-pie-chart-fill text-indigo-600 me-2"></i>สัดส่วนข้อบกพร่อง</h6>
                    </div>
                    <div class="chart-container-responsive d-flex align-items-center justify-content-center">
                        <canvas id="defectTypeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recurring Areas & Personnel (2x 6 Cols) -->
            <div class="col-lg-6">
                <div class="glass-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="fw-bold mb-0 text-slate-800 fs-5"><i class="bi bi-geo-alt-fill text-amber-600 me-2"></i>พื้นที่และเครื่องจักรที่พบปัญหาบ่อย (Recurring Areas)</h6>
                            <small class="text-muted">อันดับจุดตรวจสอบที่ต้องเฝ้าระวังการเกิดซ้ำ</small>
                        </div>
                    </div>
                    <div class="chart-container-responsive" style="height: 220px;">
                        <canvas id="areaChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="glass-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="fw-bold mb-0 text-slate-800 fs-5"><i class="bi bi-people-fill text-rose-500 me-2"></i>พนักงานที่พบปัญหาบ่อย (Recurring Personnel)</h6>
                            <small class="text-muted">อันดับพนักงานที่มีข้อบกพร่องสะสมสูงสุด</small>
                        </div>
                    </div>
                    <div class="chart-container-responsive" style="height: 220px;">
                        <canvas id="personnelChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Header for Report Generator Center -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-printer-fill text-teal-600"></i> ศูนย์ออกรายงาน (Report Generator Hub)
                </h4>
                <p class="text-muted small mb-0">เลือกประเภทรายงาน กำหนดเงื่อนไขวันที่ และส่งออกไฟล์ PDF / Excel ได้อย่างรวดเร็ว</p>
            </div>
        </div>

        <!-- Report Tools Grid (4 Cards Row) -->
        <div class="row g-4">
            <!-- 1. Daily Report Card -->
            <div class="col-md-6 col-lg-3">
                <div class="glass-card h-100 p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-badge bg-primary bg-opacity-10 text-primary me-3">
                                <i class="bi bi-calendar-check-fill"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-slate-800">รายงานประจำวัน</h6>
                                <small class="text-muted">Daily Hygiene Checklist</small>
                            </div>
                        </div>

                        <form action="{{ route('reports.daily') }}" method="GET" id="report_form">
                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-slate-600 mb-1">วันที่ตรวจสอบ</label>
                                <input type="date" name="date" id="date" value="{{ date('Y-m-d') }}" class="form-control form-control-custom">
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-slate-600 mb-1">แผนก</label>
                                <select name="department_id" id="department_id" class="form-select form-select-custom">
                                    <option value="">ทั้งหมด</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-slate-600 mb-1">กะงาน</label>
                                    <select name="shift" class="form-select form-select-custom">
                                        <option value="">ทั้งหมด</option>
                                        <option value="morning">เช้า</option>
                                        <option value="afternoon">บ่าย</option>
                                        <option value="night">ดึก</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-slate-600 mb-1">ประเภท</label>
                                    <select name="report_type" id="report_type" class="form-select form-select-custom">
                                        <option value="all">ทั้งหมด</option>
                                        <option value="person">พนักงาน</option>
                                        <option value="machine">เครื่องจักร/พื้นที่</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-2" id="machine-filter-group" style="display: none;">
                                <label class="form-label small fw-semibold text-slate-600 mb-1">เครื่องจักร</label>
                                <select name="machine_id" id="machine_id" class="form-select form-select-custom">
                                    <option value="">ทั้งหมด</option>
                                    @foreach($machines as $machine)
                                        <option value="{{ $machine->id }}">{{ $machine->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-slate-600 mb-1">การจัดวางกระดาษ</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="orientation" id="orientation_landscape" value="landscape" checked>
                                        <label class="form-check-label small" for="orientation_landscape">แนวนอน</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="orientation" id="orientation_portrait" value="portrait">
                                        <label class="form-check-label small" for="orientation_portrait">แนวตั้ง</label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-gradient-primary w-100 mb-2">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i> ออกรายงานประจำวัน
                            </button>
                            
                            <a href="#" onclick="event.preventDefault(); document.getElementById('report_form').action='{{ route('reports.export.fm-qa-22') }}'; document.getElementById('report_form').submit(); document.getElementById('report_form').action='{{ route('reports.daily') }}';" class="btn btn-outline-secondary w-100 btn-sm rounded-3">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i> FM-QA-22 (Machine)
                            </a>
                        </form>
                    </div>
                </div>
            </div>

            <!-- 2. Monthly Report Card -->
            <div class="col-md-6 col-lg-3">
                <div class="glass-card h-100 p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-badge bg-emerald-100 text-emerald-600 me-3" style="background-color: #d1fae5; color: #059669;">
                                <i class="bi bi-calendar-month-fill"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-slate-800">รายงานประจำเดือน</h6>
                                <small class="text-muted">Monthly Executive Summary</small>
                            </div>
                        </div>

                        <form action="{{ route('reports.monthly.pdf') }}" method="GET" id="monthly_report_form" target="_blank">
                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-slate-600 mb-1">เดือนที่ตรวจสอบ</label>
                                <input type="month" name="month" id="month" value="{{ date('Y-m') }}" class="form-control form-control-custom">
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-slate-600 mb-1">แผนก</label>
                                <select name="department_id" class="form-select form-select-custom">
                                    <option value="">ทั้งหมด</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-slate-600 mb-1">เป้าหมาย</label>
                                    <select name="target_type" class="form-select form-select-custom">
                                        <option value="person">พนักงาน</option>
                                        <option value="machine">เครื่องจักร/พื้นที่</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-slate-600 mb-1">รูปแบบ</label>
                                    <select name="format" class="form-select form-select-custom">
                                        <option value="summary">สถิติ (Summary)</option>
                                        <option value="matrix">ตาราง (Matrix)</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-gradient-emerald w-100 mt-4">
                                <i class="bi bi-file-earmark-pdf me-1"></i> ออกรายงานรายเดือน
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- 3. Money Report Card -->
            <div class="col-md-6 col-lg-3">
                <div class="glass-card h-100 p-4 d-flex flex-column justify-content-between" style="background: linear-gradient(180deg, #ffffff 0%, #fff5f5 100%);">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-badge bg-rose-100 text-rose-600 me-3" style="background-color: #ffe4e6; color: #e11d48;">
                                <i class="bi bi-currency-dollar fs-4"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-slate-800">Money Report 💰</h6>
                                <small class="text-muted">Cost of Non-conformance</small>
                            </div>
                        </div>

                        <p class="small text-slate-600 mb-4" style="line-height: 1.5;">
                            ประเมินมูลค่าความเสียหายเป็นตัวเงิน สรุปต้นทุนความไม่สอดคล้องตามแผนกและหมวดปัญหาสำหรับ QA Manager
                        </p>
                    </div>

                    <a href="{{ route('reports.money') }}" class="btn btn-gradient-danger w-100 text-center">
                        <i class="bi bi-bar-chart-line-fill me-1"></i> ดูรายงานมูลค่าความเสียหาย
                    </a>
                </div>
            </div>

            <!-- 4. Top Offenders Card -->
            <div class="col-md-6 col-lg-3">
                <div class="glass-card h-100 p-4 d-flex flex-column justify-content-between" style="background: linear-gradient(180deg, #ffffff 0%, #fffbeb 100%);">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-badge bg-amber-100 text-amber-600 me-3" style="background-color: #fef3c7; color: #d97706;">
                                <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-slate-800">Top Offenders</h6>
                                <small class="text-muted">High-Risk Targets</small>
                            </div>
                        </div>

                        <p class="small text-slate-600 mb-4" style="line-height: 1.5;">
                            ติดตามอันดับเป้าหมาย (พนักงาน/เครื่องจักร) ที่มีประวัติการตรวจไม่ผ่านเกณฑ์สูงสุด เพื่อวางแผนปรับปรุงและอบรม
                        </p>
                    </div>

                    <a href="{{ route('reports.offenders') }}" class="btn btn-outline-warning text-dark w-100 fw-semibold rounded-3" style="border-color: #f59e0b; background-color: #ffffff;">
                        <i class="bi bi-award-fill me-1 text-warning"></i> ดูอันดับผู้กระทำผิด
                    </a>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Data from Controller
            // @json() applies JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT.
            // Plain json_encode() leaves "<" and "/" intact, so a checkpoint title
            // or location name containing "</script>" would break out of this block.
            const trendLabels = @json($trendLabels);
            const trendValues = @json($trendValues);

            const areaData = @json($recurringAreas);
            const personnelData = @json($recurringPersonnel);
            const defectData = @json($commonDefects);

            // Toggle machine filter group
            const reportTypeSelect = document.getElementById('report_type');
            if (reportTypeSelect) {
                reportTypeSelect.addEventListener('change', function() {
                    const machineGroup = document.getElementById('machine-filter-group');
                    if (this.value === 'machine') {
                        machineGroup.style.display = 'block';
                    } else {
                        machineGroup.style.display = 'none';
                        document.getElementById('machine_id').value = '';
                    }
                });
            }

            // Chart Defaults & Fonts
            Chart.defaults.font.family = "'Inter', 'THSarabunNew', sans-serif";
            Chart.defaults.color = '#64748b';

            // 1. Monthly Trend (Line)
            const trendCtx = document.getElementById('trendChart');
            if (trendCtx) {
                const gradient = trendCtx.getContext('2d').createLinearGradient(0, 0, 0, 200);
                gradient.addColorStop(0, 'rgba(13, 148, 136, 0.35)');
                gradient.addColorStop(1, 'rgba(13, 148, 136, 0.0)');

                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: trendLabels,
                        datasets: [{
                            label: 'จำนวนจุดที่ไม่ผ่าน (Failures)',
                            data: trendValues,
                            borderColor: '#0d9488',
                            borderWidth: 3,
                            backgroundColor: gradient,
                            fill: true,
                            tension: 0.35,
                            pointBackgroundColor: '#0f766e',
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                padding: 10,
                                cornerRadius: 8,
                                backgroundColor: '#0f172a'
                            }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } }
                        }
                    }
                });
            }

            // 2. Defect Types (Doughnut)
            const defectCtx = document.getElementById('defectTypeChart');
            if (defectCtx) {
                new Chart(defectCtx, {
                    type: 'doughnut',
                    data: {
                        labels: defectData.map(d => d.label),
                        datasets: [{
                            data: defectData.map(d => d.count),
                            backgroundColor: [
                                '#0d9488', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                position: 'bottom',
                                labels: { boxWidth: 12, padding: 15, font: { size: 11 } } 
                            }
                        },
                        cutout: '70%'
                    }
                });
            }

            // 3. Problem Areas (Bar - Horizontal)
            const areaCtx = document.getElementById('areaChart');
            if (areaCtx) {
                new Chart(areaCtx, {
                    type: 'bar',
                    data: {
                        labels: areaData.map(d => d.label),
                        datasets: [{
                            label: 'จำนวนครั้งที่ไม่ผ่าน',
                            data: areaData.map(d => d.count),
                            backgroundColor: '#f59e0b',
                            borderRadius: 6,
                            barThickness: 16
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                            y: { grid: { display: false } }
                        }
                    }
                });
            }

            // 4. Problem Personnel (Bar - Horizontal)
            const personnelCtx = document.getElementById('personnelChart');
            if (personnelCtx) {
                new Chart(personnelCtx, {
                    type: 'bar',
                    data: {
                        labels: personnelData.map(d => d.label),
                        datasets: [{
                            label: 'จำนวนครั้งที่ไม่ผ่าน',
                            data: personnelData.map(d => d.count),
                            backgroundColor: '#f43f5e',
                            borderRadius: 6,
                            barThickness: 16
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                            y: { grid: { display: false } }
                        }
                    }
                });
            }
        });
    </script>
    @endpush
</x-app-layout>
