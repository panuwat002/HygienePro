<x-app-layout>
    @section('header', 'เพิ่มจุดตรวจใหม่')

    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="{{ route('checkpoints.index') }}" class="text-decoration-none text-muted mb-2 d-inline-block">
                    <i class="bi bi-arrow-left me-1"></i>กลับไปรายการจุดตรวจ
                </a>
            </div>

            <div class="row">
                <div class="col-md-7">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <form action="{{ route('checkpoints.store') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold">หมวดหมู่จุดตรวจ</label>
                                <select name="category_id" class="form-select">
                                    <option value="">-- ไม่ระบุหมวดหมู่ --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">ชื่อจุดตรวจ <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" placeholder="เช่น เล็บสะอาดและสั้น, สวมหมวกคลุมผมเรียบร้อย" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">คำอธิบาย</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="ระบุรายละเอียด หรือ สิ่งที่ต้องการให้ตรวจสอบ"></textarea>
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
