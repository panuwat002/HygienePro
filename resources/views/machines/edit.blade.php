<x-app-layout>
    @section('header', 'แก้ไขข้อมูลเครื่องจักร (Edit Machine)')

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="{{ route('machines.update', $machine->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        
                        <h5 class="fw-bold mb-4 text-primary"><i class="bi bi-pencil-square me-2"></i>แก้ไขข้อมูล</h5>

                        <div class="mb-3">
                            <label for="location_id" class="form-label fw-bold small">สังกัดจุดประจำการ (Location)</label>
                            <select class="form-select @error('location_id') is-invalid @enderror" id="location_id" name="location_id" required>
                                <option value="" disabled>-- เลือกสถานที่ติดตั้ง --</option>
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}" {{ $machine->location_id == $loc->id ? 'selected' : '' }}>{{ $loc->location_name }}</option>
                                @endforeach
                            </select>
                            @error('location_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label for="name" class="form-label fw-bold small">ชื่อเครื่องจักร / พื้นที่ย่อย</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $machine->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="code" class="form-label fw-bold small">รหัสทรัพย์สิน (Asset Code)</label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $machine->code) }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label fw-bold small">รายละเอียดเพิ่มเติม</label>
                            <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $machine->description) }}</textarea>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <label for="image" class="form-label fw-bold small mb-0 me-3">รูปภาพประกอบ</label>
                                @if($machine->image)
                                    <a href="{{ asset('storage/' . $machine->image) }}" target="_blank" class="badge bg-light text-primary text-decoration-none border">
                                        <i class="bi bi-eye me-1"></i> ดูรูปปัจจุบัน
                                    </a>
                                @endif
                            </div>
                            <input class="form-control" type="file" id="image" name="image" accept="image/*">
                            <div class="form-text">อัปโหลดไฟล์ใหม่เพื่อเปลี่ยนรูปเดิม (รองรับ JPG, PNG / Max 2MB)</div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ $machine->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">ใช้งาน (Active)</label>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="{{ route('machines.index') }}" class="btn btn-light px-4">ยกเลิก</a>
                                <button type="submit" class="btn btn-primary px-4 bg-gradient shadow-sm">
                                    <i class="bi bi-save me-2"></i>บันทึกการแก้ไข
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
