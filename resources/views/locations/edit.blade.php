<x-app-layout>
    @section('header', 'แก้ไขจุดประจำการ')

    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="{{ route('locations.index') }}" class="text-decoration-none text-muted mb-2 d-inline-block">
                    <i class="bi bi-arrow-left me-1"></i>กลับไปรายการจุดประจำการ
                </a>
                <h5 class="fw-bold">แก้ไข: {{ $location->location_name }}</h5>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <form action="{{ route('locations.update', $location->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label fw-bold">ชื่อจุดประจำการ <span class="text-danger">*</span></label>
                                <input type="text" name="location_name" class="form-control" value="{{ $location->location_name }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">คำอธิบาย</label>
                                <textarea name="description" class="form-control" rows="3">{{ $location->description }}</textarea>
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
