<x-app-layout>
    @section('header', 'เพิ่มเครื่องจักรใหม่')

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="{{ route('machines.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        
                        <h5 class="fw-bold mb-4 text-primary"><i class="bi bi-plus-circle me-2"></i>ข้อมูลเบื้องต้น</h5>

                        <div class="mb-3">
                            <label for="location_id" class="form-label fw-bold small">สังกัดจุดประจำการ (Location)</label>
                            <select class="form-select @error('location_id') is-invalid @enderror" id="location_id" name="location_id" required>
                                <option value="" selected disabled>-- เลือกสถานที่ติดตั้ง --</option>
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->location_name }}</option>
                                @endforeach
                            </select>
                            @error('location_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label for="name" class="form-label fw-bold small">ชื่อเครื่องจักร / พื้นที่ย่อย</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="Ex. Machine #1, Line A" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label fw-bold small">รายละเอียดเพิ่มเติม</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="ระบุรายละเอียด, รุ่น, หรือข้อมูลจำเพาะ...">{{ old('description') }}</textarea>
                        </div>

                        <div class="mb-4">
                            <label for="image" class="form-label fw-bold small">รูปภาพประกอบ (ถ้ามี)</label>
                            <input class="form-control" type="file" id="image" name="image" accept="image/*">
                            <div class="form-text">รองรับไฟล์ภาพ JPG, PNG ขนาดไม่เกิน 2MB</div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('machines.index') }}" class="btn btn-light px-4">ยกเลิก</a>
                            <button type="submit" class="btn btn-primary px-4 bg-gradient shadow-sm">
                                <i class="bi bi-save me-2"></i>บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
