<x-app-layout>
    @section('header', 'รายงานและวิเคราะห์ผล')

    <div class="py-4">
        <!-- 1. Key Metrics Row -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                 <div class="card shadow-sm border-0 bg-white h-100 rounded-4 transition-hover">
                    <div class="card-body p-3 d-flex flex-column flex-sm-row align-items-sm-center">
                        <div class="rounded-circle mb-2 mb-sm-0 me-sm-3 d-flex align-items-center justify-content-center flex-shrink-0" style="background-color: #fee2e2; width: 48px; height: 48px;">
                            <i class="bi bi-exclamation-octagon-fill text-danger fs-5"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold text-dark mb-0 lh-1">{{ $recurringAreas->sum('count') }}</h3>
                            <div class="text-muted mt-1 text-truncate" style="font-size: 0.8rem;">จุดที่พบปัญหา</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 bg-white h-100 rounded-4 transition-hover">
                   <div class="card-body p-3 d-flex flex-column flex-sm-row align-items-sm-center">
                       <div class="rounded-circle mb-2 mb-sm-0 me-sm-3 d-flex align-items-center justify-content-center flex-shrink-0" style="background-color: #fef3c7; width: 48px; height: 48px;">
                           <i class="bi bi-bug-fill text-warning fs-5"></i>
                       </div>
                       <div>
                           <h3 class="fw-bold text-dark mb-0 lh-1">{{ $commonDefects->sum('count') }}</h3>
                           <div class="text-muted mt-1 text-truncate" style="font-size: 0.8rem;">ข้อบกพร่อง</div>
                       </div>
                   </div>
               </div>
           </div>
           <!-- Shortcuts -->
           <div class="col-12 col-md-6">
               <div class="card shadow-sm border-0 h-100 rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                   <div class="position-absolute top-0 end-0 opacity-25" style="transform: translate(15%, -25%);">
                        <i class="bi bi-file-earmark-bar-graph-fill" style="font-size: 7rem; color: white;"></i>
                   </div>
                   <div class="card-body p-3 p-md-4 d-flex align-items-center position-relative z-1">
                        <div class="bg-white rounded-circle me-3 d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 48px; height: 48px;">
                            <i class="bi bi-file-earmark-pdf-fill fs-5 text-primary"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="fw-bold mb-1 text-white">ต้องการรายงานด่วน?</h6>
                            <div class="text-white opacity-75" style="font-size: 0.85rem; line-height: 1.3;">ไปที่ "สร้างรายงาน" เพื่อโหลด PDF</div>
                        </div>
                   </div>
               </div>
           </div>
        </div>

        <div class="row g-4">
            <!-- LEFT COLUMN: ANALYTICS DASHBOARD (Col-lg-8) -->
            <div class="col-lg-8">
                <div class="row g-4">
                    <div class="col-12">
                        <h5 class="fw-bold text-secondary mb-0"><i class="bi bi-bar-chart-fill me-2"></i>วิเคราะห์แนวโน้ม</h5>
                    </div>
                    
                    <!-- AI Insights -->
                    @if(!empty($aiInsight))
                    <div class="col-12">
                        <div class="card shadow-sm border-0 rounded-4" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-left: 5px solid #0ea5e9 !important;">
                            <div class="card-body p-4 d-flex align-items-center">
                                <div class="bg-white p-3 rounded-circle me-4 shadow-sm text-primary flex-shrink-0">
                                    <i class="bi bi-robot fs-2"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-2">AI Insights 🧠</h6>
                                    <p class="mb-0 text-dark" style="font-size: 0.95rem;">{{ $aiInsight }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Monthly Trend -->
                    <div class="col-12">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-transparent border-0 p-4 pb-0">
                                <h6 class="fw-bold mb-0">แนวโน้มการพบปัญหารายเดือน</h6>
                                <small class="text-muted">สถิติจำนวนจุดที่ "ไม่ผ่าน" ในรอบเดือนปัจจุบัน</small>
                            </div>
                            <div class="card-body p-4">
                                <canvas id="trendChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="col-md-6">
                         <div class="card shadow-sm border-0 rounded-4 h-100">
                             <div class="card-header bg-transparent border-0 p-4 pb-0">
                                <h6 class="fw-bold mb-0">ประเภทปัญหาที่พบบ่อย (Top Defects)</h6>
                            </div>
                            <div class="card-body p-4 d-flex align-items-center justify-content-center">
                                <canvas id="defectTypeChart" style="max-height: 250px;"></canvas>
                            </div>
                         </div>
                    </div>

                    <div class="col-md-6">
                         <div class="card shadow-sm border-0 rounded-4 h-100">
                             <div class="card-header bg-transparent border-0 p-4 pb-0">
                                <h6 class="fw-bold mb-0">พื้นที่ที่มีปัญหาบ่อย (Recurring Areas)</h6>
                            </div>
                            <div class="card-body p-4">
                                 <canvas id="areaChart" height="200"></canvas>
                            </div>
                         </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: REPORT TOOLS (Col-lg-4) -->
            <div class="col-lg-4">
                <h5 class="fw-bold text-secondary mb-3"><i class="bi bi-printer-fill me-2"></i>สร้างรายงาน (Generate Reports)</h5>
                
                <!-- Daily Report Card -->
                <div class="card shadow-sm border-0 mb-4 rounded-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                <i class="bi bi-calendar-check text-primary fs-5"></i>
                            </div>
                            <h6 class="fw-bold mb-0">รายงานประจำวัน</h6>
                        </div>
                        
                        <form action="{{ route('reports.daily') }}" method="GET" id="report_form">
                            <div class="mb-3">
                                <label class="form-label small text-muted">วันที่ตรวจสอบ</label>
                                <input type="date" name="date" id="date" value="{{ date('Y-m-d') }}" class="form-control form-control-sm">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">แผนก</label>
                                <select name="department_id" id="department_id" class="form-select form-select-sm">
                                    <option value="">ทั้งหมด</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small text-muted">กะงาน</label>
                                    <select name="shift" class="form-select form-select-sm">
                                        <option value="">ทั้งหมด</option>
                                        <option value="morning">เช้า</option>
                                        <option value="afternoon">บ่าย</option>
                                        <option value="night">ดึก</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted">ประเภท</label>
                                    <select name="report_type" id="report_type" class="form-select form-select-sm">
                                        <option value="all">ทั้งหมด</option>
                                        <option value="person">รายคน</option>
                                        <option value="machine">เครื่องจักร</option>
                                        <option value="area">พื้นที่</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3" id="machine-filter-group" style="display: none;">
                                <label class="form-label small text-muted">เลือกเครื่องจักร</label>
                                <select name="machine_id" id="machine_id" class="form-select form-select-sm">
                                    <option value="">ทั้งหมด</option>
                                    @foreach($machines as $machine)
                                        <option value="{{ $machine->id }}">{{ $machine->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
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
                            
                            <script>
                                document.getElementById('report_type').addEventListener('change', function() {
                                    const machineGroup = document.getElementById('machine-filter-group');
                                    if (this.value === 'machine') {
                                        machineGroup.style.display = 'block';
                                    } else {
                                        machineGroup.style.display = 'none';
                                        document.getElementById('machine_id').value = '';
                                    }
                                });
                            </script>

                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <i class="bi bi-file-earmark-arrow-down me-2"></i> สร้าง PDF
                            </button>
                            
                            <a href="#" onclick="event.preventDefault(); document.getElementById('report_form').action='{{ route('reports.export.fm-qa-22') }}'; document.getElementById('report_form').submit(); document.getElementById('report_form').action='{{ route('reports.daily') }}';" class="btn btn-outline-success w-100">
                                <i class="bi bi-file-earmark-spreadsheet me-2"></i> สร้างรายงาน FM-QA-22 (Machine)
                            </a>
                        </form>
                    </div>
                </div>

                <!-- Monthly Report Card -->
                <div class="card shadow-sm border-0 mb-4 rounded-4" style="background-color: #f8fafc;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success bg-opacity-10 p-2 rounded-circle me-3">
                                <i class="bi bi-graph-up-arrow text-success fs-5"></i>
                            </div>
                            <h6 class="fw-bold mb-0">รายงานประจำเดือน</h6>
                        </div>
                        
                        <form action="{{ route('reports.monthly.pdf') }}" method="GET" id="monthly_report_form" target="_blank">
                            <div class="mb-3">
                                <label class="form-label small text-muted">เดือนที่ตรวจสอบ</label>
                                <input type="month" name="month" id="month" value="{{ date('Y-m') }}" class="form-control form-control-sm">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">แผนก</label>
                                <select name="department_id" class="form-select form-select-sm">
                                    <option value="">ทั้งหมด</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small text-muted">เป้าหมาย</label>
                                    <select name="target_type" class="form-select form-select-sm">
                                        <option value="person">รายคน</option>
                                        <option value="machine">เครื่องจักร</option>
                                        <option value="area">พื้นที่</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted">รูปแบบรายงาน</label>
                                    <select name="format" class="form-select form-select-sm">
                                        <option value="summary">สถิติ (Summary)</option>
                                        <option value="matrix">ตาราง (Matrix)</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-file-earmark-pdf me-2"></i> สร้างรายงานรายเดือน
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Top Offenders Link -->
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning bg-opacity-10 p-2 rounded-circle me-3">
                                <i class="bi bi-exclamation-triangle text-dark fs-5"></i>
                            </div>
                             <h6 class="fw-bold mb-0">Top Offenders</h6>
                        </div>
                        <p class="small text-muted mb-3">ดูรายชื่อผู้ที่มีประวัติการตรวจไม่ผ่านเกณฑ์สูงสุดประจำเดือน</p>
                        <a href="{{ route('reports.offenders') }}" class="btn btn-outline-warning text-dark w-100">
                             ดูรายงาน
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Data from Controller
            const trendLabels = {!! json_encode($trendLabels) !!};
            const trendValues = {!! json_encode($trendValues) !!};
            
            const areaData = {!! json_encode($recurringAreas) !!};
            const defectData = {!! json_encode($commonDefects) !!};

            // 1. Monthly Trend (Line)
            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'จำนวนจุดที่ไม่ผ่าน (Failures)',
                        data: trendValues,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });

            // 2. Defect Types (Doughnut)
            new Chart(document.getElementById('defectTypeChart'), {
                type: 'doughnut',
                data: {
                    labels: defectData.map(d => d.label),
                    datasets: [{
                        data: defectData.map(d => d.count),
                        backgroundColor: [
                            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { 
                        legend: { position: 'bottom' } 
                    }
                }
            });

            // 3. Problem Areas (Bar - Horizontal)
            new Chart(document.getElementById('areaChart'), {
                type: 'bar',
                data: {
                    labels: areaData.map(d => d.label),
                    datasets: [{
                        label: 'จำนวนครั้งที่ไม่ผ่าน',
                        data: areaData.map(d => d.count),
                        backgroundColor: '#f97316',
                        borderRadius: 5
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
