<x-app-layout>
    @section('header', 'สร้างผู้ใช้งานใหม่ (Create New User)')

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="{{ route('users.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-bold">ชื่อ-นามสกุล (Full Name)</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">รหัสพนักงาน (Employee Code)</label>
                            <input type="text" name="employee_code" class="form-control @error('employee_code') is-invalid @enderror" value="{{ old('employee_code') }}">
                            @error('employee_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-muted">ตัวเลือก: สามารถใช้ login เข้าสู่ระบบแทนอีเมลได้</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">อีเมล (Email)</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">สังกัดแผนก (Department)</label>
                            <select name="department_id" class="form-select @error('department_id') is-invalid @enderror">
                                <option value="">-- ไม่ระบุ / ส่วนกลาง --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->dept_name }} ({{ $dept->dept_code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('department_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-muted">จำเป็นสำหรับ Supervisor/Staff เพื่อกำหนดสิทธิ์การตรวจ/จัดการ</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">สิทธิ์การใช้งาน (Role)</label>
                            <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                                <option value="">-- เลือกสิทธิ์ --</option>
                                <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin (ผู้ดูแลระบบ)</option>
                                <option value="manager" {{ old('role') == 'manager' ? 'selected' : '' }}>Manager (ผู้จัดการ)</option>
                                <option value="supervisor" {{ old('role') == 'supervisor' ? 'selected' : '' }}>Supervisor (หัวหน้างาน)</option>
                                <option value="staff" {{ old('role') == 'staff' ? 'selected' : '' }}>Staff (พนักงานทั่วไป)</option>
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <label class="form-label fw-bold">รหัสผ่าน (Password)</label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">ยืนยันรหัสผ่าน (Confirm Password)</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('users.index') }}" class="btn btn-light text-muted">ยกเลิก</a>
                            <button type="submit" class="btn btn-primary px-4">บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
