<section>
    <header class="mb-4">
        <h2 class="h4 text-dark fw-bold mb-1">
            {{ __('ลายเซ็นต์อิเล็กทรอนิกส์ (E-Signature)') }}
        </h2>

        <p class="text-muted small">
            {{ __('ตั้งค่าลายเซ็นต์เพื่อใช้ประทับตราในการอนุมัติผลการตรวจสอบ (Verify / Approve)') }}
        </p>
    </header>

    <div>
        @if ($user->signature_path)
            <div class="mb-4 p-3 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                <div>
                    <p class="small fw-bold text-secondary mb-2">ลายเซ็นต์ปัจจุบัน:</p>
                    <img src="{{ asset('storage/' . $user->signature_path) }}" alt="Current Signature" class="border bg-white shadow-sm rounded" style="max-height: 96px;">
                </div>
                <form method="post" action="{{ route('profile.signature.destroy') }}" class="m-0" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบลายเซ็นต์นี้?');">
                    @csrf
                    @method('delete')
                    <button type="submit" class="btn btn-outline-danger btn-sm fw-bold rounded-pill">
                        <i class="bi bi-trash"></i> ลบลายเซ็นต์
                    </button>
                </form>
            </div>
        @endif

        <form method="post" action="{{ route('profile.signature') }}" id="signature-form" enctype="multipart/form-data">
            @csrf
            @method('patch')

            <!-- Nav tabs -->
            <ul class="nav nav-pills mb-3" id="signatureTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill fw-bold" id="draw-tab" data-bs-toggle="tab" data-bs-target="#draw-tab-pane" type="button" role="tab" aria-controls="draw-tab-pane" aria-selected="true">วาดลายเซ็น (Draw)</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill fw-bold" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-tab-pane" type="button" role="tab" aria-controls="upload-tab-pane" aria-selected="false">อัปโหลดไฟล์ (.png)</button>
                </li>
            </ul>

            <!-- Tab panes -->
            <div class="tab-content" id="signatureTabsContent">
                <div class="tab-pane fade show active" id="draw-tab-pane" role="tabpanel" aria-labelledby="draw-tab" tabindex="0">
                    <div class="mb-4">
                        <div class="border rounded-3 shadow-sm bg-white overflow-hidden d-inline-block w-100" style="max-width: 380px; touch-action: none;">
                            <canvas id="signature-pad" class="w-100" width="380" height="200" style="background-color: #fff;"></canvas>
                        </div>
                        <div class="mt-2">
                            <button type="button" id="clear-signature" class="btn btn-outline-secondary btn-sm px-3 fw-bold rounded-pill shadow-sm">
                                ล้าง (Clear)
                            </button>
                        </div>
                        <input type="hidden" name="signature_data" id="signature_data">
                        @error('signature_data')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="tab-pane fade" id="upload-tab-pane" role="tabpanel" aria-labelledby="upload-tab" tabindex="0">
                    <div class="mb-4">
                        <label for="signature_file" class="form-label fw-bold">เลือกไฟล์ลายเซ็น (แนะนำ .png โปร่งใส)</label>
                        <input class="form-control" type="file" id="signature_file" name="signature_file" accept=".png">
                        @error('signature_file')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <button type="submit" id="save-signature" class="btn btn-primary px-4 fw-bold rounded-pill shadow-sm">{{ __('บันทึกลายเซ็นต์') }}</button>

                @if (session('status') === 'signature-updated')
                    <p
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 2000)"
                        class="text-success small mb-0 fw-medium"
                    ><i class="bi bi-check-circle-fill me-1"></i>{{ __('บันทึกสำเร็จ') }}</p>
                @endif
            </div>
        </form>
    </div>

    <!-- Add Signature Pad JS -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var canvas = document.getElementById('signature-pad');
            
            function resizeCanvas() {
                var ratio =  Math.max(window.devicePixelRatio || 1, 1);
                var data;
                if(signaturePad && !signaturePad.isEmpty()) {
                    data = signaturePad.toData();
                }
                
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                
                if(data && signaturePad) {
                    signaturePad.fromData(data);
                }
            }

            var signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 1)',
                penColor: 'rgb(0, 0, 0)'
            });

            // Set canvas size correctly for different screen scales (desktop/mobile)
            window.addEventListener("resize", resizeCanvas);
            resizeCanvas();

            document.getElementById('clear-signature').addEventListener('click', function () {
                signaturePad.clear();
            });

            document.getElementById('signature-form').addEventListener('submit', function (e) {
                var isUploadTab = document.getElementById('upload-tab').classList.contains('active');
                var signatureFile = document.getElementById('signature_file').files.length > 0;

                if (!isUploadTab && signaturePad.isEmpty()) {
                    e.preventDefault();
                    alert('กรุณาวาดลายเซ็นต์ก่อนบันทึก หรือเปลี่ยนไปที่แท็บ "อัปโหลดไฟล์" หากต้องการอัปโหลดไฟล์');
                } else if (!isUploadTab && !signaturePad.isEmpty()) {
                    // Clear the file input just in case
                    document.getElementById('signature_file').value = "";
                    document.getElementById('signature_data').value = signaturePad.toDataURL('image/png');
                } else if (isUploadTab && !signatureFile) {
                    e.preventDefault();
                    alert('กรุณาเลือกไฟล์ลายเซ็นต์ก่อนบันทึก หรือหากต้องการเก็บลายเซ็นต์เดิมไว้ไม่ต้องกดบันทึกในส่วนนี้');
                } else if (isUploadTab && signatureFile) {
                    // Clear the canvas data just in case
                    document.getElementById('signature_data').value = "";
                }
            });
        });
    </script>
</section>
