<x-app-layout>
    @section('header', 'แก้ไขหมวดหมู่')

    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="{{ route('checkpoint-categories.index') }}" class="text-decoration-none text-muted mb-2 d-inline-block">
                    <i class="bi bi-arrow-left me-1"></i>กลับไปรายการหมวดหมู่
                </a>
                <h5 class="fw-bold">แก้ไข: {{ $checkpoint_category->name }}</h5>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <form action="{{ route('checkpoint-categories.update', $checkpoint_category->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label fw-bold">ชื่อหมวดหมู่ <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ $checkpoint_category->name }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">ไอคอน (Bootstrap Icon Name)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-{{ $checkpoint_category->icon ?? 'tag' }}"></i></span>
                                    <input type="text" name="icon" class="form-control" value="{{ $checkpoint_category->icon ?? 'tag' }}">
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
