<x-app-layout>
    @section('header', 'เมนูรายงานและวิเคราะห์ผล (Reports & Analytics)')

    <div class="py-4">
        <!-- 1. Key Metrics Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                 <div class="card shadow-sm border-0 bg-white h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
                            <i class="bi bi-exclamation-octagon text-danger fs-4"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold text-dark mb-0">{{ $recurringAreas->sum('count') }}</h2>
                            <small class="text-muted">จุดที่พบปัญหา (Areas)</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card shadow-sm border-0 bg-white h-100">
                   <div class="card-body p-3 d-flex align-items-center">
                       <div class="bg-warning bg-opacity-10 p-3 rounded-circle me-3">
                           <i class="bi bi-bug text-warning fs-4"></i>
                       </div>
                       <div>
                           <h2 class="fw-bold text-dark mb-0">{{ $commonDefects->sum('count') }}</h2>
                           <small class="text-muted">ข้อบกพร่อง (Defects)</small>
                       </div>
                   </div>
               </div>
           </div>
           <!-- Shortcuts -->
           <div class="col-md-6">
               <div class="card shadow-sm border-0 bg-primary text-white h-100 bg-gradient">
                   <div class="card-body p-3 d-flex align-items-center justify-content-between">
                       <div class="d-flex align-items-center">
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle me-3">
                                <i class="bi bi-file-earmark-pdf fs-4"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0">ต้องการรายงานด่วน?</h5>
                                <small class="text-white-50">เลือกเมนูทางขวาเพื่อสร้างรายงาน PDF</small>
                            </div>
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
                        <h5 class="fw-bold text-secondary mb-0"><i class="bi bi-bar-chart-fill me-2"></i>วิเคราะห์แนวโน้ม (Trend Analytics)</h5>
                    </div>
                    
                    <!-- Monthly Trend -->
                    <div class="col-12">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-transparent border-0 p-4 pb-0">
                                <h6 class="fw-bold mb-0">แนวโน้มการพบปัญหา (Monthly Failure Trend)</h6>
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
