<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'HygienePro') }} - Login</title>

        <!-- Bootstrap Icons CDN -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <!-- Scripts -->
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])

        <style>
            .login-page {
                min-height: 100vh;
                display: flex;
            }
            
            .login-brand {
                background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 3rem;
                color: white;
            }
            
            .login-brand .logo-icon {
                width: 100px;
                height: 100px;
                background: rgba(255, 255, 255, 0.15);
                border-radius: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 2rem;
            }
            
            .login-brand .logo-icon i {
                font-size: 3rem;
                color: #60a5fa;
            }
            
            .login-brand h1 {
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 0.5rem;
            }
            
            .login-brand p {
                font-size: 1.1rem;
                opacity: 0.8;
            }
            
            .login-form-section {
                flex: 1;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 2rem;
                background: #f8fafc;
            }
            
            .login-form-card {
                width: 100%;
                max-width: 420px;
                background: white;
                border-radius: 24px;
                padding: 3rem;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            }
            
            .login-form-card h2 {
                font-size: 1.75rem;
                font-weight: 700;
                color: #1e293b;
                margin-bottom: 2rem;
                text-align: center;
            }
            
            .form-floating {
                margin-bottom: 1rem;
            }
            
            .form-floating .form-control {
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                padding: 1rem 1rem 1rem 3rem;
                height: 56px;
            }
            
            .form-floating .form-control:focus {
                border-color: #2563eb;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
            }
            
            .form-floating label {
                padding-left: 3rem;
            }
            
            .input-icon {
                position: absolute;
                left: 1rem;
                top: 50%;
                transform: translateY(-50%);
                color: #94a3b8;
                z-index: 5;
            }
            
            .btn-login {
                width: 100%;
                padding: 0.875rem;
                font-size: 1rem;
                font-weight: 600;
                border-radius: 12px;
                background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
                border: none;
                color: white;
                margin-top: 1.5rem;
                transition: all 0.3s ease;
            }
            
            .btn-login:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
            }
            
            .password-toggle {
                position: absolute;
                right: 1rem;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: #94a3b8;
                cursor: pointer;
                z-index: 5;
            }
            
            .password-toggle:hover {
                color: #64748b;
            }
            
            @media (max-width: 992px) {
                .login-brand {
                    display: none;
                }
                
                .login-form-section {
                    padding: 1rem;
                }
                
                .login-form-card {
                    padding: 2rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-page">
            <!-- Brand Section -->
            <div class="login-brand">
                <div class="logo-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h1>HygienePro</h1>
                <p>ระบบตรวจสอบความสะอาดอัจฉริยะ</p>
                <p class="mt-4" style="opacity: 0.6; font-size: 0.9rem;">
                    Smart Hygiene Inspection System
                </p>
            </div>

            <!-- Form Section -->
            <div class="login-form-section">
                <div class="login-form-card">
                    <h2>เข้าสู่ระบบ</h2>

                    <!-- Session Status -->
                    @if (session('status'))
                        <div class="alert alert-success mb-3" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <!-- Login (Email or Employee Code) -->
                        <div class="form-floating position-relative">
                            <i class="bi bi-person-badge input-icon"></i>
                            <input id="login" type="text" 
                                   class="form-control @error('login') is-invalid @enderror" 
                                   name="login" 
                                   value="{{ old('login') }}" 
                                   placeholder="อีเมล หรือ รหัสพนักงาน"
                                   required autofocus autocomplete="username">
                            <label for="login">อีเมล หรือ รหัสพนักงาน</label>
                            @error('login')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div class="form-floating position-relative">
                            <i class="bi bi-lock input-icon"></i>
                            <input id="password" type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   name="password" 
                                   placeholder="รหัสผ่าน"
                                   required autocomplete="current-password">
                            <label for="password">รหัสผ่าน</label>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                            @error('password')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Remember Me -->
                        <div class="form-check mt-3">
                            <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                            <label for="remember_me" class="form-check-label text-secondary">
                                จดจำฉัน
                            </label>
                        </div>

                        <button type="submit" class="btn btn-login">
                            <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
                        </button>

                        @if (Route::has('password.request'))
                            <div class="text-center mt-3">
                                <a class="text-decoration-none small text-muted" href="{{ route('password.request') }}">
                                    ลืมรหัสผ่าน?
                                </a>
                            </div>
                        @endif
                    </form>

                    <div class="text-center mt-4 pt-4 border-top text-muted small">
                        &copy; {{ date('Y') }} HygienePro by Enterprise Matrix System
                    </div>
                </div>
            </div>
        </div>

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
        </script>
    </body>
</html>
