<x-app-layout>
    @section('header', 'เพิ่มจุดประจำการใหม่')

    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="{{ route('locations.index') }}" class="text-decoration-none text-muted mb-2 d-inline-block">
                    <i class="bi bi-arrow-left me-1"></i>กลับไปรายการจุดประจำการ
                </a>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <form action="{{ route('locations.store') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold">ชื่อจุดประจำการ <span class="text-danger">*</span></label>
                                <input type="text" name="location_name" class="form-control" placeholder="เช่น โรงอัดน้ำ, คลังสินค้า, จุดบรรจุ" required>
                            </div>

                            {{-- Who runs the area. This is what decides who a finding
                                 raised in it is handed to; without it the system has
                                 nothing to go on but whoever walked the round, which
                                 is always QA. --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold">แผนกที่ดูแลพื้นที่นี้</label>
                                <select name="department_id" class="form-select">
                                    <option value="">-- ยังไม่ระบุ --</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>
                                            {{ $department->dept_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    ข้อบกพร่องที่พบในพื้นที่นี้จะถูกส่งให้แผนกนี้เป็นผู้แก้ไข
                                    หากไม่ระบุ ระบบจะใช้แผนกของผู้ตรวจเหมือนเดิม
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">คำอธิบาย</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="รายละเอียดหรือข้อมูลเพิ่มเติม"></textarea>
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
