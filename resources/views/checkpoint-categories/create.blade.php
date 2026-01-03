<x-app-layout>
    @section('header', 'เพิ่มหมวดหมู่ใหม่')

    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="{{ route('checkpoint-categories.index') }}" class="text-decoration-none text-muted mb-2 d-inline-block">
                    <i class="bi bi-arrow-left me-1"></i>กลับไปรายการหมวดหมู่
                </a>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <form action="{{ route('checkpoint-categories.store') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold">ชื่อหมวดหมู่ <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="เช่น สุขอนามัยส่วนบุคคล, สภาพแวดล้อม" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">ไอคอน (Bootstrap Icon Name)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-tag"></i></span>
                                    <input type="text" name="icon" class="form-control" placeholder="เช่น person, house, tool (ไม่ต้องใส่ bi-)" value="tag">
                                </div>
                                <div class="form-text small text-muted">ค้นหาไอคอนได้ที่ <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a></div>
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
