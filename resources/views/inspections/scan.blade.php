<x-app-layout>
    @section('header', 'ตรวจพนักงาน')

    @php
        // Calculate progress percentage (filtered by session's shift)
        $totalEmployees = $session->department->employees()
            ->where('is_active', true)
            ->whereHas('shift', fn($q) => $q->where('shift_name', $session->shift))
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
                    
                    <button id="start-scan-btn" class="btn btn-primary-custom btn-lg w-75 shadow-sm mb-3">
                        <i class="bi bi-qr-code me-2"></i> สแกน QR Code
                    </button>
                    
                    <div class="px-4">
                        <hr class="my-4" style="opacity: 0.2;">
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
                    window.location.href = `/inspection/session/{{ $session->id }}/verify/${encodeURIComponent(result.value.trim())}`;
                }
            });
        }
    </script>
    @endpush
</x-app-layout>
