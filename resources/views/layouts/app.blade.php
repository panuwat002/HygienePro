<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'HygienePro') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Overlay for Mobile -->
        <div id="sidebar-overlay" onclick="document.body.classList.remove('sidebar-toggled'); document.getElementById('sidebar-wrapper').classList.remove('toggled'); document.getElementById('sidebar-overlay').classList.remove('active');"></div>

        <!-- Sidebar -->
        <div class="bg-white" id="sidebar-wrapper">
            <div class="sidebar-heading d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2 ps-2">
                    <div class="rounded-3 bg-primary bg-opacity-10 p-2 d-flex justify-content-center align-items-center">
                        <i class="bi bi-shield-check text-primary" style="font-size: 1.5rem;"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="fw-bold text-dark" style="line-height: 1; font-size: 1.1rem; letter-spacing: -0.5px;">HygienePro</span>
                        <small class="text-muted" style="font-size: 0.65rem; font-weight: 500;">ENTERPRISE 4.0</small>
                    </div>
                </div>
                <!-- Mobile Toggle (Collapse) -->
                <button class="btn btn-link link-secondary d-lg-none p-0 me-2" id="sidebar-toggle-mobile" onclick="toggleSidebar()" style="position: relative; z-index: 1051; cursor: pointer; min-width: 44px; min-height: 44px;">
                    <i class="bi bi-chevron-bar-left" style="font-size: 1.5rem;"></i>
                </button>
                
                <!-- Desktop Toggle (Collapse) -->
                <button class="btn btn-link link-secondary d-none d-lg-block p-0 me-2" id="sidebar-toggle-desktop" onclick="toggleDesktopSidebar()">
                    <i class="bi bi-list" style="font-size: 1.5rem;"></i>
                </button>
            </div>
            
            <!-- Sidebar Content -->
            <div id="sidebar-content-scroll">
                <div class="list-group list-group-flush mt-2 mb-5 pb-5"> 
                    <small class="px-4 mb-3 text-uppercase text-secondary fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px; opacity: 0.7;">เมนูหลัก (Main Menu)</small>
                    
                    <a href="{{ route('inspection.dashboard') }}" class="list-group-item list-group-item-action {{ (request()->routeIs('inspection.*') && !request()->routeIs('inspection.verification')) ? 'active' : '' }}">
                        <i class="bi bi-person-bounding-box me-2"></i> <span>ตรวจพนักงาน</span>
                    </a>

                    <a href="{{ route('inspection.verification') }}" class="list-group-item list-group-item-action {{ request()->routeIs('inspection.verification') ? 'active' : '' }}">
                        <i class="bi bi-shield-check me-2"></i> <span>ทวนสอบผล</span>
                    </a>
                    
                    <a href="#" class="list-group-item list-group-item-action disabled">
                        <i class="bi bi-grid me-2"></i> <span>สรุปผลภาพรวม</span>
                    </a>
                    
                    <a href="{{ route('employees.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('employees.*') ? 'active' : '' }}">
                        <i class="bi bi-people me-2"></i> <span>จัดการพนักงาน</span>
                    </a>

                    <small class="px-4 mt-3 mb-2 text-uppercase text-muted" style="font-size: 0.75rem; letter-spacing: 1px;">ตั้งค่าระบบ</small>

                    <a href="{{ route('shifts.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('shifts.*') ? 'active' : '' }}">
                        <i class="bi bi-calendar2-range me-2"></i> <span>จัดการกะทำงาน</span>
                    </a>

                    <a href="{{ route('locations.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('locations.*') ? 'active' : '' }}">
                        <i class="bi bi-geo-alt me-2"></i> <span>จุดประจำการ</span>
                    </a>

                    <a href="{{ route('machines.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('machines.*') ? 'active' : '' }}">
                        <i class="bi bi-gear-wide-connected me-2"></i> <span>เครื่องจักร</span>
                    </a>

                    <a href="{{ route('checkpoint-categories.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('checkpoint-categories.*') ? 'active' : '' }}">
                        <i class="bi bi-tags me-2"></i> <span>หมวดหมู่จุดตรวจ</span>
                    </a>
                </div>
            </div>

            <!-- Sidebar Footer (User Profile) -->
            <div class="sidebar-footer mb-3">
                <div class="card bg-slate-50 border-0" style="border-radius: 12px; background-color: #f8fafc;">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-white shadow-sm d-flex justify-content-center align-items-center me-3 text-primary border" style="width: 40px; height: 40px;">
                                <span class="fw-bold">{{ substr(Auth::user()->name, 0, 1) }}</span>
                            </div>
                            <div>
                                <h6 class="mb-0 text-dark fw-bold" style="font-size: 0.9rem;">{{ Auth::user()->name }}</h6>
                                <small class="text-muted" style="font-size: 0.75rem;">{{ Auth::user()->role }}</small>
                            </div>
                        </div>
                        
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-link text-secondary p-0" title="Logout">
                                <i class="bi bi-box-arrow-right"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div> 

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Top Navigation (Mobile Toggle & Context) -->
            <nav class="navbar navbar-expand-lg navbar-light glass-nav py-3 px-4">
                <div class="d-flex align-items-center w-100 justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none rounded-circle p-2 shadow-sm border" id="menu-toggle" onclick="toggleSidebar()" style="cursor: pointer; z-index: 9999;">
                            <i class="bi bi-list fs-4"></i>
                        </button>
                        <h2 class="fs-4 fw-bold mb-0 text-dark">@yield('header')</h2>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="badge bg-white text-success border px-3 py-2 rounded-pill">
                            <span class="d-flex align-items-center gap-2">
                                <span class="spinner-grow spinner-grow-sm text-success" role="status" aria-hidden="true"></span>
                                ระบบออนไลน์
                            </span>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-white border rounded-pill px-3 py-1" type="button">
                                <span class="flag-icon">🇹🇭</span> ไทย
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid px-4">
                {{ $slot }}
            </div>
        </div>
    </div>
    <!-- /#wrapper -->
    
    <script>
        window.toggleSidebar = function() {
            const sidebar = document.getElementById('sidebar-wrapper');
            const overlay = document.getElementById('sidebar-overlay');
            const body = document.body;
            
            if (sidebar && overlay && body) {
                sidebar.classList.toggle('toggled');
                overlay.classList.toggle('active');
                body.classList.toggle('sidebar-toggled');
            }
        }

        window.toggleDesktopSidebar = function() {
            document.body.classList.toggle('sb-collapsed');
        }

        window.closeSidebar = function() {
            const sidebar = document.getElementById('sidebar-wrapper');
            const overlay = document.getElementById('sidebar-overlay');
            const body = document.body;
            
            if (sidebar && overlay && body) {
                sidebar.classList.remove('toggled');
                overlay.classList.remove('active');
                body.classList.remove('sidebar-toggled');
            }
        }
    </script>
    
    <script>
        // Global UX Enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Loading State for Forms
            const forms = document.querySelectorAll('form:not(.no-loading)');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const btn = this.querySelector('button[type="submit"]');
                    if (btn && !btn.classList.contains('no-loading')) {
                        const originalText = btn.innerHTML;
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> กำลังประมวลผล...';
                        
                        // Restore after 10s (failsafe)
                        setTimeout(() => {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }, 10000);
                    }
                });
            });

            // 2. SweetAlert2 Confirmation
            // 2. SweetAlert2 Confirmation
            window.confirmAction = function(element, message = 'คุณแน่ใจหรือไม่?', confirmBtnText = 'ยืนยัน', icon = 'warning') {
                // Find the closest form from the clicked element (button)
                const form = element.closest('form');
                
                if (!form) {
                    console.error('Form not found for confirmation action');
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: 'ไม่สามารถทำรายการได้ (ไม่พบฟอร์มข้อมูล)',
                        timer: 2000
                    });
                    return;
                }
                
                Swal.fire({
                    title: 'ยืนยันการทำรายการ',
                    text: message,
                    icon: icon,
                    showCancelButton: true,
                    confirmButtonColor: '#2563eb', // Primary Blue
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: confirmBtnText,
                    cancelButtonText: 'ยกเลิก',
                    reverseButtons: true,
                    focusCancel: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Manually trigger loading state
                        element.disabled = true;
                        element.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> กำลังยืนยัน...';
                        console.log('Submitting form...');
                        form.submit();
                    }
                });
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
