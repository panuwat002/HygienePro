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
    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    
    <!-- HygienePro Clean Theme Overrides -->
    <link rel="stylesheet" href="{{ asset('css/hygiene-theme.css') }}?v={{ file_exists(public_path('css/hygiene-theme.css')) ? filemtime(public_path('css/hygiene-theme.css')) : time() }}">
    

    @stack('styles')
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Overlay for Mobile -->
        <div id="sidebar-overlay" onclick="document.body.classList.remove('sidebar-toggled'); document.getElementById('sidebar-wrapper').classList.remove('toggled'); document.getElementById('sidebar-overlay').classList.remove('active');"></div>

        <!-- Sidebar -->
        <div class="d-flex flex-column h-100" id="sidebar-wrapper">
            <div class="sidebar-heading d-flex justify-content-between align-items-center flex-shrink-0">
                <div class="d-flex align-items-center gap-2 brand-wrapper">
                    <div class="logo-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="fw-bold" style="line-height: 1; font-size: 1.1rem;">HygienePro</span>
                        <small style="font-size: 0.65rem; opacity: 0.6;">Enterprise System</small>
                    </div>
                </div>
                <!-- Mobile Toggle (Collapse) -->
                <button class="btn btn-link text-white d-lg-none p-0 me-2" id="sidebar-toggle-mobile" onclick="toggleSidebar()" aria-label="เปิด/ปิดเมนู" style="position: relative; z-index: 1051; cursor: pointer; min-width: 44px; min-height: 44px;">
                    <i class="bi bi-chevron-bar-left" style="font-size: 1.5rem;" aria-hidden="true"></i>
                </button>
                
                <!-- Desktop Toggle (Collapse) -->
                <button class="btn btn-link text-white d-none d-lg-block p-0 mx-auto" id="sidebar-toggle-desktop" onclick="toggleDesktopSidebar()" aria-label="ย่อ/ขยายเมนู">
                    <i class="bi bi-list" style="font-size: 1.5rem;" aria-hidden="true"></i>
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

                                @can('view-random-audits')
                                <a href="{{ route('audits.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 py-2 {{ request()->routeIs('audits.*') ? 'active' : '' }}">
                                    <i class="bi bi-bullseye me-2"></i> <span>ตารางสุ่มตรวจ (Audits)</span>
                                </a>
                                @endcan


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
                        $isActiveSystem = request()->routeIs('users.*') || request()->routeIs('admin.approvals.*') || request()->routeIs('admin.settings.*');
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
                                {{-- Hidden until the approval flow is actually wired up. Nothing in the
                                     codebase constructs an ApprovalRequest, so /approvals/pending is
                                     permanently empty and any step chain configured here can never fire.
                                     Leaving the link visible invites admins to configure a control that
                                     does not exist, which is worse than having no control. The route
                                     still works if visited directly. --}}
                                {{-- <a href="{{ route('admin.approvals.setup') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 {{ request()->routeIs('admin.approvals.*') ? 'active' : '' }}">
                                    <i class="bi bi-diagram-3 me-2"></i> <span>ตั้งค่า Approval Flow</span>
                                </a> --}}
                                <a href="{{ route('activity-logs.index') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 {{ request()->routeIs('activity-logs.*') ? 'active' : '' }}">
                                    <i class="bi bi-activity me-2"></i> <span>ประวัติการใช้งาน (Audit Logs)</span>
                                </a>
                                <a href="{{ route('admin.settings.email') }}" class="list-group-item list-group-item-action border-0 mb-1 rounded-3 {{ request()->routeIs('admin.settings.email') ? 'active' : '' }}">
                                    <i class="bi bi-envelope-paper me-2"></i> <span>ตั้งค่าระบบอีเมล (Email)</span>
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
                        <a href="{{ route('profile.edit') }}" class="text-decoration-none d-flex align-items-center flex-grow-1">
                            <div class="rounded-circle d-flex justify-content-center align-items-center me-3 text-white shadow-sm" style="width: 38px; height: 38px; background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%); font-size: 0.85rem;">
                                <span class="fw-bold">{{ substr(Auth::user()->name, 0, 1) }}</span>
                            </div>
                            <div>
                                <h6 class="mb-0 text-white fw-bold" style="font-size: 0.9rem;">{{ Auth::user()->name }}</h6>
                                <small class="text-white-50" style="font-size: 0.75rem;">{{ Auth::user()->role }}</small>
                            </div>
                        </a>
                        
                        <div class="d-flex align-items-center">
                            <a href="{{ route('settings.index') }}" class="text-white-50 p-0 text-decoration-none me-3" title="Settings" aria-label="ตั้งค่า">
                                <i class="bi bi-gear fs-5" aria-hidden="true"></i>
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="mb-0">
                                @csrf
                                <button type="submit" class="btn btn-link text-white-50 p-0 text-decoration-none" title="Logout" aria-label="ออกจากระบบ">
                                    <i class="bi bi-box-arrow-right fs-5" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div> 

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Top Navigation (Mobile Toggle & Context) -->
            <nav class="navbar navbar-expand-lg navbar-light glass-nav py-2 py-md-3 px-3 px-md-4">
                <div class="d-flex align-items-center w-100 justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none rounded-circle p-2 shadow-sm border" id="menu-toggle" onclick="toggleSidebar()" aria-label="เปิด/ปิดเมนู" style="cursor: pointer; z-index: 9999;">
                            <i class="bi bi-list fs-4" aria-hidden="true"></i>
                        </button>
                        <h2 class="fw-bold mb-0 text-dark" style="font-size:clamp(1rem, 4vw, 1.5rem); line-height:1.3;">@yield('header')</h2>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="badge bg-white text-success border px-3 py-2 rounded-pill d-none d-md-inline-flex" style="font-weight: 600; font-size: 0.75rem;">
                            <span class="d-flex align-items-center gap-2">
                                <span class="d-inline-block rounded-circle bg-success animate-breathe" style="width: 7px; height: 7px;"></span>
                                ออนไลน์
                            </span>
                        </div>

                        <!-- Notification Bell (Bootstrap) -->
                        @php
                            // Read once: the relation is used for the badge, the "อ่านทั้งหมด"
                            // control and the list below, and each access re-reads the property.
                            $unreadNotifications = Auth::check() ? Auth::user()->unreadNotifications : collect();
                            $unreadCount = $unreadNotifications->count();
                        @endphp
                        <div class="dropdown position-relative">
                            <button class="btn btn-white border rounded-circle p-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="การแจ้งเตือน{{ $unreadCount > 0 ? ' — ยังไม่ได้อ่าน ' . $unreadCount . ' รายการ' : '' }}" style="width: 40px; height: 40px;">
                                <i class="bi bi-bell" aria-hidden="true"></i>
                            </button>
                            @if($unreadCount > 0)
                                {{-- Deliberately a sibling of the button, not a child: .btn sets
                                     overflow: hidden for its ripple, which cropped the badge to a
                                     red sliver the moment it overhung the bell. pointer-events are
                                     off so the part covering the bell still opens the dropdown. --}}
                                <span class="position-absolute translate-middle badge rounded-pill bg-danger border border-2 border-white fw-bold"
                                      data-unread-count="{{ $unreadCount }}"
                                      style="top: 4px; left: 100%; z-index: 2; pointer-events: none; font-size: 0.7rem; line-height: 1; min-width: 20px; padding: 0.25rem 0.35rem;">
                                    {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                                    <span class="visually-hidden">รายการที่ยังไม่ได้อ่าน</span>
                                </span>
                            @endif
                            <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2 p-0 animate-slide-down" style="width: 320px; max-height: 400px; overflow-y:auto;">
                                <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center bg-light rounded-top">
                                    <h6 class="mb-0 fw-bold text-secondary" style="font-size: 0.85rem;">การแจ้งเตือน (Notifications)</h6>
                                    @if($unreadCount > 0)
                                        <form action="{{ route('notifications.readAll') }}" method="POST" class="m-0">
                                            @csrf
                                            <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size: 0.75rem;">อ่านทั้งหมด</button>
                                        </form>
                                    @endif
                                </div>
                                <div class="list-group list-group-flush">
                                    @if(Auth::check())
                                        @forelse($unreadNotifications as $notification)
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
                            <button class="btn btn-white border rounded-pill px-3 py-1" type="button" aria-label="เลือกภาษา">
                                <span class="flag-icon" aria-hidden="true">🇹🇭</span> ไทย
                            </button>
                        </div>
                    </div>

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
                form.addEventListener('submit', function(e) {
                    const btn = this.querySelector('button[type="submit"]');
                    if (btn && !btn.classList.contains('no-loading')) {
                        // Create or show global loading overlay
                        let overlay = document.getElementById('global-submit-overlay');
                        if (!overlay) {
                            overlay = document.createElement('div');
                            overlay.id = 'global-submit-overlay';
                            overlay.innerHTML = `
                                <div class="spinner-border text-primary shadow" style="width: 3.5rem; height: 3.5rem; border-width: 0.35em;" role="status"></div>
                                <div class="mt-3 fw-bold fs-5 text-dark bg-white px-4 py-2 rounded-pill shadow-sm">กำลังประมวลผล...</div>
                            `;
                            overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(255,255,255,0.7); backdrop-filter: blur(4px); z-index: 99999; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.2s;';
                            document.body.appendChild(overlay);
                        } else {
                            overlay.style.display = 'flex';
                        }
                        
                        // Restore after 10s (failsafe)
                        setTimeout(() => {
                            if (overlay) overlay.style.display = 'none';
                        }, 10000);
                    }
                });
            });

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
    <!-- Fix Table Dropdown Stacking Context (Append to Body) -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Monitor dropdown show/hide events globally
            document.addEventListener('show.bs.dropdown', function (e) {
                if (e.target.closest('.table-modern')) {
                    var menu = e.target.nextElementSibling;
                    if (menu && menu.classList.contains('dropdown-menu')) {
                        document.body.appendChild(menu);
                        e.target.dataset.appendedMenu = 'true';
                    }
                }
            });
            document.addEventListener('hide.bs.dropdown', function (e) {
                if (e.target.dataset.appendedMenu === 'true') {
                    var menu = document.querySelector('body > .dropdown-menu.show');
                    if (menu) {
                        e.target.parentElement.appendChild(menu);
                    }
                    e.target.dataset.appendedMenu = 'false';
                }
            });
        });
    </script>
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
    
    <!-- Third-party Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Global Session Notification (SweetAlert2 Toast) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            @if(session('success'))
                Toast.fire({ icon: 'success', title: '{!! addslashes(session('success')) !!}' });
            @endif
            @if(session('error'))
                Toast.fire({ icon: 'error', title: '{!! addslashes(session('error')) !!}' });
            @endif
            @if(session('warning'))
                Toast.fire({ icon: 'warning', title: '{!! addslashes(session('warning')) !!}' });
            @endif
            @if(session('info') || session('status'))
                Toast.fire({ icon: 'info', title: '{!! addslashes(session('info') ?? session('status')) !!}' });
            @endif
        });
    </script>

    @stack('modals')
    @stack('scripts')
</body>
</html>
