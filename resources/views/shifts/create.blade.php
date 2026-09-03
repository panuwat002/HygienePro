<x-app-layout>
    @section('header', 'เพิ่มกะการทำงานใหม่')

    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="{{ route('shifts.index') }}" class="text-decoration-none text-muted mb-2 d-inline-block">
                    <i class="bi bi-arrow-left me-1"></i>กลับไปรายการกะ
                </a>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <form action="{{ route('shifts.store') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold">ชื่อกะ <span class="text-danger">*</span></label>
                                <input type="text" name="shift_name" class="form-control" placeholder="เช่น กะเช้า, กะดึก, กะโอที" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">ประเภทกะ</label>
                                <select name="shift_type" class="form-select">
                                    <option value="">-- ให้ระบบกำหนดจากชื่อกะและเวลาเริ่มงาน --</option>
                                    @foreach(\App\Models\Shift::types() as $type)
                                        <option value="{{ $type }}" {{ old('shift_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">ใช้จัดกลุ่มกะตอนเริ่มตรวจแบบอัตโนมัติ เช่น "กะบ่าย 17.00-02.00" อยู่ในกลุ่มกะบ่าย</small>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">เวลาเริ่มงาน <span class="text-danger">*</span></label>
                                    <input type="time" name="start_time" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">เวลาเลิกงาน <span class="text-danger">*</span></label>
                                    <input type="time" name="end_time" class="form-control" required>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
                                    <i class="bi bi-save me-2"></i>บันทึกข้อมูล
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
