<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Hygiene Checklist') }}</title>

        <!-- Bootstrap Icons CDN -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <!-- Scripts -->
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    </head>
    <body class="bg-light">
        <div class="container">
            <div class="row min-vh-100 justify-content-center align-items-center">
                <div class="col-md-5">
                    <div class="text-center mb-4">
                        <h1 class="display-5 fw-bold text-primary">
                            <i class="bi bi-shield-check"></i> Hygiene Check
                        </h1>
                        <p class="text-secondary">Digital Inspection System</p>
                    </div>

                    <div class="card shadow rounded-3 border-0">
                        <div class="card-body p-4">
                            {{ $slot }}
                        </div>
                    </div>

                    <div class="text-center mt-3 text-muted small">
                        &copy; {{ date('Y') }} Enterprise Matrix System
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
