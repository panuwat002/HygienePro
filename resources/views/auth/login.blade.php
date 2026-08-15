<x-guest-layout>
    <div class="login-card">
        <h2 class="login-title">เข้าสู่ระบบ</h2>

        <!-- Session Status -->
        @if (session('status'))
            <div class="alert alert-success mb-3" role="alert" style="border-radius: 12px; font-size: 0.95rem;">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Login (Email or Employee Code) -->
            <div class="form-floating">
                <i class="bi bi-person-badge input-icon"></i>
                <input id="login" type="text" 
                       class="form-control @error('login') is-invalid @enderror" 
                       name="login" 
                       value="{{ old('login') }}" 
                       placeholder="อีเมล หรือ รหัสพนักงาน"
                       required autofocus autocomplete="username">
                <label for="login">อีเมล หรือ รหัสพนักงาน</label>
                @error('login')
                    <div class="invalid-feedback ps-2">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <!-- Password -->
            <div class="form-floating">
                <i class="bi bi-lock input-icon"></i>
                <input id="password" type="password" 
                       class="form-control @error('password') is-invalid @enderror" 
                       name="password" 
                       placeholder="รหัสผ่าน"
                       required autocomplete="current-password">
                <label for="password">รหัสผ่าน</label>
                <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle Password">
                    <i class="bi bi-eye" id="toggleIcon"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback ps-2">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <!-- Remember Me -->
            <div class="form-check mt-3 d-flex align-items-center">
                <input id="remember_me" type="checkbox" class="form-check-input mt-0" name="remember">
                <label for="remember_me" class="form-check-label ms-2">
                    จดจำฉัน
                </label>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
            </button>

            @if (Route::has('password.request'))
                <div class="text-center mt-4">
                    <a class="link-primary-custom small" href="{{ route('password.request') }}">
                        ลืมรหัสผ่าน?
                    </a>
                </div>
            @endif
        </form>

        <div class="text-center mt-4 pt-4 border-top text-muted small" style="opacity: 0.7;">
            &copy; {{ date('Y') }} HygienePro by Enterprise Matrix System
        </div>
    </div>

    @push('scripts')
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }
        
        // Loading State for Login Form
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form:not(.no-loading)');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const btn = this.querySelector('button[type="submit"]');
                    if (btn && !btn.classList.contains('no-loading')) {
                        // Store original width to prevent button shrinking/growing
                        const width = btn.offsetWidth;
                        btn.style.width = width + 'px';
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> กำลังเข้าสู่ระบบ...';
                        btn.style.opacity = '0.8';
                    }
                });
            });
        });
    </script>
    @endpush
</x-guest-layout>
