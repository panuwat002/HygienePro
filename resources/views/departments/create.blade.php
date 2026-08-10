<x-app-layout>
    @section('header', 'สร้างแผนกใหม่')

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-5">
                    <form method="POST" action="{{ route('departments.store') }}">
                        @csrf

                        <div class="mb-4">
                            <label class="form-label fw-bold">ชื่อแผนก (Department Name)</label>
                            <input type="text" name="dept_name" class="form-control form-control-lg @error('dept_name') is-invalid @enderror" value="{{ old('dept_name') }}" placeholder="เช่น Production, Quality Assurance" required autofocus>
                            @error('dept_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">รหัสแผนก (Code)</label>
                            <input type="text" name="dept_code" class="form-control form-control-lg @error('dept_code') is-invalid @enderror" value="{{ old('dept_code') }}" placeholder="เช่น PD, QA, HR" maxlength="10" required>
                            @error('dept_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">รหัสย่อภาษาอังกฤษ (เช่น QA, PD)</div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-bold">ประเภทการมองเห็น (Visibility Type)</label>
                            <select name="visibility_type" class="form-select form-select-lg @error('visibility_type') is-invalid @enderror" required>
                                <option value="isolated" selected>Isolated (เฉพาะส่วน - เห็นข้อมูลเฉพาะแผนกตัวเอง)</option>
                                <option value="global">Global (ส่วนกลาง - เห็น/เข้าถึงได้ทั่วทั้งระบบ)</option>
                            </select>
                            @error('visibility_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-muted">Global สำหรับแผนก Support เช่น QA, HR, IT / Isolated สำหรับแผนกปฏิบัติการ เช่น รายผลิต</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('departments.index') }}" class="btn btn-light text-muted btn-lg px-4 rounded-pill">
                                <i class="bi bi-arrow-left me-2"></i>ยกเลิก
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg px-5 rounded-pill shadow-sm">
                                <i class="bi bi-save me-2"></i>บันทึกข้อมูล
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
