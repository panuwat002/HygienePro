<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'HygienePro') }} - Login</title>

        <!-- Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        
        <!-- Bootstrap Icons CDN -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <!-- Scripts -->
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])

        <style>
            :root {
                --primary-50: #eff6ff;
                --primary-100: #dbeafe;
                --primary-500: #3b82f6;
                --primary-600: #2563eb;
                --primary-700: #1d4ed8;
                --primary-900: #1e3a8a;
                --surface-color: #ffffff;
                --bg-color: #f8fafc;
                --text-main: #0f172a;
                --text-muted: #64748b;
                --border-color: #e2e8f0;
            }

            body {
                font-family: 'Inter', 'Prompt', sans-serif;
                background-color: var(--bg-color);
                color: var(--text-main);
                margin: 0;
                padding: 0;
            }

            .login-page {
                min-height: 100vh;
                display: flex;
            }
            
            /* Left Brand Section */
            .login-brand {
                flex: 1.2;
                background: linear-gradient(135deg, var(--primary-900) 0%, var(--primary-600) 100%);
                position: relative;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 3rem;
                color: white;
            }

            /* Abstract Background Decoration */
            .login-brand::before {
                content: '';
                position: absolute;
                top: -20%;
                left: -10%;
                width: 70%;
                height: 70%;
                background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
                border-radius: 50%;
            }
            .login-brand::after {
                content: '';
                position: absolute;
                bottom: -20%;
                right: -10%;
                width: 60%;
                height: 60%;
                background: radial-gradient(circle, rgba(59, 130, 246, 0.4) 0%, rgba(59, 130, 246, 0) 70%);
                border-radius: 50%;
            }
            
            .login-brand-content {
                position: relative;
                z-index: 2;
                text-align: center;
                animation: fadeUpIn 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }

            .logo-icon {
                width: 90px;
                height: 90px;
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 2rem;
                backdrop-filter: blur(10px);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
                transition: transform 0.3s ease;
            }

            .logo-icon:hover {
                transform: scale(1.05);
            }
            
            .logo-icon i {
                font-size: 2.5rem;
                color: #fff;
                filter: drop-shadow(0px 2px 4px rgba(0,0,0,0.2));
            }
            
            .login-brand h1 {
                font-size: 2.75rem;
                font-weight: 700;
                margin-bottom: 0.5rem;
                letter-spacing: -0.02em;
            }
            
            .login-brand p {
                font-size: 1.15rem;
                color: var(--primary-100);
                margin-bottom: 0.2rem;
                font-family: 'Prompt', sans-serif;
            }
            
            .login-brand .subtitle-en {
                font-size: 0.9rem;
                opacity: 0.7;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                margin-top: 1.5rem;
            }
            
            /* Right Form Section */
            .login-form-section {
                flex: 1;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 2rem;
                background: var(--bg-color);
            }
            
            .login-form-wrapper {
                width: 100%;
                max-width: 440px;
                animation: fadeUpIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                animation-delay: 0.1s;
                opacity: 0;
            }

            @keyframes fadeUpIn {
                0% { opacity: 0; transform: translateY(20px); }
                100% { opacity: 1; transform: translateY(0); }
            }
            
            @media (max-width: 992px) {
                .login-brand { display: none; }
                .login-form-section { padding: 1.5rem; }
            }

            /* Global UI Form Styles applied to the slot content */
            .login-card {
                background: var(--surface-color);
                border-radius: 24px;
                padding: 3rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 20px 25px -5px rgba(0, 0, 0, 0.03);
                border: 1px solid rgba(0, 0, 0, 0.04);
            }

            .login-title {
                font-size: 1.75rem;
                font-weight: 700;
                color: var(--text-main);
                margin-bottom: 2.5rem;
                text-align: center;
                font-family: 'Prompt', sans-serif;
            }

            .form-floating { margin-bottom: 1.25rem; position: relative; }
            
            .form-floating > .form-control,
            .form-floating > .form-control-plaintext {
                padding: 1rem 1rem 1rem 3.2rem;
                height: calc(3.5rem + 2px);
                line-height: 1.25;
            }

            .form-floating > label {
                padding: 1rem 1rem 1rem 3.2rem;
                color: var(--text-muted);
                transition: opacity .2s ease-in-out, transform .2s ease-in-out;
            }

            .form-floating > .form-control:focus ~ label,
            .form-floating > .form-control:not(:placeholder-shown) ~ label {
                opacity: 0.8;
                transform: scale(.85) translateY(-.6rem) translateX(0.15rem);
                color: var(--primary-600);
            }

            .form-floating .form-control {
                border-radius: 12px;
                border: 1px solid var(--border-color);
                font-size: 1rem;
                background-color: var(--surface-color);
                transition: all 0.2s ease;
            }
            
            .form-floating .form-control:focus {
                border-color: var(--primary-500);
                box-shadow: 0 0 0 4px var(--primary-100);
                background-color: var(--surface-color);
                outline: 0;
            }
            
            .input-icon {
                position: absolute;
                left: 1.25rem;
                top: 50%;
                transform: translateY(-50%);
                color: var(--text-muted);
                z-index: 5;
                font-size: 1.15rem;
                transition: color 0.2s ease;
            }

            .form-control:focus ~ .input-icon {
                color: var(--primary-600);
            }
            
            .btn-login {
                width: 100%;
                padding: 0.875rem;
                font-size: 1rem;
                font-weight: 600;
                border-radius: 12px;
                background: var(--primary-600);
                border: none;
                color: white;
                margin-top: 1.5rem;
                font-family: 'Prompt', sans-serif;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
            }
            
            .btn-login:hover {
                background: var(--primary-700);
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
                color: white;
            }

            .btn-login:active {
                transform: translateY(0);
            }

            .password-toggle {
                position: absolute;
                right: 1.25rem;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: var(--text-muted);
                cursor: pointer;
                z-index: 5;
                transition: color 0.2s ease;
                padding: 0;
            }
            
            .password-toggle:hover {
                color: var(--text-main);
            }

            .form-check-input {
                width: 1.25em;
                height: 1.25em;
                border-radius: 6px;
                border: 1px solid var(--border-color);
                cursor: pointer;
            }

            .form-check-input:checked {
                background-color: var(--primary-600);
                border-color: var(--primary-600);
            }

            .form-check-input:focus {
                box-shadow: 0 0 0 4px var(--primary-100);
            }

            .form-check-label {
                padding-top: 0.15rem;
                padding-left: 0.25rem;
                font-size: 0.95rem;
                cursor: pointer;
                user-select: none;
                color: var(--text-muted);
            }

            .link-primary-custom {
                color: var(--primary-600);
                text-decoration: none;
                font-weight: 500;
                transition: color 0.2s;
            }

            .link-primary-custom:hover {
                color: var(--primary-700);
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="login-page">
            <!-- Brand Section -->
            <div class="login-brand">
                <div class="login-brand-content">
                    <div class="logo-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h1>HygienePro</h1>
                    <p>ระบบตรวจสอบความสะอาดอัจฉริยะ</p>
                    <p class="subtitle-en">
                        Smart Hygiene Inspection System
                    </p>
                </div>
            </div>

            <!-- Form Section -->
            <div class="login-form-section">
                <div class="login-form-wrapper">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
