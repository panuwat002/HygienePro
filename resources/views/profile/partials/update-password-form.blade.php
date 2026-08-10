<section>
    <header class="mb-4">
        <h2 class="h4 text-dark fw-bold mb-1">
            {{ __('เปลี่ยนรหัสผ่าน (Update Password)') }}
        </h2>

        <p class="text-muted small">
            {{ __('ตั้งรหัสผ่านใหม่ที่คาดเดาได้ยากเพื่อความปลอดภัยของบัญชี') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="needs-validation">
        @csrf
        @method('put')

        <div class="mb-3">
            <label for="update_password_current_password" class="form-label fw-bold">{{ __('รหัสผ่านปัจจุบัน') }}</label>
            <input id="update_password_current_password" name="current_password" type="password" class="form-control" autocomplete="current-password">
            @error('current_password', 'updatePassword')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password" class="form-label fw-bold">{{ __('รหัสผ่านใหม่') }}</label>
            <input id="update_password_password" name="password" type="password" class="form-control" autocomplete="new-password">
            @error('password', 'updatePassword')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="update_password_password_confirmation" class="form-label fw-bold">{{ __('ยืนยันรหัสผ่านใหม่') }}</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password">
            @error('password_confirmation', 'updatePassword')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary px-4 fw-bold rounded-pill shadow-sm">{{ __('เปลี่ยนรหัสผ่าน') }}</button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-success small mb-0 fw-medium"
                ><i class="bi bi-check-circle-fill me-1"></i>{{ __('เปลี่ยนรหัสผ่านสำเร็จ') }}</p>
            @endif
        </div>
    </form>
</section>
