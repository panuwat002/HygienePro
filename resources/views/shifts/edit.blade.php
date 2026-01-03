<x-app-layout>
    @section('header', 'แก้ไขกะการทำงาน')

    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="{{ route('shifts.index') }}" class="text-decoration-none text-muted mb-2 d-inline-block">
                    <i class="bi bi-arrow-left me-1"></i>กลับไปรายการกะ
                </a>
                <h5 class="fw-bold">แก้ไข: {{ $shift->shift_name }}</h5>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <form action="{{ route('shifts.update', $shift->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label fw-bold">ชื่อกะ <span class="text-danger">*</span></label>
                                <input type="text" name="shift_name" class="form-control" value="{{ $shift->shift_name }}" required>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">เวลาเริ่มงาน <span class="text-danger">*</span></label>
                                    <input type="time" name="start_time" class="form-control" value="{{ date('H:i', strtotime($shift->start_time)) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">เวลาเลิกงาน <span class="text-danger">*</span></label>
                                    <input type="time" name="end_time" class="form-control" value="{{ date('H:i', strtotime($shift->end_time)) }}" required>
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
