{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', \App\Models\Setting::get('system_name', 'الميّزان'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f0f2f5;
        }

        /* ── Sidebar ── */
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #1a2535 0%, #243447 100%);
            box-shadow: 2px 0 12px rgba(0,0,0,0.18);
            display: flex;
            flex-direction: column;
        }
        .sidebar-brand {
            padding: 18px 15px 14px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            margin-bottom: 6px;
        }
        .sidebar-brand h5 {
            font-size: 1rem;
            font-weight: 800;
            color: #fff;
            margin: 0;
            letter-spacing: 0.3px;
        }
        .sidebar-brand small { color: rgba(255,255,255,0.45); font-size: 0.72rem; }

        /* Regular nav links */
        .sidebar .nav-link {
            color: rgba(255,255,255,0.72);
            padding: 9px 16px;
            margin: 1px 8px;
            border-radius: 7px;
            transition: all 0.22s;
            font-size: 0.88rem;
            font-weight: 500;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255,255,255,0.09);
            color: #fff;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: #fff;
            box-shadow: 0 2px 8px rgba(52,152,219,0.35);
        }
        .sidebar .nav-link i {
            margin-left: 8px;
            font-size: 1rem;
            width: 18px;
            text-align: center;
            opacity: 0.85;
        }
        .sidebar .nav-link.active i { opacity: 1; }

        /* Section toggle buttons */
        .nav-section-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            width: calc(100% - 16px);
            margin: 8px 8px 2px;
            padding: 7px 12px;
            color: rgba(255,255,255,0.65);
            font-size: 0.83rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            border: none;
            background: none;
            cursor: pointer;
            border-radius: 6px;
            transition: background 0.18s, color 0.18s;
            text-align: right;
        }
        .nav-section-toggle:hover {
            color: #fff;
            background: rgba(255,255,255,0.09);
        }
        .nav-section-toggle .sec-icon { font-size: 0.9rem; flex-shrink: 0; opacity: 0.85; }
        .nav-section-toggle .chevron {
            font-size: 0.65rem;
            margin-right: auto;
            margin-left: 0;
            transition: transform 0.2s ease;
            opacity: 0.7;
        }
        .nav-section-toggle.collapsed .chevron { transform: rotate(-90deg); }

        /* Sub-nav (inside collapse) */
        .sub-nav .nav-link {
            padding-right: 30px !important;
            font-size: 0.85rem;
        }

        /* Sidebar separator */
        .sidebar-sep { border-top: 1px solid rgba(255,255,255,0.07); margin: 6px 14px; }

        /* ── Top Navbar ── */
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            background: white !important;
        }

        /* ── Cards ── */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            font-weight: 600;
            color: #2c3e50;
        }

        /* ── Stat Cards ── */
        .stat-card {
            padding: 20px;
            border-radius: 10px;
            color: white;
        }
        .stat-card.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card.success { background: linear-gradient(135deg, #48c6ef 0%, #6f86d6 100%); }
        .stat-card.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.info    { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

        /* ── Tables ── */
        .table-custom { background: white; border-radius: 10px; overflow: hidden; }
        .btn-action   { padding: 4px 10px; margin: 0 2px; }

        /* ── Page title ── */
        .page-title { color: #2c3e50; font-weight: 600; margin-bottom: 20px; }

        /* ── Print ── */
        @media print {
            nav.sidebar, nav.navbar, .no-print,
            .dt-buttons, .dataTables_length, .dataTables_filter,
            .dataTables_info, .dataTables_paginate,
            form.row, .alert, .btn-close { display: none !important; }
            main { margin-right: 0 !important; width: 100% !important;
                   max-width: 100% !important; flex: 0 0 100% !important; }
            .table-responsive { overflow: visible !important; }
            body { background: white !important; }
            .card { box-shadow: none !important; }
        }
    </style>

    @vite(['resources/css/theme.css', 'resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
<div class="container-fluid">
    <div class="row">

        <!-- ═══════════════ SIDEBAR ═══════════════ -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar p-0">
            <div class="position-sticky pt-0" style="top:0; overflow-y:auto; max-height:100vh;">

                <!-- Brand -->
                <div class="sidebar-brand">
                    <h5><i class="bi bi-shop-window"></i> {{ \App\Models\Setting::get('system_name', 'الميّزان') }}</h5>
                    @auth
                        <small>{{ auth()->user()->name }}</small>
                    @endauth
                </div>

                <ul class="nav flex-column pb-3" id="sidebarNav">
                @auth

                <!-- ── Always visible ── -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                       href="{{ route('dashboard') }}">
                        <i class="bi bi-speedometer2"></i> لوحة التحكم
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}"
                       href="{{ route('pos.index') }}">
                        <i class="bi bi-cart-check"></i> نقطة البيع
                    </a>
                </li>

                {{-- ── Sales & Inventory ── --}}
                @canany(['products.view','categories.view','sales.view','inventory.view'])
                @php $s1 = request()->routeIs('products.*','categories.*','sales.*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$s1 ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns1"
                            aria-expanded="{{ $s1 ? 'true' : 'false' }}">
                        <i class="bi bi-shop sec-icon"></i>
                        <span>المبيعات والمخزون</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $s1 ? 'show' : '' }}" id="ns1">
                    <ul class="nav flex-column sub-nav">
                        @can('products.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}"
                               href="{{ route('products.index') }}">
                                <i class="bi bi-box-seam"></i> المنتجات
                            </a>
                        </li>
                        @endcan
                        @can('categories.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}"
                               href="{{ route('categories.index') }}">
                                <i class="bi bi-tags"></i> الفئات
                            </a>
                        </li>
                        @endcan
                        @can('sales.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}"
                               href="{{ route('sales.index') }}">
                                <i class="bi bi-receipt"></i> المبيعات
                            </a>
                        </li>
                        @endcan
                        @can('inventory.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('products.low-stock','products.expiring') ? 'active' : '' }}"
                               href="{{ route('products.low-stock') }}">
                                <i class="bi bi-exclamation-triangle"></i> تنبيهات المخزون
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
                @endcanany

                {{-- ── Purchases ── --}}
                @canany(['suppliers.view','purchases.view'])
                @php $s2 = request()->routeIs('suppliers.*','purchases.*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$s2 ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns2"
                            aria-expanded="{{ $s2 ? 'true' : 'false' }}">
                        <i class="bi bi-truck sec-icon"></i>
                        <span>المشتريات</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $s2 ? 'show' : '' }}" id="ns2">
                    <ul class="nav flex-column sub-nav">
                        @can('suppliers.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}"
                               href="{{ route('suppliers.index') }}">
                                <i class="bi bi-building"></i> الموردون
                            </a>
                        </li>
                        @endcan
                        @can('purchases.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}"
                               href="{{ route('purchases.index') }}">
                                <i class="bi bi-bag-plus"></i> فواتير الشراء
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
                @endcanany

                {{-- ── Customers ── --}}
                @can('customers.view')
                @php $s3 = request()->routeIs('customers.*','customer-payments.*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$s3 ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns3"
                            aria-expanded="{{ $s3 ? 'true' : 'false' }}">
                        <i class="bi bi-people sec-icon"></i>
                        <span>العملاء</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $s3 ? 'show' : '' }}" id="ns3">
                    <ul class="nav flex-column sub-nav">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customers.*','customer-payments.*') ? 'active' : '' }}"
                               href="{{ route('customers.index') }}">
                                <i class="bi bi-person-lines-fill"></i> العملاء والذمم
                            </a>
                        </li>
                    </ul>
                </div>
                @endcan

                {{-- ── HR ── --}}
                @canany(['hr.view_employees','hr.view_payroll','hr.view_attendance','hr.view_shifts'])
                @php $s4 = request()->routeIs('hr.*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$s4 ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns4"
                            aria-expanded="{{ $s4 ? 'true' : 'false' }}">
                        <i class="bi bi-person-badge sec-icon"></i>
                        <span>الموارد البشرية</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $s4 ? 'show' : '' }}" id="ns4">
                    <ul class="nav flex-column sub-nav">
                        @can('hr.view_employees')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.employees.*') ? 'active' : '' }}"
                               href="{{ route('hr.employees.index') }}">
                                <i class="bi bi-people"></i> الموظفون
                            </a>
                        </li>
                        @endcan
                        @can('hr.view_shifts')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.shifts.*') ? 'active' : '' }}"
                               href="{{ route('hr.shifts.index') }}">
                                <i class="bi bi-clock"></i> الورديات
                            </a>
                        </li>
                        @endcan
                        @can('hr.view_attendance')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.attendance.*') ? 'active' : '' }}"
                               href="{{ route('hr.attendance.daily') }}">
                                <i class="bi bi-calendar-check"></i> الحضور والانصراف
                            </a>
                        </li>
                        @endcan
                        @can('hr.view_payroll')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.payroll.*') ? 'active' : '' }}"
                               href="{{ route('hr.payroll.index') }}">
                                <i class="bi bi-cash-stack"></i> مسير الرواتب
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
                @endcanany

                {{-- ── Accounting ── --}}
                @canany(['accounts.view','journal_entries.view','ledger.view','trial_balance.view','financial_statements.view','accounting.year_end_close','reversals.view','audit_logs.view'])
                @php $s5 = request()->routeIs('accounting.*','accounts.*','reversals.*','journal_entries.*','audit.*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$s5 ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns5"
                            aria-expanded="{{ $s5 ? 'true' : 'false' }}">
                        <i class="bi bi-calculator sec-icon"></i>
                        <span>المحاسبة</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $s5 ? 'show' : '' }}" id="ns5">
                    <ul class="nav flex-column sub-nav">
                        @can('accounts.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.index') ? 'active' : '' }}"
                               href="{{ route('accounting.index') }}">
                                <i class="bi bi-calculator"></i> لوحة المحاسبة
                            </a>
                        </li>
                        @endcan
                        @can('journal_entries.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('journal_entries.*') ? 'active' : '' }}"
                               href="{{ route('journal_entries.index') }}">
                                <i class="bi bi-journal-text"></i> القيود اليومية
                            </a>
                        </li>
                        @endcan
                        @can('financial_statements.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.income-statement') ? 'active' : '' }}"
                               href="{{ route('accounting.income-statement') }}">
                                <i class="bi bi-bar-chart-line"></i> قائمة الدخل
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.balance-sheet') ? 'active' : '' }}"
                               href="{{ route('accounting.balance-sheet') }}">
                                <i class="bi bi-layout-split"></i> الميزانية العمومية
                            </a>
                        </li>
                        @endcan
                        @can('ledger.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.ledger.*') ? 'active' : '' }}"
                               href="{{ route('accounting.ledger.index') }}">
                                <i class="bi bi-journal-bookmark"></i> دفتر الأستاذ
                            </a>
                        </li>
                        @endcan
                        @can('trial_balance.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.trial-balance') ? 'active' : '' }}"
                               href="{{ route('accounting.trial-balance') }}">
                                <i class="bi bi-check2-square"></i> ميزان المراجعة
                            </a>
                        </li>
                        @endcan
                        @can('accounting.year_end_close')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.year-end-closing*') ? 'active' : '' }}"
                               href="{{ route('accounting.year-end-closing') }}">
                                <i class="bi bi-calendar-check"></i> إقفال نهاية السنة
                            </a>
                        </li>
                        @endcan
                        @canany(['accounts.create','accounts.edit','reversals.view','audit_logs.view'])
                        <div class="sidebar-sep"></div>
                        @can('accounts.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}"
                               href="{{ route('accounts.index') }}">
                                <i class="bi bi-list-columns-reverse"></i> شجرة الحسابات
                            </a>
                        </li>
                        @endcan
                        @can('reversals.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reversals.*') ? 'active' : '' }}"
                               href="{{ route('reversals.index') }}">
                                <i class="bi bi-arrow-counterclockwise"></i> قيود التصحيح
                            </a>
                        </li>
                        @endcan
                        @can('audit_logs.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}"
                               href="{{ route('audit.logs.index') }}">
                                <i class="bi bi-shield-check"></i> سجل المراجعة
                            </a>
                        </li>
                        @endcan
                        @endcanany
                    </ul>
                </div>
                @endcanany

                {{-- ── Reports ── --}}
                @can('reports.view')
                @php $s6 = request()->routeIs('reports.*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$s6 ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns6"
                            aria-expanded="{{ $s6 ? 'true' : 'false' }}">
                        <i class="bi bi-graph-up sec-icon"></i>
                        <span>التقارير</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $s6 ? 'show' : '' }}" id="ns6">
                    <ul class="nav flex-column sub-nav">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('reports') ? 'active' : '' }}"
                               href="{{ route('reports.index') }}">
                                <i class="bi bi-grid"></i> جميع التقارير
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.sales') ? 'active' : '' }}"
                               href="{{ route('reports.sales') }}">
                                <i class="bi bi-graph-up-arrow"></i> تقرير المبيعات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.profit') ? 'active' : '' }}"
                               href="{{ route('reports.profit') }}">
                                <i class="bi bi-currency-dollar"></i> تقرير الأرباح
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.ar-aging') ? 'active' : '' }}"
                               href="{{ route('reports.ar-aging') }}">
                                <i class="bi bi-people"></i> تقادم ذمم العملاء
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.ap-aging') ? 'active' : '' }}"
                               href="{{ route('reports.ap-aging') }}">
                                <i class="bi bi-truck"></i> تقادم ذمم الموردين
                            </a>
                        </li>
                    </ul>
                </div>
                @endcan

                {{-- ── System Management ── --}}
                @canany(['users.view','roles.view','settings.view'])
                @php $s7 = request()->routeIs('users.*','roles.*','permissions.*','settings.*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$s7 ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns7"
                            aria-expanded="{{ $s7 ? 'true' : 'false' }}">
                        <i class="bi bi-shield-lock sec-icon"></i>
                        <span>إدارة النظام</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $s7 ? 'show' : '' }}" id="ns7">
                    <ul class="nav flex-column sub-nav">
                        @can('users.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                               href="{{ route('users.index') }}">
                                <i class="bi bi-people-fill"></i> المستخدمون
                            </a>
                        </li>
                        @endcan
                        @can('roles.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}"
                               href="{{ route('roles.index') }}">
                                <i class="bi bi-shield-lock"></i> الأدوار والصلاحيات
                            </a>
                        </li>
                        @endcan
                        @can('settings.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}"
                               href="{{ route('settings.edit') }}">
                                <i class="bi bi-gear-fill"></i> إعدادات النظام
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
                @endcanany

                <!-- ── Help & Logout ── -->
                <div class="sidebar-sep mt-2"></div>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('help') ? 'active' : '' }}"
                       href="{{ route('help') }}">
                        <i class="bi bi-question-circle"></i> دليل الاستخدام
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger"
                       href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </li>

                @endauth
                </ul>

            </div>
        </nav>

        <!-- ═══════════════ MAIN CONTENT ═══════════════ -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <!-- Top bar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4 mt-3 rounded">
                <div class="container-fluid">
                    <span class="navbar-brand fw-semibold">@yield('page-title', 'لوحة التحكم')</span>
                    <div class="d-flex align-items-center gap-2">
                        @auth
                            <span class="badge bg-primary">{{ auth()->user()->getRoleNames()->first() ?? auth()->user()->role }}</span>
                        @endauth
                        <span class="text-muted small"><i class="bi bi-clock"></i> {{ now()->format('Y-m-d H:i') }}</span>
                    </div>
                </div>
            </nav>

            <!-- Flash messages -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle"></i> {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
    // Auto-dismiss alerts
    setTimeout(function() { $('.alert').fadeOut('slow'); }, 5000);

    // Shared DT config used by both client-side and server-side tables
    window.dtDefaults = {
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json' },
        pageLength: 25,
        order: [],
        dom: "<'row mb-2 align-items-center'" +
                 "<'col-sm-2'l>" +
                 "<'col-sm-6'B>" +
                 "<'col-sm-4'f>" +
             ">" +
             "<'row'<'col-12'tr>>" +
             "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
        buttons: [
            { extend:'excelHtml5', text:'<i class="bi bi-file-earmark-excel"></i> Excel',
              className:'btn btn-sm btn-success', exportOptions:{ columns:':not(:last-child)' } },
            { extend:'csvHtml5',   text:'<i class="bi bi-filetype-csv"></i> CSV',
              className:'btn btn-sm btn-secondary', bom:true, exportOptions:{ columns:':not(:last-child)' } },
            { extend:'print',      text:'<i class="bi bi-printer"></i> طباعة',
              className:'btn btn-sm btn-info', exportOptions:{ columns:':not(:last-child)' } },
        ],
    };

    // Auto-init client-side tables (.dt-table class)
    $(function() {
        $('.dt-table').each(function() {
            var title = $(this).data('title') || document.title;
            var cfg = $.extend(true, {}, window.dtDefaults);
            cfg.buttons.forEach(function(b){ b.title = title; });
            $(this).DataTable(cfg);
        });
    });
</script>

@yield('scripts')

<script>
    // Guarantee the sidebar section containing the active sub-link stays open,
    // even if the server-side routeIs() detection misses a route name.
    (function () {
        var active = document.querySelector('#sidebarNav .sub-nav .nav-link.active');
        if (!active) return;

        var collapse = active.closest('.collapse');
        if (!collapse) return;

        if (!collapse.classList.contains('show')) {
            collapse.classList.add('show');
        }

        var btn = document.querySelector('[data-bs-target="#' + collapse.id + '"]');
        if (btn) {
            btn.classList.remove('collapsed');
            btn.setAttribute('aria-expanded', 'true');
        }
    })();
</script>
</body>
</html>
