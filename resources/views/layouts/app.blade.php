<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'HygienePro') }}</title>

    <!-- Fonts — Premium Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    
    <!-- HygienePro Clean Theme Overrides -->
    <link rel="stylesheet" href="{{ asset('css/hygiene-theme.css') }}?v={{ time() }}">
    
    <style>
        /* Modern Mobile Tables - Hygiene Clean Upgrade */
        .table-modern thead { background: var(--hygiene-bg); }
        .table-modern th { font-weight: 600; color: var(--hygiene-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--hygiene-border) !important; padding: 1rem !important; }
        .table-modern td { padding: 1rem !important; vertical-align: middle; border-bottom: 1px solid var(--hygiene-border-light) !important; font-size: 0.95rem; transition: background var(--transition-smooth); color: var(--hygiene-text-main) !important; }
        .table-modern tr:hover td { background: var(--hygiene-bg); }
        .table-modern tr:last-child td { border-bottom: none !important; }

        @media (max-width: 767.98px) {
            .table-modern { background: transparent !important; border: none !important; box-shadow: none !important; }
            .table-modern .table-responsive { border: none !important; overflow-x: visible !important; }
            .table-modern table { display: block !important; background: transparent !important; }
            .table-modern thead { display: none !important; }
            .table-modern tbody { display: block !important; }
            .table-modern tr { display: block !important; background: var(--hygiene-surface) !important; border-radius: var(--radius-md) !important; padding: 1.25rem !important; margin-bottom: 1.25rem !important; box-shadow: var(--shadow-soft) !important; border: 1px solid var(--hygiene-border) !important; }
            .table-modern td { display: flex !important; justify-content: space-between !important; align-items: center !important; border-bottom: 1px solid var(--hygiene-border-light) !important; padding: 0.75rem 0 !important; text-align: right !important; width: 100% !important; min-height: 48px; color: var(--hygiene-text-main) !important; }
            .table-modern td:first-child { justify-content: flex-start !important; text-align: left !important; font-weight: 600 !important; font-size: 1.05rem !important; border-bottom: 1px dashed var(--hygiene-border) !important; padding-bottom: 0.875rem !important; margin-bottom: 0.25rem !important; color: var(--hygiene-text-heading) !important; }
            .table-modern td:last-child { border-bottom: none !important; padding-top: 0.875rem !important; margin-top: 0.25rem !important; justify-content: flex-end !important; }
            .table-modern td::before { content: attr(data-label); font-weight: 600; color: var(--hygiene-text-muted); font-size: 0.75rem; text-transform: uppercase; text-align: left !important; margin-right: 1rem; display: inline-block; font-family: var(--font-sans); letter-spacing: 0.05em; }
            .table-modern td:first-child::before, .table-modern td:last-child::before { display: none !important; }
            
            /* Empty State Fix */
            .table-modern td[colspan] { justify-content: center !important; text-align: center !important; flex-direction: column; padding: 2.5rem 1rem !important; border-bottom: none !important; }
            .table-modern td[colspan]::before { display: none !important; }
            .table-modern td[colspan] i { font-size: 3rem; margin-bottom: 1rem; color: var(--hygiene-border); }
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Overlay for Mobile -->
        <div id="sidebar-overlay" onclick="document.body.classList.remove('sidebar-toggled'); document.getElementById('sidebar-wrapper').classList.remove('toggled'); document.getElementById('sidebar-overlay').classList.remove('active');"></div>

        <!-- Sidebar -->
        <div class="d-flex flex-column h-100" id="sidebar-wrapper">
            <div class="sidebar-heading d-flex justify-content-between align-items-center flex-shrink-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="logo-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="fw-bold" style="line-height: 1; font-size: 1.1rem;">HygienePro</span>
                        <small style="font-size: 0.65rem; opacity: 0.6;">Enterprise System</small>
                    </div>
                </div>
                <!-- Mobile Toggle (Collapse) -->
                <button class="btn btn-link text-white d-lg-none p-0 me-2" id="sidebar-toggle-mobile" onclick="toggleSidebar()" style="position: relative; z-index: 1051; cursor: pointer; min-width: 44px; min-height: 44px;">
                    <i class="bi bi-chevron-bar-left" style="font-size: 1.5rem;"></i>
                </button>
                
                <!-- Desktop Toggle (Collapse) -->
                <button class="btn btn-link text-white d-none d-lg-block p-0 me-2" id="sidebar-toggle-desktop" onclick="toggleDesktopSidebar()">
                    <i class="bi bi-list" style="font-size: 1.5rem;"></i>
                </button>
            </div>
            
            <!-- Sidebar Content -->
            <div id="sidebar-content-scroll" class="flex-grow-1 overflow-y-auto" style="scrollbar-width: thin;">
                <div class="list-group list-group-flush mt-2 mb-4"> 
                    @php 
                        $user = Auth::user();
                        $isQA = $user->isQA();
                        $isAdmin = $user->isAdmin();
                        $isSupervisorPlus = $user->level >= 4; 
                        $isQASupervisor = $isQA && $isSupervisorPlus;
                        $isManagerPlus = $user->level >= 5;
                    @endphp

                    {{-- GROUP 1: DASHBOARD --}}
                    <div class="mb-1">
                        <a href="{{ route('dashboard') }}" class="list-group-item list-group-item-action border-0 py-2 {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="bi bi-grid me-2"></i> <span>แดชบอร์ด (Dashboard)</span>
                        </a>
                    </div>
 
                    {{-- GROUP 2: INSPECTION (Operations) --}}
                    @if($isQA || $isSupervisorPlus || $isAdmin || $isManagerPlus)
                    @php
                        $isActiveInspection = request()->routeIs('inspection.*') || request()->routeIs('corrective.*') || request()->routeIs('reports.*');
                    @endphp
                    <div class="mt-1">
                        <a class="d-flex align-items-center justify-content-between text-secondary fw-bold px-3 py-1 text-decoration-none w-100" 
                           data-bs-toggle="collapse" 
                           href="#menu-inspection" 
                           role="button" 
                           aria-expanded="{{ $isActiveInspection ? 'true' : 'false' }}"
                           aria-controls="menu-inspection"
                           style="font-size: 0.75rem; letter-spacing: 0.5px; opacity: 0.7;">
                            <span>การตรวจสอบ (INSPECTION)</span>
                            <i class="bi bi-chevron-down" style="font-size: 0.7rem;"></i>
                        </a>
                        
                        <div class="collapse {{ $isActiveInspection ? 'show' : '' }}" id="menu-inspection">
                            <div class="ps-2">
                                @if($isQA || $isAdmin)
                                <a href="{{ route('inspection.dashboard', 'personnel') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 py-2 {{ (request()->routeIs('inspection.dashboard') || request()->routeIs('inspection.area.*') || request()->routeIs('inspection.checklist')) ? 'active' : '' }}">
                                    <i class="bi bi-play-circle me-2"></i> <span>เริ่มการตรวจ (Inspection)</span>
                                </a>
                                @endif

                                @if(($isQA && $isSupervisorPlus) || $isAdmin)
                                <a href="{{ route('inspection.verification') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 py-2 {{ request()->routeIs('inspection.verification') ? 'active' : '' }}">
                                    <i class="bi bi-shield-check me-2"></i> <span>ทวนสอบผล (Verify)</span>
                                </a>
                                @endif

                                <a href="{{ route('corrective.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 py-2 {{ request()->routeIs('corrective.*') ? 'active' : '' }}">
                                    <i class="bi bi-exclamation-triangle me-2"></i> <span>ติดตามการแก้ไข (Issues)</span>
                                </a>

                                <a href="{{ route('approvals.pending') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 py-2 {{ request()->routeIs('approvals.*') ? 'active' : '' }}">
                                    <i class="bi bi-check2-square me-2"></i> <span>การอนุมัติ (Approvals)</span>
                                </a>
                                
                                @if($isManagerPlus || $isAdmin || $isQASupervisor)
                                <a href="{{ route('reports.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 py-2 {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                                    <i class="bi bi-file-earmark-bar-graph me-2"></i> <span>รายงานสรุปผล (Reports)</span>
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- GROUP 3: MASTER DATA (Setup) --}}
                    @if($isSupervisorPlus || $isAdmin)
                    @php
                        $isActiveMaster = request()->routeIs('employees.*') || request()->routeIs('departments.*') || request()->routeIs('shifts.*') || 
                                          request()->routeIs('locations.*') || request()->routeIs('machines.*') || request()->routeIs('checkpoint-categories.*') || request()->routeIs('checkpoints.*');
                    @endphp
                    <div class="mt-1">
                        <a class="d-flex align-items-center justify-content-between text-secondary fw-bold px-3 py-1 text-decoration-none w-100" 
                           data-bs-toggle="collapse" 
                           href="#menu-master" 
                           role="button" 
                           aria-expanded="{{ $isActiveMaster ? 'true' : 'false' }}"
                           aria-controls="menu-master"
                           style="font-size: 0.75rem; letter-spacing: 0.5px; opacity: 0.7;">
                            <span>ข้อมูลหลัก (MASTER DATA)</span>
                            <i class="bi bi-chevron-down" style="font-size: 0.7rem;"></i>
                        </a>

                        <div class="collapse {{ $isActiveMaster ? 'show' : '' }}" id="menu-master">
                            <div class="ps-2">
                                {{-- Admin / QA Supervisor Items --}}
                                @if($isAdmin || $isQASupervisor)
                                <a href="{{ route('locations.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 {{ request()->routeIs('locations.*') ? 'active' : '' }}">
                                    <i class="bi bi-geo-alt me-2"></i> <span>จุดประจำการ (Areas)</span>
                                </a>
                                <a href="{{ route('machines.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 {{ request()->routeIs('machines.*') ? 'active' : '' }}">
                                    <i class="bi bi-cpu me-2"></i> <span>เครื่องจักร (Machines)</span>
                                </a>
                                <a href="{{ route('checkpoint-categories.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 {{ (request()->routeIs('checkpoint-categories.*') || request()->routeIs('checkpoints.*')) ? 'active' : '' }}">
                                    <i class="bi bi-card-checklist me-2"></i> <span>จุดตรวจ (Checkpoints)</span>
                                </a>
                                <a href="{{ route('departments.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 py-2 {{ request()->routeIs('departments.*') ? 'active' : '' }}">
                                    <i class="bi bi-building me-2"></i> <span>แผนก (Departments)</span>
                                </a>
                                <a href="{{ route('schedules.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 {{ request()->routeIs('schedules.*') ? 'active' : '' }}">
                                    <i class="bi bi-calendar-range me-2"></i> <span>ตารางการตรวจ (Schedules)</span>
                                </a>
                                @endif

                                {{-- Supervisor+ Items --}}
                                <a href="{{ route('employees.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 py-2 {{ request()->routeIs('employees.*') ? 'active' : '' }}">
                                    <i class="bi bi-people me-2"></i> <span>พนักงาน (Employees)</span>
                                </a>
                                <a href="{{ route('shifts.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 {{ request()->routeIs('shifts.*') ? 'active' : '' }}">
                                    <i class="bi bi-clock me-2"></i> <span>กะทำงาน (Shifts)</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- GROUP 4: SYSTEM (Settings) --}}
                    @if($isAdmin)
                    @php
                        $isActiveSystem = request()->routeIs('users.*') || request()->routeIs('admin.approvals.*');
                    @endphp
                    <div class="mt-2">
                        <a class="d-flex align-items-center justify-content-between text-secondary fw-bold px-3 py-2 text-decoration-none w-100" 
                           data-bs-toggle="collapse" 
                           href="#menu-system" 
                           role="button" 
                           aria-expanded="{{ $isActiveSystem ? 'true' : 'false' }}"
                           aria-controls="menu-system"
                           style="font-size: 0.75rem; letter-spacing: 0.5px; opacity: 0.7;">
                            <span>ตั้งค่าระบบ (SYSTEM)</span>
                            <i class="bi bi-chevron-down" style="font-size: 0.7rem;"></i>
                        </a>

                        <div class="collapse {{ $isActiveSystem ? 'show' : '' }}" id="menu-system">
                            <div class="ps-2">
                                <a href="{{ route('users.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                    <i class="bi bi-person-badge me-2"></i> <span>ผู้ใช้งานระบบ (Users)</span>
                                </a>
                                <a href="{{ route('admin.approvals.setup') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 {{ request()->routeIs('admin.approvals.*') ? 'active' : '' }}">
                                    <i class="bi bi-diagram-3 me-2"></i> <span>ตั้งค่า Approval Flow</span>
                                </a>
                                <a href="{{ route('activity-logs.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 {{ request()->routeIs('activity-logs.*') ? 'active' : '' }}">
                                    <i class="bi bi-activity me-2"></i> <span>ประวัติการใช้งาน (Audit Logs)</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Sidebar Footer (User Profile) -->
            <div class="sidebar-footer mb-3 flex-shrink-0 px-2">
                <div class="border-0" style="border-radius: 12px; background-color: rgba(255, 255, 255, 0.05); padding: 12px;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex justify-content-center align-items-center me-3 text-white shadow-sm" style="width: 38px; height: 38px; background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%); font-size: 0.85rem;">
                                <span class="fw-bold">{{ substr(Auth::user()->name, 0, 1) }}</span>
                            </div>
                            <div>
                                <h6 class="mb-0 text-white fw-bold" style="font-size: 0.9rem;">{{ Auth::user()->name }}</h6>
                                <small class="text-white-50" style="font-size: 0.75rem;">{{ Auth::user()->role }}</small>
                            </div>
                        </div>
                        
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-link text-white-50 p-0 text-decoration-none" title="Logout">
                                <i class="bi bi-box-arrow-right fs-5"></i>
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
                        <div class="badge bg-white text-success border px-3 py-2 rounded-pill d-none d-md-inline-flex" style="font-weight: 600; font-size: 0.75rem;">
                            <span class="d-flex align-items-center gap-2">
                                <span class="d-inline-block rounded-circle bg-success animate-breathe" style="width: 7px; height: 7px;"></span>
                                ออนไลน์
                            </span>
                        </div>

                        <!-- Notification Bell (Bootstrap) -->
                        <div class="dropdown">
                            <button class="btn btn-white border rounded-circle position-relative p-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 40px; height: 40px;">
                                <i class="bi bi-bell"></i>
                                @if(Auth::check() && Auth::user()->unreadNotifications->count() > 0)
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white p-1" style="font-size: 0.65rem;">
                                        {{ Auth::user()->unreadNotifications->count() }}
                                        <span class="visually-hidden">unread messages</span>
                                    </span>
                                @endif
                            </button>
                            <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2 p-0 animate-slide-down" style="width: 320px; max-height: 400px; overflow-y:auto;">
                                <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center bg-light rounded-top">
                                    <h6 class="mb-0 fw-bold text-secondary" style="font-size: 0.85rem;">การแจ้งเตือน (Notifications)</h6>
                                    @if(Auth::check() && Auth::user()->unreadNotifications->count() > 0)
                                        <form action="{{ route('notifications.readAll') }}" method="POST" class="m-0">
                                            @csrf
                                            <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size: 0.75rem;">อ่านทั้งหมด</button>
                                        </form>
                                    @endif
                                </div>
                                <div class="list-group list-group-flush">
                                    @if(Auth::check())
                                        @forelse(Auth::user()->unreadNotifications as $notification)
                                            <a href="javascript:void(0)" onclick="markAsRead('{{ $notification->id }}', '{{ $notification->data['link'] ?? '#' }}')" class="list-group-item list-group-item-action px-3 py-3 border-bottom-0 border-top-0 border-start-0 border-end-0 hover-bg-light">
                                                <div class="d-flex align-items-start">
                                                    <div class="me-3 mt-1">
                                                        <i class="{{ $notification->data['icon'] ?? 'bi-bell' }} fs-5"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 text-dark fw-semibold" style="font-size: 0.9rem;">{{ $notification->data['title'] ?? 'Notification' }}</h6>
                                                        <p class="mb-1 text-muted text-wrap" style="font-size: 0.8rem; line-height: 1.4;">{{ $notification->data['message'] ?? '' }}</p>
                                                        <small class="text-secondary" style="font-size: 0.7rem;">{{ $notification->created_at->diffForHumans() }}</small>
                                                    </div>
                                                </div>
                                            </a>
                                        @empty
                                            <div class="text-center py-4">
                                                <i class="bi bi-bell-slash text-muted fs-3 mb-2 d-block"></i>
                                                <span class="text-muted small">ไม่มีการแจ้งเตือนใหม่</span>
                                            </div>
                                        @endforelse
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="dropdown">
                            <button class="btn btn-white border rounded-pill px-3 py-1" type="button">
                                <span class="flag-icon">🇹🇭</span> ไทย
                            </button>
                        </div>
                    </div>

    <script>
        function markAsRead(id, link) {
            fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json'
                }
            }).then(() => {
                if(link && link !== '#') {
                    window.location.href = link;
                } else {
                    window.location.reload();
                }
            }).catch(error => {
                console.error('Error marking as read:', error);
                if(link && link !== '#') window.location.href = link;
            });
        }
    </script>
                </div>
            </nav>

            <div class="container-fluid px-2 px-md-4">
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
        // Scroll-aware navbar
        (function() {
            const nav = document.querySelector('.glass-nav');
            if (nav) {
                window.addEventListener('scroll', function() {
                    if (window.scrollY > 10) {
                        nav.classList.add('scrolled');
                    } else {
                        nav.classList.remove('scrolled');
                    }
                }, { passive: true });
            }
        })();
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
