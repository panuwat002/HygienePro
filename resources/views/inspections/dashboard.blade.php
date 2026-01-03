<x-app-layout>
    @section('header', 'เริ่มต้นการตรวจ (Start Inspection)')

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-5">
                    <form action="{{ route('inspection.start') }}" method="POST">
                        @csrf
                        
                        <div class="mb-4">
                            <label for="department_id" class="form-label fw-bold text-muted">เลือกแผนก (Department)</label>
                            <select class="form-select form-select-lg" name="department_id" id="department_id" required>
                                <option value="" selected disabled>-- กรุณาเลือกแผนก --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->dept_code }} - {{ $dept->dept_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Location Overview Section -->
                        <div id="location-overview" class="mb-4 d-none">
                            <h6 class="fw-bold text-muted mb-3"><i class="bi bi-geo-alt-fill me-2"></i>สรุปจุดประจำการในแผนกนี้</h6>
                            <div class="row g-3" id="location-cards">
                                <!-- Cards will be injected here via JS -->
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="shift" class="form-label fw-bold text-muted">เลือกกะ (Shift)</label>
                            <div class="d-flex gap-3">
                                <input type="radio" class="btn-check" name="shift" id="shift_morning" value="morning" checked>
                                <label class="btn btn-outline-primary btn-lg flex-grow-1 py-3 dashboard-shift-btn" for="shift_morning">
                                    <i class="bi bi-sun fs-2 d-block mb-1"></i> เช้า
                                </label>

                                <input type="radio" class="btn-check" name="shift" id="shift_afternoon" value="afternoon">
                                <label class="btn btn-outline-warning btn-lg flex-grow-1 py-3 dashboard-shift-btn" for="shift_afternoon">
                                    <i class="bi bi-brightness-high fs-2 d-block mb-1"></i> บ่าย
                                </label>

                                <input type="radio" class="btn-check" name="shift" id="shift_night" value="night">
                                <label class="btn btn-outline-dark btn-lg flex-grow-1 py-3 dashboard-shift-btn" for="shift_night">
                                    <i class="bi bi-moon-stars fs-2 d-block mb-1"></i> ดึก
                                </label>
                            </div>
                        </div>

                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-primary-custom btn-lg shadow-sm py-3 fs-5">
                                เปิดเซสชันการตรวจ <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deptSelect = document.getElementById('department_id');
        const overviewContainer = document.getElementById('location-overview');
        const cardsContainer = document.getElementById('location-cards');
        const shiftInputs = document.querySelectorAll('input[name="shift"]');

        // 1. Auto-Select Shift based on Time
        function autoSelectShift() {
            const hour = new Date().getHours();
            let shift = 'morning';
            
            if (hour >= 6 && hour < 14) {
                shift = 'morning';
            } else if (hour >= 14 && hour < 22) {
                shift = 'afternoon';
            } else {
                shift = 'night';
            }
            
            document.getElementById(`shift_${shift}`).checked = true;
            return shift;
        }

        // Run auto-select on load
        autoSelectShift();

        // 2. Fetch Data Function
        function fetchStats() {
            const deptId = deptSelect.value;
            if(!deptId) return;

            // Get selected shift
            const selectedShift = document.querySelector('input[name="shift"]:checked').value;

            // Loading state
            overviewContainer.classList.remove('d-none');
            // Only show full loading if container is empty or we switched departments. 
            // If just switching shift, maybe keep content but dim it? For now simple loading.
            cardsContainer.innerHTML = '<div class="col-12 text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">กำลังโหลดข้อมูล...</p></div>';

            fetch(`/inspection/stats/${deptId}?shift=${selectedShift}`)
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        renderLocations(data.locations);
                    } else {
                        cardsContainer.innerHTML = '<div class="col-12 text-center text-danger">ไม่สามารถโหลดข้อมูลได้</div>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    cardsContainer.innerHTML = '<div class="col-12 text-center text-danger">เกิดข้อผิดพลาดในการเชื่อมต่อ</div>';
                });
        }

        // 3. Event Listeners
        deptSelect.addEventListener('change', fetchStats);
        
        shiftInputs.forEach(input => {
            input.addEventListener('change', fetchStats);
        });

        // 4. Render Logic with Progress
        function renderLocations(locations) {
            cardsContainer.innerHTML = '';
            
            if(locations.length === 0) {
                 cardsContainer.innerHTML = '<div class="col-12 text-center text-muted py-3">ไม่มีข้อมูลจุดประจำการ</div>';
                 return;
            }

            locations.forEach(loc => {
                const total = loc.employees_count;
                const inspected = loc.inspected_count || 0;
                const hasEmployees = total > 0;
                const opacityClass = hasEmployees ? '' : 'opacity-75';
                
                // Progress Logic
                let progressBadge = '';
                if(hasEmployees) {
                    if(inspected >= total) {
                        progressBadge = `<span class="badge bg-success rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>ครบแล้ว (${inspected}/${total})</span>`;
                    } else {
                        progressBadge = `<span class="badge bg-warning text-dark rounded-pill"><i class="bi bi-hourglass-split me-1"></i>ตรวจแล้ว ${inspected}/${total}</span>`;
                    }
                } else {
                    progressBadge = `<span class="badge bg-secondary rounded-pill">ไม่มีพนักงาน</span>`;
                }

                // Machine Status Button
                const areaUrl = `/inspection/area/${deptSelect.value}/${loc.id}`; // Construct URL
                const machineBtn = `<a href="${areaUrl}" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-gear-wide-connected me-1"></i>ตรวจเครื่องจักร/พื้นที่</a>`;

                const html = `
                <div class="col-md-6">
                    <div class="card h-100 border shadow-sm ${opacityClass}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold mb-0 text-dark">${loc.location_name}</h6>
                                ${progressBadge}
                            </div>
                            <p class="text-muted small mb-3">${loc.description || '-'}</p>
                            
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <small class="text-muted"><i class="bi bi-people me-1"></i> ${hasEmployees ? 'สำหรับกะนี้' : '-'}</small>
                                ${machineBtn}
                            </div>
                        </div>
                    </div>
                </div>`;
                
                cardsContainer.innerHTML += html;
            });
        }
    });
    </script>
    @endpush
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
