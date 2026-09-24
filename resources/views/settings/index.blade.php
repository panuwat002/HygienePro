<x-app-layout>
    @section('title', 'ตั้งค่าระบบ (Settings)')

    <div class="container-fluid py-4 px-md-4">
        <div class="row mb-4 align-items-center">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px;">
                        <i class="bi bi-sliders text-primary me-2"></i> ตั้งค่าการรับอีเมล
                    </h2>
                    <p class="text-muted mb-0 fs-6">ปรับแต่งการแจ้งเตือนเพื่อรับเฉพาะข้อมูลที่สำคัญสำหรับคุณ</p>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center p-3" role="alert" style="background: #ecfdf5; color: #065f46;">
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 32px; height: 32px;">
                    <i class="bi bi-check-lg"></i>
                </div>
                <div class="fw-medium">{{ session('success') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-xl-8 col-lg-7">
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    
                    <!-- Section: Inspections -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                        <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded p-2 me-3">
                                    <i class="bi bi-clipboard-check fs-5"></i>
                                </div>
                                <h5 class="fw-bold mb-0 text-dark">การตรวจเช็ค (Inspections)</h5>
                            </div>
                        </div>
                        <div class="card-body p-4 pt-2">
                            <div class="setting-item d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div class="pe-3">
                                    <h6 class="fw-bold text-dark mb-1">พนักงานเริ่มตรวจ (Session Started)</h6>
                                    <span class="text-muted small">แจ้งเตือนเมื่อมีการเริ่มรอบการตรวจเช็คในระบบ</span>
                                </div>
                                <div class="form-check form-switch form-switch-lg mb-0">
                                    <input class="form-check-input shadow-sm cursor-pointer" type="checkbox" name="email_session_started" id="email_session_started" {{ $preferences['email_session_started'] ? 'checked' : '' }}>
                                </div>
                            </div>
                            
                            <div class="setting-item d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div class="pe-3">
                                    <h6 class="fw-bold text-dark mb-1">ตรวจเสร็จสิ้นแบบผ่าน 100% (100% Pass)</h6>
                                    <span class="text-muted small">แจ้งเตือนเมื่อพนักงานตรวจเสร็จและทุกรายการผ่านทั้งหมด</span>
                                </div>
                                <div class="form-check form-switch form-switch-lg mb-0">
                                    <input class="form-check-input shadow-sm cursor-pointer" type="checkbox" name="email_session_finished_pass" id="email_session_finished_pass" {{ $preferences['email_session_finished_pass'] ? 'checked' : '' }}>
                                </div>
                            </div>
                            
                            <div class="setting-item d-flex justify-content-between align-items-center py-3">
                                <div class="pe-3">
                                    <h6 class="fw-bold text-dark mb-1">ตรวจเสร็จสิ้นและพบของเสีย (Has Fail)</h6>
                                    <span class="text-muted small">แจ้งเตือนเมื่อตรวจเสร็จแต่พบรายการที่ไม่ผ่านเกณฑ์</span>
                                </div>
                                <div class="form-check form-switch form-switch-lg mb-0">
                                    <input class="form-check-input shadow-sm cursor-pointer custom-switch-warning" type="checkbox" name="email_session_finished_fail" id="email_session_finished_fail" {{ $preferences['email_session_finished_fail'] ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: QA Verification -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                        <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-info bg-opacity-10 text-info rounded p-2 me-3">
                                    <i class="bi bi-shield-check fs-5"></i>
                                </div>
                                <h5 class="fw-bold mb-0 text-dark">การตรวจสอบโดย QA (Verification)</h5>
                            </div>
                        </div>
                        <div class="card-body p-4 pt-2">
                            <div class="setting-item d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div class="pe-3">
                                    <h6 class="fw-bold text-dark mb-1">QA สั่งให้ทำความสะอาดใหม่ (Order Re-clean)</h6>
                                    <span class="text-muted small">แจ้งเตือนเมื่อ QA ไม่อนุมัติและสั่งให้ทำความสะอาดใหม่</span>
                                </div>
                                <div class="form-check form-switch form-switch-lg mb-0">
                                    <input class="form-check-input shadow-sm cursor-pointer custom-switch-warning" type="checkbox" name="email_order_reclean" id="email_order_reclean" {{ $preferences['email_order_reclean'] ? 'checked' : '' }}>
                                </div>
                            </div>
                            
                            <div class="setting-item d-flex justify-content-between align-items-center py-3">
                                <div class="pe-3">
                                    <h6 class="fw-bold text-dark mb-1">QA ยืนยันผลตรวจครบทั้งเอกสาร (Verified)</h6>
                                    <span class="text-muted small">แจ้งเตือนเมื่อ QA Supervisor กดยืนยันผลตรวจครบทุกรายการ</span>
                                </div>
                                <div class="form-check form-switch form-switch-lg mb-0">
                                    <input class="form-check-input shadow-sm cursor-pointer" type="checkbox" name="email_session_verified" id="email_session_verified" {{ $preferences['email_session_verified'] ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Corrective Actions -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                        <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-danger bg-opacity-10 text-danger rounded p-2 me-3">
                                    <i class="bi bi-wrench-adjustable fs-5"></i>
                                </div>
                                <h5 class="fw-bold mb-0 text-dark">เอกสารใบสั่งแก้ไข (CAR)</h5>
                            </div>
                        </div>
                        <div class="card-body p-4 pt-2">
                            <div class="setting-item d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div class="pe-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <h6 class="fw-bold text-danger mb-0 me-2">สร้างใบ CAR ใหม่ (New CAR)</h6>
                                        <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">สำคัญ</span>
                                    </div>
                                    <span class="text-muted small">ส่งอีเมลไปยังผู้รับผิดชอบเมื่อมีการสร้างใบสั่งแก้ไขใหม่</span>
                                </div>
                                <div class="form-check form-switch form-switch-lg mb-0">
                                    <input class="form-check-input shadow-sm cursor-pointer custom-switch-danger" type="checkbox" name="email_car_new" id="email_car_new" {{ $preferences['email_car_new'] ? 'checked' : '' }}>
                                </div>
                            </div>
                            
                            <div class="setting-item d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div class="pe-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <h6 class="fw-bold text-danger mb-0 me-2">แก้ไข CAR เสร็จสิ้น (CAR Resolved)</h6>
                                        <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">สำคัญ</span>
                                    </div>
                                    <span class="text-muted small">แจ้งเตือน QA เมื่อพนักงานรายงานว่าแก้ไขข้อบกพร่องเสร็จแล้ว</span>
                                </div>
                                <div class="form-check form-switch form-switch-lg mb-0">
                                    <input class="form-check-input shadow-sm cursor-pointer custom-switch-danger" type="checkbox" name="email_car_resolved" id="email_car_resolved" {{ $preferences['email_car_resolved'] ? 'checked' : '' }}>
                                </div>
                            </div>
                            
                            <div class="setting-item d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div class="pe-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <h6 class="fw-bold text-danger mb-0 me-2">ปิดใบ CAR (CAR Closed)</h6>
                                        <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">สำคัญ</span>
                                    </div>
                                    <span class="text-muted small">แจ้งเตือนเมื่อ QA ยืนยันความถูกต้องและทำการปิดใบสั่งแก้ไข</span>
                                </div>
                                <div class="form-check form-switch form-switch-lg mb-0">
                                    <input class="form-check-input shadow-sm cursor-pointer custom-switch-danger" type="checkbox" name="email_car_closed" id="email_car_closed" {{ $preferences['email_car_closed'] ? 'checked' : '' }}>
                                </div>
                            </div>
                            
                            <div class="setting-item d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div class="pe-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <h6 class="fw-bold text-danger mb-0 me-2">CAR ค้างเกินกำหนดเวลา (Overdue CAR)</h6>
                                        <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">สำคัญ</span>
                                    </div>
                                    <span class="text-muted small">แจ้งเตือนด่วนเมื่อมีใบสั่งแก้ไขที่ยังไม่เสร็จและเลยกำหนดเวลาแล้ว</span>
                                </div>
                                <div class="form-check form-switch form-switch-lg mb-0">
                                    <input class="form-check-input shadow-sm cursor-pointer custom-switch-danger" type="checkbox" name="email_car_overdue" id="email_car_overdue" {{ $preferences['email_car_overdue'] ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- ถึงปิดอีเมลไว้ กระดิ่งในระบบก็ยังแจ้งอยู่ --}}
                            <div class="setting-item d-flex justify-content-between align-items-center py-3">
                                <div class="pe-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <h6 class="fw-bold text-danger mb-0 me-2">งานรออนุมัติ (Awaiting Approval)</h6>
                                        <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">สำคัญ</span>
                                    </div>
                                    <span class="text-muted small">สรุปรอบตรวจที่ QA ทวนสอบเสร็จแล้วและรอคุณอนุมัติ ส่งวันละครั้งตอนเช้า (เฉพาะผู้มีสิทธิ์อนุมัติ)</span>
                                </div>
                                <div class="form-check form-switch form-switch-lg mb-0">
                                    <input class="form-check-input shadow-sm cursor-pointer custom-switch-danger" type="checkbox" name="email_awaiting_approval" id="email_awaiting_approval" {{ $preferences['email_awaiting_approval'] ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mb-5">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm fw-bold d-flex align-items-center" style="transition: all 0.3s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(13, 110, 253, 0.3) !important'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 .125rem .25rem rgba(0,0,0,.075) !important'">
                            <i class="bi bi-save2 me-2"></i> บันทึกการตั้งค่า
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="col-xl-4 col-lg-5">
                <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden sticky-top" style="top: 2rem;">
                    <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(59,130,246,0.05) 0%, rgba(139,92,246,0.05) 100%); z-index: 0;"></div>
                    <div class="card-body p-5 text-center position-relative z-1">
                        <div class="mb-4 position-relative d-inline-block">
                            <div class="rounded-circle bg-white shadow-sm d-flex justify-content-center align-items-center" style="width: 120px; height: 120px;">
                                <i class="bi bi-envelope-paper-heart fs-1" style="background: -webkit-linear-gradient(45deg, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                            </div>
                            <div class="position-absolute top-0 end-0 translate-middle p-2 bg-success border border-light rounded-circle shadow-sm" style="margin-top: 15px; margin-right: 5px;"></div>
                        </div>
                        
                        <h4 class="fw-bold text-dark mb-2">Control Your Inbox</h4>
                        <p class="text-muted small mb-4">การปรับแต่งการแจ้งเตือนอย่างเหมาะสม จะช่วยลดปัญหาอีเมลขยะและทำให้คุณโฟกัสกับงานที่สำคัญได้ดีขึ้น</p>
                        
                        <div class="bg-white rounded-3 p-3 text-start shadow-sm border border-light">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-info-circle-fill text-primary mt-1 me-2"></i>
                                <div>
                                    <strong class="d-block small text-dark mb-1">แจ้งเตือนในระบบยังคงทำงานปกติ</strong>
                                    <span class="text-muted" style="font-size: 0.75rem;">การปิดอีเมลจะไม่มีผลกับไอคอนกระดิ่งแจ้งเตือนที่มุมขวาบนของระบบ</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Custom Switch Styling */
        .form-switch-lg {
            padding-left: 3rem;
        }
        .form-switch-lg .form-check-input {
            width: 3rem;
            height: 1.5rem;
            margin-left: -3rem;
            background-color: #e2e8f0;
            border: none;
            transition: all 0.3s ease;
        }
        .form-switch-lg .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%28255, 255, 255, 1%29'/%3e%3c/svg%3e");
        }
        .form-switch-lg .form-check-input:checked {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }
        
        /* Custom Colors for Switches */
        .custom-switch-danger:checked {
            background-color: #ef4444 !important;
            border-color: #ef4444 !important;
        }
        .custom-switch-danger:focus {
            box-shadow: 0 0 0 0.25rem rgba(239, 68, 68, 0.25) !important;
        }
        
        .custom-switch-warning:checked {
            background-color: #f59e0b !important;
            border-color: #f59e0b !important;
        }
        .custom-switch-warning:focus {
            box-shadow: 0 0 0 0.25rem rgba(245, 158, 11, 0.25) !important;
        }
        
        .cursor-pointer {
            cursor: pointer;
        }
        
        .setting-item {
            transition: background-color 0.2s ease;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
            border-radius: 8px;
        }
        .setting-item:hover {
            background-color: #f8fafc;
        }
        .setting-item:last-child {
            border-bottom: none !important;
        }
    </style>
</x-app-layout>
