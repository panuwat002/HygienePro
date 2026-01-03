<x-app-layout>
    @section('header', 'เพิ่มพนักงานใหม่')

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">ข้อมูลพนักงาน</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('employees.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        
                        <!-- Profile Image -->
                        <div class="mb-4 text-center">
                            <div class="position-relative d-inline-block">
                                <div class="rounded-circle bg-light d-flex justify-content-center align-items-center border" style="width: 120px; height: 120px; overflow: hidden;" id="image-preview-container">
                                    <i class="bi bi-person-fill text-muted" style="font-size: 4rem;" id="default-icon"></i>
                                    <img id="image-preview" src="#" class="w-100 h-100 object-fit-cover d-none">
                                </div>
                                <label for="profile_image" class="btn btn-sm btn-primary rounded-circle position-absolute bottom-0 end-0 shadow-sm" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-camera-fill"></i>
                                </label>
                                <input type="file" name="profile_image" id="profile_image" class="d-none" accept="image/*" onchange="previewImage(this)">
                            </div>
                            <div class="mt-2 text-muted small">รูปโปรไฟล์ (ถ้ามี)</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">คำนำหน้า</label>
                                <select name="prefix" class="form-select" required>
                                    <option value="นาย">นาย</option>
                                    <option value="นาง">นาง</option>
                                    <option value="นางสาว">นางสาว</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">ชื่อจริง</label>
                                <input type="text" name="fname" class="form-control" required placeholder="สมชาย">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">นามสกุล</label>
                                <input type="text" name="lname" class="form-control" required placeholder="ใจดี">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">รหัสพนักงาน</label>
                                <input type="text" name="employee_id" class="form-control" required placeholder="EMP001">
                                <div class="form-text">ใช้สำหรับระบุตัวตนและสร้าง QR Code</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">แผนก</label>
                                <select name="department_id" class="form-select" required>
                                    <option value="">-- เลือกแผนก --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">ระดับ (Level)</label>
                                <input type="text" name="level" class="form-control" placeholder="เช่น L1, Supervisor, etc.">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">กะการทำงาน</label>
                                <select name="shift_id" class="form-select">
                                    <option value="">-- ไม่ระบุ --</option>
                                    @foreach($shifts as $shift)
                                        <option value="{{ $shift->id }}">{{ $shift->shift_name }} ({{ date('H:i', strtotime($shift->start_time)) }} - {{ date('H:i', strtotime($shift->end_time)) }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">จุดประจำการ</label>
                                <select name="location_id" class="form-select">
                                    <option value="">-- ไม่ระบุ --</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->location_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-5">
                            <a href="{{ route('employees.index') }}" class="btn btn-light text-muted">ยกเลิก</a>
                            <button type="submit" class="btn btn-primary-custom px-4">บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('default-icon').classList.add('d-none');
                    document.getElementById('image-preview').src = e.target.result;
                    document.getElementById('image-preview').classList.remove('d-none');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
    @endpush
</x-app-layout>
