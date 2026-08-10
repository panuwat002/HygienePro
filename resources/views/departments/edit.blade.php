<x-app-layout>
    @section('header', 'แก้ไขแผนก')

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-5">
                    <form method="POST" action="{{ route('departments.update', $department->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label class="form-label fw-bold">ชื่อแผนก (Department Name)</label>
                            <input type="text" name="dept_name" class="form-control form-control-lg @error('dept_name') is-invalid @enderror" value="{{ old('dept_name', $department->dept_name) }}" required autofocus>
                            @error('dept_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">รหัสแผนก (Code)</label>
                            <input type="text" name="dept_code" class="form-control form-control-lg @error('dept_code') is-invalid @enderror" value="{{ old('dept_code', $department->dept_code) }}" maxlength="10" required>
                            @error('dept_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-bold">ประเภทการมองเห็น (Visibility Type)</label>
                            <select name="visibility_type" class="form-select form-select-lg @error('visibility_type') is-invalid @enderror" required>
                                <option value="isolated" {{ old('visibility_type', $department->visibility_type) == 'isolated' ? 'selected' : '' }}>Isolated (เฉพาะส่วน - เห็นข้อมูลเฉพาะแผนกตัวเอง)</option>
                                <option value="global" {{ old('visibility_type', $department->visibility_type) == 'global' ? 'selected' : '' }}>Global (ส่วนกลาง - เห็น/เข้าถึงได้ทั่วทั้งระบบ)</option>
                            </select>
                            @error('visibility_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-bold">หัวหน้าแผนก (Department Manager)</label>
                            <select name="manager_id" class="form-select form-select-lg @error('manager_id') is-invalid @enderror">
                                <option value="">-- ไม่ระบุ (None) --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ (old('manager_id', $department->manager?->id) == $user->id) ? 'selected' : '' }}>
                                        {{ $user->name }} {{ $user->department_id ? '('.($user->department->dept_name ?? 'ไม่มีแผนก').')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted"><i class="bi bi-info-circle me-1"></i>พนักงานที่ถูกเลือกจะถูกปรับตำแหน่งเป็น Manager (ระดับ 5) ในแผนกนี้ทันที และหัวหน้าคนเดิมจะถูกปรับลงเป็น Staff</div>
                            @error('manager_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('departments.index') }}" class="btn btn-light text-muted btn-lg px-4 rounded-pill">
                                <i class="bi bi-arrow-left me-2"></i>ยกเลิก
                            </a>
                            <button type="submit" class="btn btn-warning btn-lg px-5 rounded-pill shadow-sm text-dark fw-bold">
                                <i class="bi bi-save me-2"></i>บันทึกการแก้ไข
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
