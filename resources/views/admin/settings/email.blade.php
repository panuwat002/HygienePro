<x-app-layout>
    @section('header', 'ตั้งค่าอีเมลของระบบ (Email Settings)')

    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                        <div class="d-flex align-items-center mb-1">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <i class="bi bi-envelope-paper fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0">การตั้งค่าเซิร์ฟเวอร์อีเมล (SMTP)</h5>
                                <small class="text-muted">ปรับแต่งค่าสำหรับการส่งอีเมลแจ้งเตือนของระบบ</small>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <form action="{{ route('admin.settings.email.update') }}" method="POST">
                            @csrf

                            <div class="row g-3">
                                <!-- Basic Credentials -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">อีเมลผู้ส่ง (Email Address) <span class="text-danger">*</span></label>
                                    <input type="text" name="MAIL_USERNAME" class="form-control form-control-lg rounded-3" value="{{ $env['MAIL_USERNAME'] === 'null' ? '' : $env['MAIL_USERNAME'] }}" placeholder="your-email@gmail.com" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">รหัสผ่าน (App Password) <span class="text-danger">*</span></label>
                                    <input type="password" name="MAIL_PASSWORD" class="form-control form-control-lg rounded-3" value="{{ $env['MAIL_PASSWORD'] === 'null' ? '' : $env['MAIL_PASSWORD'] }}" placeholder="รหัสผ่าน หรือ App Password" required>
                                    <small class="text-muted d-block mt-1">กรณีใช้ Gmail แนะนำให้ใช้ <a href="https://support.google.com/accounts/answer/185833" target="_blank">App Passwords</a></small>
                                </div>

                                <div class="col-md-12 mt-4">
                                    <label class="form-label fw-bold">ชื่อผู้ส่ง (Sender Name)</label>
                                    <input type="text" name="MAIL_FROM_NAME" class="form-control form-control-lg rounded-3" value="{{ str_replace('"', '', $env['MAIL_FROM_NAME']) }}" placeholder="HygienePro System" required>
                                </div>

                            </div>

                            <div class="accordion mt-4 border-0" id="advancedSettings">
                                <div class="accordion-item border rounded-3 overflow-hidden">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed bg-light text-secondary fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced">
                                            <i class="bi bi-gear-fill me-2"></i> การตั้งค่าขั้นสูง (Advanced Settings)
                                        </button>
                                    </h2>
                                    <div id="collapseAdvanced" class="accordion-collapse collapse" data-bs-parent="#advancedSettings">
                                        <div class="accordion-body">
                                            <div class="row g-3">
                                                <!-- Mailer & Host -->
                                                <div class="col-md-4">
                                                    <label class="form-label fw-bold small">Mailer</label>
                                                    <select name="MAIL_MAILER" class="form-select rounded-3">
                                                        <option value="smtp" {{ $env['MAIL_MAILER'] === 'smtp' ? 'selected' : '' }}>SMTP</option>
                                                        <option value="log" {{ $env['MAIL_MAILER'] === 'log' ? 'selected' : '' }}>Log (ทดสอบ)</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-8">
                                                    <label class="form-label fw-bold small">Mail Host</label>
                                                    <input type="text" name="MAIL_HOST" class="form-control rounded-3" value="{{ $env['MAIL_HOST'] }}" placeholder="smtp.gmail.com">
                                                </div>

                                                <!-- Port & Encryption -->
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold small">Port</label>
                                                    <input type="number" name="MAIL_PORT" class="form-control rounded-3" value="{{ $env['MAIL_PORT'] }}" placeholder="587">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold small">Encryption</label>
                                                    <select name="MAIL_ENCRYPTION" class="form-select rounded-3">
                                                        <option value="tls" {{ $env['MAIL_ENCRYPTION'] === 'tls' ? 'selected' : '' }}>TLS</option>
                                                        <option value="ssl" {{ $env['MAIL_ENCRYPTION'] === 'ssl' ? 'selected' : '' }}>SSL</option>
                                                        <option value="null" {{ $env['MAIL_ENCRYPTION'] === 'null' ? 'selected' : '' }}>ไม่มี</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-12">
                                                    <label class="form-label fw-bold small">From Address</label>
                                                    <input type="email" id="mailFromAddress" name="MAIL_FROM_ADDRESS" class="form-control rounded-3" value="{{ str_replace('"', '', $env['MAIL_FROM_ADDRESS']) }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @push('scripts')
                            <script>
                                document.querySelector('input[name="MAIL_USERNAME"]').addEventListener('input', function(e) {
                                    document.getElementById('mailFromAddress').value = e.target.value;
                                });
                            </script>
                            @endpush

                            <hr class="my-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-outline-info rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#testEmailModal">
                                    <i class="bi bi-send me-2"></i> ทดสอบส่งอีเมล (Test Email)
                                </button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4">
                                    <i class="bi bi-save me-2"></i> บันทึกการตั้งค่า
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="alert alert-warning border border-warning shadow-sm rounded-4 mt-4 d-flex align-items-center">
                    <i class="bi bi-info-circle-fill fs-3 me-3 flex-shrink-0"></i>
                    <div>
                        <h6 class="fw-bold mb-1">คำแนะนำการใช้งาน</h6>
                        <span class="small">การบันทึกข้อมูลในหน้านี้จะทำการเปลี่ยนแปลงไฟล์ Config ของระบบ และรีเซ็ต Cache โดยอัตโนมัติ หากตั้งค่าผิดพลาดอาจทำให้ระบบล่าช้าเมื่อพยายามส่งอีเมล</span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @push('modals')
    <!-- Test Email Modal -->
    <div class="modal fade" id="testEmailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <form action="{{ route('admin.settings.email.test') }}" method="POST">
                    @csrf
                    <div class="modal-header border-bottom-0 pb-0">
                        <h5 class="modal-title fw-bold">ทดสอบการส่งอีเมล</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-3 pb-4">
                        <p class="text-muted small mb-3">กรุณาระบุอีเมลที่ต้องการส่งข้อความทดสอบไปหา เพื่อตรวจสอบว่าระบบสามารถส่งอีเมลได้จริง (แนะนำให้บันทึกการตั้งค่าก่อนทำการทดสอบ)</p>
                        <div class="form-group">
                            <label class="form-label fw-bold small">อีเมลผู้รับ (Recipient Email)</label>
                            <input type="email" name="test_email" class="form-control form-control-lg rounded-3" placeholder="example@gmail.com" required>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-info text-white rounded-pill px-4">
                            <i class="bi bi-send-check me-2"></i> ส่งอีเมลทดสอบ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endpush
</x-app-layout>
