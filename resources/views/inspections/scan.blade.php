<x-app-layout>
    @section('header', 'ตรวจพนักงาน')

    @php
        // Calculate progress percentage (filtered by session's shift, handling both English and Thai DB names)
        $shiftNames = match($session->shift) {
            'morning' => ['morning', 'กะเช้า'],
            'afternoon' => ['afternoon', 'กะบ่าย'],
            'night' => ['night', 'กะดึก'],
            default => [$session->shift]
        };
        $totalEmployees = $session->department->employees()
            ->where('is_active', true)
            ->whereHas('shift', fn($q) => $q->whereIn('shift_name', $shiftNames))
            ->count();
        $inspectedCount = $session->logs()->distinct('employee_id')->count();
        $progressPercent = $totalEmployees > 0 ? round(($inspectedCount / $totalEmployees) * 100) : 0;
    @endphp

    <div class="row justify-content-center align-items-center" style="min-height: 70vh;">
        <div class="col-md-6 col-lg-5">
            
            <!-- Session Info - Updated Design -->
            <div class="card border-0 shadow-sm mb-4" style="background: var(--primary-soft); border-radius: var(--radius-lg);">
                <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <small class="fw-bold text-uppercase d-block" style="font-size: 0.7rem; letter-spacing: 1px; color: var(--primary);">กำลังตรวจ (Inspecting)</small>
                        <h6 class="fw-bold mb-0" style="color: var(--slate-800);">{{ $session->department->dept_name }}</h6>
                    </div>
                    <div class="text-end">
                        @if($session->type === 'personnel')
                            <span class="badge rounded-pill px-3" style="background: var(--primary);">รอบที่ {{ $session->round }}</span>
                        @endif
                        <small class="d-block mt-1" style="font-size: 0.75rem; color: var(--slate-500);">กะ {{ ucfirst($session->shift) }}</small>
                    </div>
                </div>
            </div>

            <!-- Ready State with Progress Ring -->
            <div id="scan-ready-state" class="card border-0 shadow-sm text-center py-5" style="border-radius: var(--radius-xl);">
                <div class="card-body">
                    <!-- Progress Ring -->
                    <div class="progress-ring-container mb-4">
                        <svg class="progress-ring" width="180" height="180" viewBox="0 0 180 180">
                            <circle cx="90" cy="90" r="80" fill="none" stroke="var(--slate-200)" stroke-width="12"/>
                            <circle cx="90" cy="90" r="80" fill="none" stroke="var(--primary)" stroke-width="12" 
                                stroke-dasharray="502.65" 
                                stroke-dashoffset="{{ 502.65 * (1 - ($progressPercent / 100)) }}"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="progress-ring-text">
                            <div class="value">{{ $progressPercent }}%</div>
                            <div class="label">เสร็จสิ้น</div>
                        </div>
                    </div>
                    
                    <h3 class="fw-bold mb-2" style="color: var(--slate-800);">พร้อมสำหรับการตรวจ</h3>
                    <p class="mb-4 px-3" style="color: var(--slate-500);">
                        ตรวจไปแล้ว <strong style="color: var(--primary);">{{ $inspectedCount }}</strong> จาก <strong>{{ $totalEmployees }}</strong> คน
                    </p>
                    
                    <button id="start-scan-btn" class="btn btn-primary-custom btn-lg w-100 shadow-sm mb-3 py-3 fs-5 fw-bold">
                        <i class="bi bi-qr-code me-2"></i> สแกน QR Code
                    </button>
                    
                    <div class="px-4">
                        <hr class="my-4" style="opacity: 0.2;">
                        
                        {{-- Bulk Pass Button Section --}}
                        @php
                            $shiftRemainingCount = $totalEmployees - $inspectedCount;
                        @endphp
                        
                        <div class="card border-0 shadow-sm mb-3" style="background: {{ $shiftRemainingCount > 0 ? 'var(--success-soft)' : '#f8f9fa' }}; border-radius: var(--radius-lg); border-left: 4px solid {{ $shiftRemainingCount > 0 ? 'var(--success)' : '#ced4da' }} !important;">
                            <div class="card-body p-3 text-start">
                                <h6 class="fw-bold mb-1" style="color: {{ $shiftRemainingCount > 0 ? 'var(--success)' : '#6c757d' }};">ตรวจพนักงานกะ{{ $session->shift === 'morning' ? 'เช้า' : 'ดึก' }}ครบแล้วใช่ไหม?</h6>
                                <small class="text-muted d-block mb-3">
                                    @if($shiftRemainingCount > 0)
                                        ระบบจะให้ "ผ่าน" เฉพาะพนักงานในกะปัจจุบันเท่านั้น
                                    @elseif($totalEmployees == 0)
                                        แผนกนี้ไม่มีพนักงานที่ทำงานในกะปัจจุบัน
                                    @else
                                        คุณได้ตรวจสอบพนักงานกะปัจจุบันครบทุกคนแล้ว
                                    @endif
                                </small>
                                
                                @if($shiftRemainingCount > 0)
                                    <button type="button" class="btn btn-success rounded-pill fw-bold shadow-sm w-100" data-bs-toggle="modal" data-bs-target="#bulkPassModal">
                                        <i class="bi bi-check-all me-1"></i> ผ่านทุกคนที่เหลือ ({{ $shiftRemainingCount }})
                                    </button>
                                @elseif($totalEmployees == 0)
                                    <button type="button" class="btn btn-secondary rounded-pill fw-bold shadow-sm w-100" disabled>
                                        <i class="bi bi-dash-circle me-1"></i> ไม่มีพนักงานในกะนี้
                                    </button>
                                @else
                                    <button type="button" class="btn btn-secondary rounded-pill fw-bold shadow-sm w-100" disabled>
                                        <i class="bi bi-check-all me-1"></i> กะปัจจุบันครบแล้ว
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                             <form action="{{ route('inspection.pause', $session->id) }}" method="POST" class="flex-fill">
                                @csrf
                                <button type="submit" class="btn btn-lg w-100 py-3 fw-bold" style="background: var(--warning-soft); color: var(--warning); border-radius: var(--radius-md);">
                                    <i class="bi bi-pause-circle me-2"></i> พักการตรวจ
                                </button>
                            </form>
                            <form action="{{ route('inspection.finish', $session->id) }}" method="POST" id="finish-session-form" class="flex-fill">
                                @csrf
                                <button type="button" class="btn btn-lg w-100 py-3 fw-bold" style="background: var(--success-soft); color: var(--success); border-radius: var(--radius-md);" onclick="confirmFinishSession(this)">
                                    <i class="bi bi-check2-circle me-2"></i> จบงาน
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary State (Hidden by default, shown after Bulk Pass) -->
            <div id="summary-state" class="card border-0 shadow-sm text-center py-5 d-none" style="border-radius: var(--radius-xl); background: var(--success-soft); border: 2px solid var(--success) !important;">
                <div class="card-body">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                    </div>
                    
                    <h3 class="fw-bold mb-2 text-success">ตรวจสอบครบถ้วนแล้ว</h3>
                    <p class="mb-4 px-3" style="color: var(--slate-600);">
                        คุณได้ทำการตรวจสอบเป้าหมายทั้งหมดในรอบนี้เรียบร้อยแล้ว กรุณากด "จบงาน" เพื่อบันทึกข้อมูลและส่งให้หัวหน้าอนุมัติ
                    </p>
                    
                    <div class="d-flex gap-2 justify-content-center px-4">
                        <a href="{{ route('inspection.browse', $session->id) }}" class="btn btn-outline-success btn-lg flex-fill py-3 fw-bold rounded-pill">
                            <i class="bi bi-search me-1"></i> ทบทวนข้อมูล
                        </a>
                        <button type="button" class="btn btn-success btn-lg flex-fill py-3 fw-bold rounded-pill shadow-sm" onclick="confirmFinishSession(this)">
                            <i class="bi bi-check2-circle me-2"></i> จบงาน (Finish Job)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Scanner State (Hidden by default) -->
            <div id="scanner-container" class="card border-0 shadow-sm d-none overflow-hidden" style="border-radius: var(--radius-xl);">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold m-0"><i class="bi bi-camera-video me-2" style="color: var(--primary);"></i>กำลังสแกน...</h5>
                    <button id="close-scan-btn" class="btn btn-light btn-sm rounded-circle" style="width: 40px; height: 40px;"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="card-body p-0 position-relative">
                    <div id="reader" style="width: 100%;"></div>
                    <!-- Overlay text -->
                    <div class="position-absolute bottom-0 w-100 text-center text-white pb-3" style="background: linear-gradient(to top, rgba(0,0,0,0.5), transparent); pointer-events: none;">
                        <small>ถือกล้องให้นิ่งเพื่อโฟกัส QR Code</small>
                    </div>
                </div>
            </div>

            <!-- Mode Toggle -->
            <div class="card border-0 shadow-sm mt-3" style="border-radius: var(--radius-lg);">
                <div class="card-body py-3 px-4">
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill text-center" disabled>
                            <i class="bi bi-qr-code me-1"></i> สแกน QR
                        </button>
                        <a href="{{ route('inspection.browse', $session->id) }}" class="btn btn-outline-secondary btn-sm flex-fill text-center">
                            <i class="bi bi-list-ul me-1"></i> เลือกจากรายชื่อ
                        </a>
                    </div>
                </div>
            </div>

            <!-- Manual Input (Fallback) -->
            <div class="text-center mt-3">
                <button class="btn btn-link text-decoration-none" style="color: var(--slate-400);" onclick="toggleManualInput()">
                    <small>สแกนไม่ได้? กรอกรหัสพนักงาน</small>
                </button>
            </div>


        </div>
    </div>


    @if($shiftRemainingCount > 0)
        @push('modals')
        {{-- Bulk Pass Confirmation Modal --}}
        <div class="modal fade" id="bulkPassModal" tabindex="-1" aria-labelledby="bulkPassModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                    <div class="modal-header bg-success text-white border-0 py-3">
                        <h5 class="modal-title fw-bold" id="bulkPassModalLabel">
                            <i class="bi bi-shield-check me-2"></i>ยืนยัน Bulk Pass (เฉพาะกะปัจจุบัน)
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="text-center mb-3">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-people-fill text-success" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="fw-bold text-dark">คุณกำลังจะให้ผ่านทั้งหมด</h5>
                            <p class="text-muted mb-0">
                                พนักงานที่เหลืออีก <strong class="text-success fs-4">{{ $shiftRemainingCount }}</strong> คน
                                <strong class="text-danger border-bottom border-danger">เฉพาะในกะ{{ $session->shift === 'morning' ? 'เช้า' : 'ดึก' }}</strong><br>
                                จะถูกบันทึกว่า <strong class="text-success">"ผ่าน"</strong> ทุกหัวข้อตรวจโดยอัตโนมัติ
                            </p>
                        </div>
                        <div class="alert alert-warning d-flex align-items-start rounded-3 mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 mt-1 flex-shrink-0"></i>
                            <div class="small">
                                <strong>พนักงานกะอื่นจะไม่ได้รับผลกระทบ:</strong> การดำเนินการนี้จะเปลี่ยนสถานะเฉพาะพนักงานที่มีรอบทำงานตรงกับกะของ Session นี้เท่านั้น (พนักงานกะอื่นจะยังคงสถานะเดิม)
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-3 pt-0 gap-2">
                        <button type="button" class="btn btn-light rounded-pill flex-grow-1 fw-bold" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i> ยกเลิก
                        </button>
                        <form id="bulkPassForm" action="{{ route('inspection.bulk-pass', $session->id) }}" method="POST" class="flex-grow-1 m-0">
                            @csrf
                            <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-check-all me-1"></i> ยืนยัน ผ่านทุกคน ({{ $shiftRemainingCount }} คน)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endpush
    @endif

    @push('scripts')
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const readyState = document.getElementById('scan-ready-state');
            const scannerContainer = document.getElementById('scanner-container');
            const startBtn = document.getElementById('start-scan-btn');
            const closeBtn = document.getElementById('close-scan-btn');
            let html5QrcodeScanner = null;

            // Start Scanner Function
            function startScanner() {
                readyState.classList.add('d-none');
                scannerContainer.classList.remove('d-none');

                html5QrcodeScanner = new Html5Qrcode("reader");
                const config = { fps: 10, qrbox: { width: 250, height: 250 } };
                
                // Request camera
                html5QrcodeScanner.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure)
                .catch(err => {
                    alert("Error starting camera: " + err);
                    stopScanner();
                });
            }

            // Stop Scanner Function
            function stopScanner() {
                if (html5QrcodeScanner) {
                    html5QrcodeScanner.stop().then(() => {
                        html5QrcodeScanner.clear();
                        readyState.classList.remove('d-none');
                        scannerContainer.classList.add('d-none');
                    }).catch(err => {
                        console.error("Failed to stop scanner", err);
                    });
                } else {
                    readyState.classList.remove('d-none');
                    scannerContainer.classList.add('d-none');
                }
            }

            // Button Event Listeners
            if(startBtn) startBtn.addEventListener('click', startScanner);
            if(closeBtn) closeBtn.addEventListener('click', stopScanner);

            function onScanSuccess(decodedText, decodedResult) {
                if (html5QrcodeScanner) {
                     stopScanner();
                }

                let cleanCode = decodedText.trim();
                
                // Deterministic 10% random check (Session ID + Scanned Code)
                // Note: cleanCode is usually qr_code_hash or employee_id.
                // For a simpler client-side check, we generate a pseudo-random number based on the string.
                let hash = 0;
                let str = `{{ $session->id }}-${cleanCode}`;
                for (let i = 0; i < str.length; i++) {
                    let char = str.charCodeAt(i);
                    hash = ((hash << 5) - hash) + char;
                    hash = hash & hash;
                }
                let requiresPhoto = Math.abs(hash) % 10 === 0;

                // For personnel type only: 10% chance to force a real-time photo
                if (requiresPhoto && '{{ $session->type }}' === 'personnel') {
                    // Force Real-time Photo taking via SweetAlert
                    Swal.fire({
                        title: '📸 สุ่มตรวจยืนยันตัวตน!',
                        html: `
                            <p class="text-danger small mb-2">ระบบสุ่มให้คุณถ่ายภาพพนักงานหน้างานจริง เพื่อยืนยันการตรวจ</p>
                            <div id="random-camera-container" style="width: 100%; max-width: 300px; margin: 0 auto; overflow: hidden; border-radius: 8px; border: 2px solid #ccc;">
                                <video id="random-video" width="100%" autoplay playsinline style="display:block;"></video>
                                <canvas id="random-canvas" style="display:none;"></canvas>
                            </div>
                            <img id="random-preview" style="display:none; width: 100%; max-width: 300px; margin: 0 auto; border-radius: 8px; border: 2px solid #16a34a;" />
                        `,
                        showCancelButton: true,
                        confirmButtonText: '<i class="bi bi-camera"></i> ถ่ายภาพ',
                        cancelButtonText: 'ยกเลิก',
                        confirmButtonColor: '#0d6efd',
                        cancelButtonColor: '#dc3545',
                        allowOutsideClick: false,
                        didOpen: () => {
                            // Start camera in Swal
                            const video = document.getElementById('random-video');
                            navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
                                .then(stream => {
                                    video.srcObject = stream;
                                    window.currentSwalStream = stream;
                                })
                                .catch(err => {
                                    console.error("Swal Camera Error:", err);
                                    Swal.showValidationMessage("ไม่สามารถเปิดกล้องได้ กรุณาตรวจสิทธิการเข้าถึง");
                                });
                        },
                        preConfirm: () => {
                            const video = document.getElementById('random-video');
                            const canvas = document.getElementById('random-canvas');
                            const preview = document.getElementById('random-preview');

                            if (video.style.display !== 'none') {
                                // First click: Take Photo
                                canvas.width = video.videoWidth;
                                canvas.height = video.videoHeight;
                                canvas.getContext('2d').drawImage(video, 0, 0);
                                
                                const base64Image = canvas.toDataURL('image/jpeg', 0.8);
                                
                                // Show preview, hide video
                                video.style.display = 'none';
                                preview.src = base64Image;
                                preview.style.display = 'block';

                                // Change button text to confirm
                                Swal.getConfirmButton().innerHTML = '<i class="bi bi-check-circle"></i> ยืนยันรูปภาพ';
                                Swal.getConfirmButton().style.backgroundColor = '#16a34a'; // Green

                                // Stop camera hardware
                                if (window.currentSwalStream) {
                                    window.currentSwalStream.getTracks().forEach(track => track.stop());
                                }

                                return false; // Prevent modal from closing on first click
                            } else {
                                // Second click: Confirm Photo
                                return preview.src; // Return base64 string
                            }
                        },
                        willClose: () => {
                            // Cleanup camera if cancelled before taking photo
                            if (window.currentSwalStream) {
                                window.currentSwalStream.getTracks().forEach(track => track.stop());
                            }
                        }
                    }).then((result) => {
                        if (result.isConfirmed && result.value) {
                            // Result.value contains the base64 image.
                            // We must POST this to the verify route instead of a simple GET redirect
                            submitVerificationPost(cleanCode, result.value);
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            // Cancelled random check, do not proceed
                            Swal.fire('ยกเลิก', 'คุณยกเลิกการถ่ายภาพยืนยันตัวตน', 'error');
                        }
                    });
                } else {
                    // Normal behavior: redirect to GET verify
                    window.location.href = `/inspection/session/{{ $session->id }}/verify/${encodeURIComponent(cleanCode)}`;
                }
            }

            function submitVerificationPost(cleanCode, base64Image) {
                // Create a temporary form to POST the base64 image + code
                Swal.fire({
                    title: 'กำลังประมวลผล...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/inspection/session/{{ $session->id }}/verify-with-photo/${encodeURIComponent(cleanCode)}`;
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);

                const photoInput = document.createElement('input');
                photoInput.type = 'hidden';
                photoInput.name = 'random_evidence_photo_base64';
                photoInput.value = base64Image;
                form.appendChild(photoInput);

                document.body.appendChild(form);
                form.submit();
            }

            function onScanFailure(error) {
               // Ignore errors for cleaner logs
            }

            // Finish Session Confirmation
            window.confirmFinishSession = function(btn) {
                const failedCount = {{ $failedCount ?? 0 }};
                
                if (failedCount > 0) {
                    Swal.fire({
                        title: 'มีพนักงานที่ไม่ผ่านสุขลักษณะ',
                        html: `พบพนักงานจำนวน <strong class="text-danger">${failedCount}</strong> คนที่ไม่ผ่านการตรวจ<br>กรุณากด <strong>"ทบทวนข้อมูล"</strong> เพื่อตรวจสอบและแก้ไขให้เรียบร้อยก่อนจบงาน`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#eab308',
                        cancelButtonColor: '#64748b',
                        confirmButtonText: '<i class="bi bi-search"></i> ทบทวนข้อมูล',
                        cancelButtonText: 'ยกเลิก',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "{{ route('inspection.browse', $session->id) }}";
                        }
                    });
                    return; // Block finish
                }

                Swal.fire({
                    title: 'ยืนยันสรุปยอดการตรวจ?',
                    html: 'ตรวจแล้ว <strong>{{ $inspectedCount }}</strong> จาก <strong>{{ $totalEmployees }}</strong> คน ({{ $progressPercent }}%)'
                        + ({{ $totalEmployees - $inspectedCount }} > 0 ? '<br><span class="text-danger">ยังเหลืออีก {{ $totalEmployees - $inspectedCount }} คนที่ยังไม่ได้ตรวจ</span>' : '')
                        + '<br><small class="text-muted">หลังจากจบงานแล้วจะไม่สามารถสแกนเพิ่มในรอบนี้ได้อีก</small>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'ใช่, จบงานเลย',
                    cancelButtonText: 'ยังก่อน',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> กำลังบันทึก...';
                        document.getElementById('finish-session-form').submit();
                    }
                });
            }
        });

        function toggleManualInput() {
            Swal.fire({
                title: 'กรอกรหัสพนักงาน',
                input: 'text',
                inputPlaceholder: 'รหัสพนักงาน เช่น EMP001',
                inputAttributes: { autocomplete: 'off', inputmode: 'text' },
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-search me-1"></i> ค้นหา',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#0d6efd',
                inputValidator: (value) => {
                    if (!value || !value.trim()) return 'กรุณากรอกรหัสพนักงาน';
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    isIntentionalNav = true;
                    window.location.href = `/inspection/session/{{ $session->id }}/verify/${encodeURIComponent(result.value.trim())}`;
                }
            });
        }

        // Bulk Pass AJAX handling
        const bulkPassForm = document.getElementById('bulkPassForm');
        if (bulkPassForm) {
            bulkPassForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Show Loading
                Swal.fire({
                    title: 'กำลังบันทึกข้อมูล...',
                    html: 'กรุณารอสักครู่',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Submit via Fetch
                fetch(this.action, {
                    method: 'POST',
                    body: new FormData(this),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close Modal
                        let myModalEl = document.getElementById('bulkPassModal');
                        let modal = bootstrap.Modal.getInstance(myModalEl);
                        if (modal) {
                            modal.hide();
                        } else {
                            // Fallback
                            myModalEl.classList.remove('show');
                            document.body.classList.remove('modal-open');
                            const backdrops = document.querySelectorAll('.modal-backdrop');
                            backdrops.forEach(b => b.remove());
                        }
                        
                        // Hide UI elements
                        document.getElementById('scan-ready-state').classList.add('d-none');
                        const scannerContainer = document.getElementById('scanner-container');
                        if (scannerContainer) scannerContainer.classList.add('d-none');
                        
                        // Hide Fallbacks and Toggles
                        const fallbacks = document.querySelectorAll('.text-center.mt-3, .card.border-0.shadow-sm.mt-3');
                        fallbacks.forEach(el => el.classList.add('d-none'));
                        
                        // Show Summary UI
                        const summaryState = document.getElementById('summary-state');
                        if (summaryState) {
                            summaryState.classList.remove('d-none');
                            // Add a tiny animation class if available in their stack
                            summaryState.style.animation = 'fadeIn 0.5s ease-in-out';
                        }
                        
                        Swal.close();
                    } else {
                        Swal.fire('ข้อผิดพลาด', data.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                });
            });
        }
    </script>
    
    <!-- Prevent Accidental Exit -->
    <script>
        let isIntentionalNav = false;

        document.addEventListener('DOMContentLoaded', function() {
            // All forms are intentional
            document.querySelectorAll('form').forEach(f => {
                f.addEventListener('submit', () => isIntentionalNav = true);
            });

            // Browse button is intentional
            const browseBtn = document.querySelector('a[href*="browse"]');
            if (browseBtn) {
                browseBtn.addEventListener('click', () => isIntentionalNav = true);
            }
        });

        window.addEventListener('beforeunload', function (e) {
            if (!isIntentionalNav) {
                e.preventDefault();
                e.returnValue = ''; // Standard behavior requires truthy value
            }
        });
    </script>
    @endpush
</x-app-layout>
