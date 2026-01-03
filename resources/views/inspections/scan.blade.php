<x-app-layout>
    @section('header', 'ตรวจพนักงาน')

    <div class="row justify-content-center align-items-center" style="min-height: 70vh;">
        <div class="col-md-6 col-lg-5">
            
            <!-- Ready State -->
            <div id="scan-ready-state" class="card border-0 shadow-sm text-center py-5 rounded-4">
                <div class="card-body">
                    <div class="mb-4">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex justify-content-center align-items-center" style="width: 100px; height: 100px;">
                            <i class="bi bi-qr-code-scan text-primary" style="font-size: 3rem;"></i>
                        </div>
                    </div>
                    
                    <h3 class="fw-bold mb-2">พร้อมสำหรับการตรวจ</h3>
                    <p class="text-muted mb-4">แตะปุ่มด้านล่างเพื่อสแกน QR Code ของพนักงาน</p>
                    
                    <button id="start-scan-btn" class="btn btn-primary-custom btn-lg w-75 shadow-sm">
                        <i class="bi bi-qr-code me-2"></i> สแกน QR Code
                    </button>
                </div>
            </div>

            <!-- Scanner State (Hidden by default) -->
            <div id="scanner-container" class="card border-0 shadow-sm rounded-4 d-none overflow-hidden">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold m-0"><i class="bi bi-camera-video me-2 text-primary"></i>กำลังสแกน...</h5>
                    <button id="close-scan-btn" class="btn btn-light btn-sm rounded-circle"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="card-body p-0 position-relative">
                    <div id="reader" style="width: 100%;"></div>
                    <!-- Overlay text -->
                    <div class="position-absolute bottom-0 w-100 text-center text-white pb-3" style="background: linear-gradient(to top, rgba(0,0,0,0.5), transparent); pointer-events: none;">
                        <small>ถือกล้องให้นิ่งเพื่อโฟกัส QR Code</small>
                    </div>
                </div>
            </div>

            <!-- Manual Input State (Optional fallback) -->
            <div class="text-center mt-4">
                <button class="btn btn-link text-muted text-decoration-none" onclick="toggleManualInput()">
                    <small>สแกนไม่ได้? กรอกรหัสพนักงาน</small>
                </button>
            </div>

        </div>
    </div>


    @push('scripts')
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        // Define global function for manual input toggle
        function toggleManualInput() {
            let code = prompt("กรุณากรอกรหัสพนักงาน (QR Hash):");
            if (code) {
                window.location.href = `/inspection/session/{{ $session->id }}/verify/${code}`;
            }
        }

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
                // Redirect using the decoded hash
                window.location.href = `/inspection/session/{{ $session->id }}/verify/${decodedText}`;
            }

            function onScanFailure(error) {
               // Ignore errors for cleaner logs
            }
        });
    </script>
    @endpush
</x-app-layout>
