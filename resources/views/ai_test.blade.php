<x-app-layout>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-primary text-white p-4">
                    <h4 class="mb-0"><i class="bi bi-robot"></i> Smart AI Auto-Verification (Mockup)</h4>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted mb-4">ทดสอบอัปโหลดรูปภาพที่พบความผิดปกติ เพื่อให้ AI คำนวณความมั่นใจ (Confidence Score)</p>
                    
                    <form action="{{ route('ai.analyze') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label fw-bold">เลือกรูปภาพตรวจสอบ:</label>
                            <input type="file" name="image" class="form-control form-control-lg" accept="image/*" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill shadow-sm">
                            <i class="bi bi-search"></i> ส่งให้ AI วิเคราะห์
                        </button>
                    </form>

                    @if(session('result'))
                        <hr class="my-5">
                        <div class="text-center mb-4">
                            <h5 class="fw-bold mb-3">ผลการวิเคราะห์จาก Python AI Service</h5>
                            
                            @if(session('image_path'))
                                <img src="/storage/{{ session('image_path') }}" class="img-fluid rounded border mb-4 shadow-sm" style="max-height: 250px;">
                            @endif

                            @php $res = session('result'); @endphp
                            
                            @if($res)
                                @if($res['confidence'] >= 0.90)
                                    <!-- Fast Lane -->
                                    <div class="alert alert-success rounded-4 text-start">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-rocket-takeoff-fill fs-3 text-success me-3"></i>
                                            <div>
                                                <h5 class="alert-heading fw-bold mb-1">Fast Lane (สั่งการอัตโนมัติ)</h5>
                                                <div class="small">ความมั่นใจ: <strong>{{ number_format($res['confidence'] * 100, 1) }}%</strong></div>
                                            </div>
                                        </div>
                                        <hr>
                                        <p class="mb-0">{{ $res['message'] }}</p>
                                        <div class="mt-3 small text-muted">ระบบจะทำการออก Corrective Action และแจ้งเตือนฝ่ายผลิตทันที โดยไม่ต้องรอ QA Supervisor อนุมัติ</div>
                                    </div>
                                @else
                                    <!-- Human Lane -->
                                    <div class="alert alert-warning rounded-4 text-start">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-person-fill-exclamation fs-3 text-warning-emphasis me-3"></i>
                                            <div>
                                                <h5 class="alert-heading fw-bold mb-1">Human Lane (รอคนตรวจสอบ)</h5>
                                                <div class="small">ความมั่นใจ: <strong>{{ number_format($res['confidence'] * 100, 1) }}%</strong></div>
                                            </div>
                                        </div>
                                        <hr>
                                        <p class="mb-0">{{ $res['message'] }}</p>
                                        <div class="mt-3 small text-muted">ระบบจะส่งข้อมูลไปให้ QA Supervisor ทวนสอบด้วยสายตามนุษย์ก่อนตัดสินใจสั่งแก้</div>
                                    </div>
                                @endif
                            @else
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle-fill"></i> ไม่สามารถเชื่อมต่อกับ Python AI Service ได้ (พอร์ต 8001)
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
