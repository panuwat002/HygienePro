<x-app-layout>
    @section('header', 'แก้ไขจุดตรวจ')

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
                        <form action="{{ route('checkpoints.update', $checkpoint->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label fw-bold">หมวดหมู่จุดตรวจ</label>
                                <select name="category_id" class="form-select">
                                    <option value="">-- ไม่ระบุหมวดหมู่ --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" @selected($checkpoint->category_id == $cat->id)>{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">ชื่อจุดตรวจ <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" value="{{ $checkpoint->title }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">คำอธิบาย</label>
                                <textarea name="description" class="form-control" rows="3">{{ $checkpoint->description }}</textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-success"><i class="bi bi-check-circle-fill me-1"></i>รูปตัวอย่างที่ถูกต้อง (Good)</label>
                                        @if($checkpoint->image_good)
                                            <div class="mb-2 p-1 border rounded">
                                                <img src="{{ asset('storage/'.$checkpoint->image_good) }}" class="img-fluid rounded" style="max-height: 150px;">
                                            </div>
                                        @endif
                                        <input type="file" name="image_good" class="form-control" accept="image/*">
                                        <div class="form-text small">อัปโหลดใหม่เพื่อเปลี่ยนรูปเดิม</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-danger"><i class="bi bi-x-circle-fill me-1"></i>รูปตัวอย่างที่ไม่ถูกต้อง (Bad)</label>
                                        @if($checkpoint->image_bad)
                                            <div class="mb-2 p-1 border rounded">
                                                <img src="{{ asset('storage/'.$checkpoint->image_bad) }}" class="img-fluid rounded" style="max-height: 150px;">
                                            </div>
                                        @endif
                                        <input type="file" name="image_bad" class="form-control" accept="image/*">
                                        <div class="form-text small">อัปโหลดใหม่เพื่อเปลี่ยนรูปเดิม</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
                                    <i class="bi bi-save me-2"></i>บันทึกการแก้ไข
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
