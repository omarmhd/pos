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
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

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

        /* ── Custom badge colors ── */
        .bg-purple { background-color: #7c3aed !important; color: #fff; }
        .bg-orange { background-color: #ea580c !important; color: #fff; }

        /* ── Tailwind v4 conflict fix ──
           Tailwind v4 generates .collapse { visibility: collapse } as a utility
           which hides Bootstrap collapse panels. Override it here so the inline
           style takes priority without needing a Vite rebuild on the server. */
        .collapse, .collapsing { visibility: visible !important; }
        .collapse:not(.show) { display: none !important; }

        /* ══════════════════════════════════════════
           RESPONSIVE — Mobile / Tablet / Desktop
           ══════════════════════════════════════════ */

        /* ── Sidebar overlay (mobile backdrop) ── */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.48);
            z-index: 1054;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.28s, visibility 0.28s;
            backdrop-filter: blur(2px);
        }
        .sidebar-overlay.show { opacity: 1; visibility: visible; }

        /* ── Mobile sidebar close button ── */
        .sidebar-close-btn {
            display: none;
            position: absolute;
            top: 14px;
            left: 14px;
            background: rgba(255,255,255,0.12);
            border: none;
            color: #fff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            z-index: 2;
            transition: background 0.18s;
        }
        .sidebar-close-btn:hover { background: rgba(255,255,255,0.22); }

        /* ── Mobile breakpoint (< 768px) ── */
        @media (max-width: 767.98px) {
            /* Sidebar becomes a fixed right-drawer (RTL) */
            nav.sidebar {
                position: fixed !important;
                top: 0;
                right: 0;
                height: 100dvh;
                width: 272px;
                z-index: 1055;
                display: flex !important;          /* override d-md-block */
                transform: translateX(110%);        /* hide off-screen right */
                transition: transform 0.28s cubic-bezier(0.4,0,0.2,1);
                box-shadow: none;
            }
            nav.sidebar.sidebar-open {
                transform: translateX(0);
                box-shadow: -6px 0 28px rgba(0,0,0,0.32);
            }
            /* Close btn visible on mobile */
            .sidebar-close-btn { display: flex; }
            /* Extra padding so brand doesn't overlap close btn */
            .sidebar-brand { padding-left: 44px; }

            /* Main content: full width */
            main.col-md-9 {
                width: 100% !important;
                max-width: 100% !important;
                padding-left: 12px !important;
                padding-right: 12px !important;
            }

            /* Top navbar: tighter on mobile */
            .navbar { padding: 6px 10px !important; border-radius: 8px !important; }
            .navbar .navbar-brand { font-size: 0.88rem !important; }

            /* Hide clock on very small screens */
            .topbar-clock { display: none !important; }

            /* Cards */
            .card-body { padding: 0.8rem !important; }
            .card-header { padding: 0.65rem 0.8rem !important; }
            .stat-card { padding: 14px !important; }

            /* Buttons */
            .btn-action { padding: 3px 7px !important; font-size: 0.8rem !important; }

            /* Page headings */
            h4.page-title { font-size: 1rem !important; }

            /* Fix row overflow */
            .container-fluid { overflow-x: hidden; }
        }

        /* ── Tablet breakpoint (768px – 991px) ── */
        @media (min-width: 768px) and (max-width: 991.98px) {
            nav.sidebar { width: 220px; }
            .sidebar .nav-link { font-size: 0.82rem; padding: 7px 10px; }
            .nav-section-toggle { font-size: 0.78rem; }
            main.col-md-9 { padding-left: 14px !important; padding-right: 14px !important; }
        }

        /* ── Tables: always scrollable ── */
        .table-responsive { -webkit-overflow-scrolling: touch; }
        /* Wrap any card-body table not already in .table-responsive */
        .card-body > .table { overflow-x: auto; display: block; white-space: nowrap; }
        .card-body > .table-responsive > .table { display: table; white-space: normal; }

        /* ── DataTables responsive ── */
        @media (max-width: 575.98px) {
            .dataTables_wrapper .row.mb-2 { flex-direction: column; gap: 6px; }
            .dataTables_wrapper .row.mb-2 > div { width: 100% !important; max-width: 100% !important; }
            .dt-buttons { flex-wrap: wrap; gap: 4px; }
            .dt-buttons .btn { font-size: 0.72rem; padding: 3px 7px; }
            .dataTables_filter input { width: 100% !important; }
        }

        /* ── Forms: full-width inputs on mobile ── */
        @media (max-width: 575.98px) {
            .row.g-3 > [class*="col-"], .row.mb-3 > [class*="col-"] { flex: 0 0 100%; max-width: 100%; }
        }

        /* ── Hamburger button (only on mobile) ── */
        .sidebar-toggle-btn {
            display: none;
            background: #1a2535;
            border: none;
            color: #fff;
            border-radius: 7px;
            padding: 5px 10px;
            font-size: 1.15rem;
            line-height: 1;
            cursor: pointer;
        }
        @media (max-width: 767.98px) { .sidebar-toggle-btn { display: inline-flex; align-items: center; } }

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
<!-- Mobile sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="container-fluid">
    <div class="row">

        <!-- ═══════════════ SIDEBAR ═══════════════ -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar p-0">
            <div class="position-sticky pt-0" style="top:0; overflow-y:auto; max-height:100dvh;">

                <!-- Mobile close button -->
                <button class="sidebar-close-btn" onclick="closeSidebar()" aria-label="إغلاق">✕</button>

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
                {{-- ══════════════════════════════════════════════════════════════════ --}}
                {{-- 1. POS — وصول سريع دائماً في الأعلى (مثل SAP Fiori LaunchPad)   --}}
                {{-- ══════════════════════════════════════════════════════════════════ --}}
                @can('sales.create')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pos.index') || request()->is('pos') ? 'active' : '' }}"
                       href="{{ route('pos.index') }}">
                        <i class="bi bi-cart-check-fill text-success"></i> <strong>نقطة البيع (POS)</strong>
                    </a>
                </li>
                @endcan
                @can('pos.shifts.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pos.shifts.*') ? 'active' : '' }}"
                       href="{{ route('pos.shifts.index') }}">
                        <i class="bi bi-cash-register text-warning"></i> الورديات النقدية
                        @php
                            try {
                                $myOpenShift = \App\Models\CashShift::activeForUser(auth()->id());
                            } catch(\Throwable) {
                                $myOpenShift = null;
                            }
                        @endphp
                        @if($myOpenShift)
                            <span class="badge bg-success ms-1" style="font-size:.65rem">مفتوحة</span>
                        @endif
                    </a>
                </li>
                @endcan

                {{-- ══════════════════════════════════════════════════════════════════ --}}
                {{-- 2. المبيعات — Sales Cycle: Quote → Order → Invoice → Return → AR  --}}
                {{-- ══════════════════════════════════════════════════════════════════ --}}
                @canany(['quotations.view','sales_orders.view','sales.view','sales.returns.view','customers.view','services.view'])
                @php $ns_sales = request()->routeIs('sales-quotations.*','sales-orders.*','sales.*','sale-returns.*','service-invoices.*','customers.*','customer-payments.*')
                              || request()->is('sales-quotations*','sales-orders*','sale-returns*','service-invoices*','customers*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$ns_sales ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns-sales"
                            aria-expanded="{{ $ns_sales ? 'true' : 'false' }}">
                        <i class="bi bi-graph-up-arrow sec-icon"></i>
                        <span>المبيعات</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $ns_sales ? 'show' : '' }}" id="ns-sales">
                    <ul class="nav flex-column sub-nav">
                        {{-- دورة البيع B2B --}}
                        @can('quotations.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('sales-quotations.*') ? 'active' : '' }}"
                               href="{{ route('sales-quotations.index') }}">
                                <i class="bi bi-file-earmark-richtext"></i> عروض الأسعار
                            </a>
                        </li>
                        @endcan
                        @can('sales_orders.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('sales-orders.*') ? 'active' : '' }}"
                               href="{{ route('sales-orders.index') }}">
                                <i class="bi bi-bag-check"></i> أوامر البيع
                            </a>
                        </li>
                        @endcan
                        @can('sales.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}"
                               href="{{ route('sales.index') }}">
                                <i class="bi bi-receipt"></i> فواتير البيع
                            </a>
                        </li>
                        @endcan
                        @can('sales.returns.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('sale-returns.*') ? 'active' : '' }}"
                               href="{{ route('sale-returns.index') }}">
                                <i class="bi bi-arrow-return-left"></i> مرتجعات المبيعات
                            </a>
                        </li>
                        @endcan
                        @can('services.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('service-invoices.*') ? 'active' : '' }}"
                               href="{{ route('service-invoices.index') }}">
                                <i class="bi bi-lightning-charge"></i> فواتير إيراد الخدمات
                            </a>
                        </li>
                        @endcan
                        {{-- ذمم العملاء — AR --}}
                        @can('customers.view')
                        <div class="sidebar-sep"></div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customers.*','customer-payments.*') ? 'active' : '' }}"
                               href="{{ route('customers.index') }}">
                                <i class="bi bi-person-lines-fill"></i> العملاء والذمم المدينة
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
                @endcanany

                {{-- ══════════════════════════════════════════════════════════════════ --}}
                {{-- 3. المشتريات — Purchase Cycle: PO → Invoice → Return → AP         --}}
                {{-- ══════════════════════════════════════════════════════════════════ --}}
                @canany(['suppliers.view','purchase_orders.view','purchases.view','purchases.returns.view','expenses.view','customs.view'])
                @php $ns_purch = request()->routeIs('suppliers.*','purchase-orders.*','purchases.*','purchase-returns.*','expense-invoices.*','customs-declarations.*')
                              || request()->is('suppliers*','purchase-orders*','purchase-returns*','expense-invoices*','customs-declarations*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$ns_purch ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns-purch"
                            aria-expanded="{{ $ns_purch ? 'true' : 'false' }}">
                        <i class="bi bi-truck sec-icon"></i>
                        <span>المشتريات</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $ns_purch ? 'show' : '' }}" id="ns-purch">
                    <ul class="nav flex-column sub-nav">
                        {{-- الموردون — AP --}}
                        @can('suppliers.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}"
                               href="{{ route('suppliers.index') }}">
                                <i class="bi bi-building-fill"></i> الموردون والذمم الدائنة
                            </a>
                        </li>
                        <div class="sidebar-sep"></div>
                        @endcan
                        {{-- دورة الشراء --}}
                        @can('purchase_orders.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}"
                               href="{{ route('purchase-orders.index') }}">
                                <i class="bi bi-file-earmark-text"></i> أوامر الشراء
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
                        @can('purchases.returns.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('purchase-returns.*') ? 'active' : '' }}"
                               href="{{ route('purchase-returns.index') }}">
                                <i class="bi bi-arrow-return-right"></i> مرتجعات المشتريات
                            </a>
                        </li>
                        @endcan
                        {{-- المصروفات التشغيلية --}}
                        @can('expenses.view')
                        <div class="sidebar-sep"></div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('expense-invoices.*') ? 'active' : '' }}"
                               href="{{ route('expense-invoices.index') }}">
                                <i class="bi bi-receipt-cutoff"></i> فواتير المصروفات
                            </a>
                        </li>
                        @endcan
                        {{-- الإقرارات الجمركية --}}
                        @can('customs.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customs-declarations.*') ? 'active' : '' }}"
                               href="{{ route('customs-declarations.index') }}">
                                <i class="bi bi-box-arrow-in-down"></i> الإقرارات الجمركية
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
                @endcanany

                {{-- ══════════════════════════════════════════════════════════════════ --}}
                {{-- 4. المخزون — Inventory: Items, Locations, Transfers, Count        --}}
                {{-- ══════════════════════════════════════════════════════════════════ --}}
                @canany(['products.view','categories.view','inventory.view','inventory.adjust','inventory.count','stock_transfers.view'])
                @php $ns_inv = request()->routeIs('products.*','categories.*','inventory.*','stock-transfers.*')
                            || request()->is('products*','categories*','inventory*','stock-transfers*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$ns_inv ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns-inv"
                            aria-expanded="{{ $ns_inv ? 'true' : 'false' }}">
                        <i class="bi bi-boxes sec-icon"></i>
                        <span>المخزون</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $ns_inv ? 'show' : '' }}" id="ns-inv">
                    <ul class="nav flex-column sub-nav">
                        {{-- بطاقات الأصناف --}}
                        @can('products.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}"
                               href="{{ route('products.index') }}">
                                <i class="bi bi-box-seam"></i> الأصناف والمنتجات
                            </a>
                        </li>
                        @endcan
                        @can('categories.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}"
                               href="{{ route('categories.index') }}">
                                <i class="bi bi-diagram-3"></i> الفئات والتصنيفات
                            </a>
                        </li>
                        @endcan
                        {{-- حركات المخزون --}}
                        <div class="sidebar-sep"></div>
                        @can('stock_transfers.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('stock-transfers.*') ? 'active' : '' }}"
                               href="{{ route('stock-transfers.index') }}">
                                <i class="bi bi-arrow-left-right"></i> تحويلات (إعادة تعبئة)
                            </a>
                        </li>
                        @endcan
                        @can('inventory.adjust')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('inventory.adjustments.*') ? 'active' : '' }}"
                               href="{{ route('inventory.adjustments.index') }}">
                                <i class="bi bi-pencil-square"></i> تعديلات المخزون
                            </a>
                        </li>
                        @endcan
                        @can('inventory.count')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('inventory.sessions.*') ? 'active' : '' }}"
                               href="{{ route('inventory.sessions.index') }}">
                                <i class="bi bi-clipboard2-check"></i> الجرد الدوري
                            </a>
                        </li>
                        @endcan
                        {{-- التصنيع والتجميع --}}
                        @can('assemblies.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('assemblies.*') ? 'active' : '' }}"
                               href="{{ route('assemblies.index') }}">
                                <i class="bi bi-gear-wide-connected"></i> التصنيع والتجميع
                            </a>
                        </li>
                        @endcan
                        {{-- تنبيهات --}}
                        @can('inventory.view')
                        <div class="sidebar-sep"></div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('products.low-stock','products.expiring') ? 'active' : '' }}"
                               href="{{ route('products.low-stock') }}">
                                <i class="bi bi-exclamation-triangle text-warning"></i> تنبيهات المخزون
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('products.reorder') ? 'active' : '' }}"
                               href="{{ route('products.reorder') }}">
                                <i class="bi bi-arrow-repeat text-warning"></i> حد إعادة الطلب
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
                @endcanany

                {{-- ══════════════════════════════════════════════════════════════════ --}}
                {{-- 5. الخزينة — Treasury: Cash In (AR) + Cash Out (AP)               --}}
                {{-- ══════════════════════════════════════════════════════════════════ --}}
                @canany(['vouchers.view', 'checks.view'])
                @php $ns_treas = request()->is('vouchers*') || request()->is('checks*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$ns_treas ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns-treas"
                            aria-expanded="{{ $ns_treas ? 'true' : 'false' }}">
                        <i class="bi bi-bank sec-icon"></i>
                        <span>الخزينة والبنوك</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $ns_treas ? 'show' : '' }}" id="ns-treas">
                    <ul class="nav flex-column sub-nav">
                        @can('vouchers.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('vouchers/receipts*') ? 'active' : '' }}"
                               href="{{ route('vouchers.receipts.index') }}">
                                <i class="bi bi-arrow-down-circle-fill text-success"></i> سندات القبض (تحصيل)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('vouchers/payments*') ? 'active' : '' }}"
                               href="{{ route('vouchers.payments.index') }}">
                                <i class="bi bi-arrow-up-circle-fill text-danger"></i> سندات الصرف (دفعيات)
                            </a>
                        </li>
                        @endcan
                        @can('vouchers.create')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('vouchers/bulk*') ? 'active' : '' }}"
                               href="{{ route('vouchers.bulk.create') }}">
                                <i class="bi bi-stack text-primary"></i> السندات المتعددة (دفعي)
                            </a>
                        </li>
                        @endcan
                        @can('checks.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('checks*') ? 'active' : '' }}"
                               href="{{ route('checks.index') }}">
                                <i class="bi bi-bank2 text-primary"></i> الشيكات (واردة / صادرة)
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
                @endcanany

                {{-- ══════════════════════════════════════════════════════════════════ --}}
                {{-- 6. المحاسبة — GL → Reports → Fixed Assets → Closing → Audit       --}}
                {{-- ══════════════════════════════════════════════════════════════════ --}}
                @canany(['accounts.view','journal_entries.view','ledger.view','trial_balance.view','financial_statements.view','accounting.year_end_close','accounting.periods.view','fixed_assets.view','reversals.view'])
                @php $ns_acc = request()->routeIs('accounting.*','accounts.*','reversals.*','journal_entries.*','fixed-assets.*','fixed-asset-categories.*')
                           || request()->is('accounting*','accounts*','journal-entries*','reversals*','fixed-assets*','fixed-asset-categories*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$ns_acc ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns-acc"
                            aria-expanded="{{ $ns_acc ? 'true' : 'false' }}">
                        <i class="bi bi-calculator sec-icon"></i>
                        <span>المحاسبة</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $ns_acc ? 'show' : '' }}" id="ns-acc">
                    <ul class="nav flex-column sub-nav">
                        {{-- إدخال القيود --}}
                        @can('accounts.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.index') ? 'active' : '' }}"
                               href="{{ route('accounting.index') }}">
                                <i class="bi bi-speedometer2"></i> لوحة المحاسبة
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
                        {{-- دفاتر وكشوف --}}
                        <div class="sidebar-sep"></div>
                        @can('ledger.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.ledger.*') ? 'active' : '' }}"
                               href="{{ route('accounting.ledger.index') }}">
                                <i class="bi bi-journal-bookmark-fill"></i> دفتر الأستاذ العام
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
                        {{-- القوائم المالية --}}
                        @can('financial_statements.view')
                        <div class="sidebar-sep"></div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.income-statement') ? 'active' : '' }}"
                               href="{{ route('accounting.income-statement') }}">
                                <i class="bi bi-bar-chart-line-fill"></i> قائمة الدخل
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.balance-sheet') ? 'active' : '' }}"
                               href="{{ route('accounting.balance-sheet') }}">
                                <i class="bi bi-layout-split"></i> الميزانية العمومية
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.cash-flow') ? 'active' : '' }}"
                               href="{{ route('accounting.cash-flow') }}">
                                <i class="bi bi-cash-coin text-success"></i> التدفقات النقدية
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.consolidated-pl') ? 'active' : '' }}"
                               href="{{ route('accounting.consolidated-pl') }}">
                                <i class="bi bi-diagram-3 text-success"></i> قائمة دخل موحدة
                            </a>
                        </li>
                        @endcan
                        {{-- الأصول الثابتة — ينتمي للمحاسبة --}}
                        @can('fixed_assets.view')
                        <div class="sidebar-sep"></div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('fixed-assets.*') && !request()->routeIs('fixed-asset-categories.*') ? 'active' : '' }}"
                               href="{{ route('fixed-assets.index') }}">
                                <i class="bi bi-building-gear"></i> الأصول الثابتة
                            </a>
                        </li>
                        @endcan
                        @can('fixed_assets.create')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('fixed-asset-categories.*') ? 'active' : '' }}"
                               href="{{ route('fixed-asset-categories.index') }}">
                                <i class="bi bi-tags"></i> فئات الأصول الثابتة
                            </a>
                        </li>
                        @endcan
                        {{-- إعداد وإغلاق --}}
                        @can('accounts.view')
                        <div class="sidebar-sep"></div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}"
                               href="{{ route('accounts.index') }}">
                                <i class="bi bi-list-columns-reverse"></i> شجرة الحسابات
                            </a>
                        </li>
                        @endcan
                        @can('accounting.periods.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.periods.*') ? 'active' : '' }}"
                               href="{{ route('accounting.periods.index') }}">
                                <i class="bi bi-calendar-lock"></i> إغلاق الفترات
                            </a>
                        </li>
                        @endcan
                        @can('journal_entries.create')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.opening-balance*') ? 'active' : '' }}"
                               href="{{ route('accounting.opening-balance') }}">
                                <i class="bi bi-bank text-info"></i> الأرصدة الافتتاحية
                            </a>
                        </li>
                        @endcan
                        @can('financial_statements.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.consolidated-pl*') ? 'active' : '' }}"
                               href="{{ route('accounting.consolidated-pl') }}">
                                <i class="bi bi-diagram-3 text-success"></i> قائمة دخل موحدة
                            </a>
                        </li>
                        @endcan
                        @can('accounting.year_end_close')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounting.year-end-closing*') ? 'active' : '' }}"
                               href="{{ route('accounting.year-end-closing') }}">
                                <i class="bi bi-calendar-check-fill"></i> إقفال نهاية السنة
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
                        @can('branches.manage')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('inter-branch.*') ? 'active' : '' }}"
                               href="{{ route('inter-branch.index') }}">
                                <i class="bi bi-arrow-left-right text-primary"></i> التحويلات البينية
                            </a>
                        </li>
                        @endcan
                        @can('accounts.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('cost-centers.*') ? 'active' : '' }}"
                               href="{{ route('cost-centers.index') }}">
                                <i class="bi bi-diagram-2 text-info"></i> مراكز التكلفة
                            </a>
                        </li>
                        @endcan
                        @can('financial_statements.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('budgets.*') ? 'active' : '' }}"
                               href="{{ route('budgets.index') }}">
                                <i class="bi bi-calculator text-primary"></i> الموازنات
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
                @endcanany

                {{-- ══════════════════════════════════════════════════════════════════ --}}
                {{-- 7. الموارد البشرية — HR: Employees → Attendance → Payroll         --}}
                {{-- ══════════════════════════════════════════════════════════════════ --}}
                @canany(['hr.view_employees','hr.view_payroll','hr.view_attendance','hr.view_shifts','hr.manage_loans'])
                @php $ns_hr = request()->routeIs('hr.*') || request()->is('hr/*','hr'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$ns_hr ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns-hr"
                            aria-expanded="{{ $ns_hr ? 'true' : 'false' }}">
                        <i class="bi bi-person-badge sec-icon"></i>
                        <span>الموارد البشرية</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $ns_hr ? 'show' : '' }}" id="ns-hr">
                    <ul class="nav flex-column sub-nav">
                        @can('hr.view_employees')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.employees.*') ? 'active' : '' }}"
                               href="{{ route('hr.employees.index') }}">
                                <i class="bi bi-people-fill"></i> الموظفون
                            </a>
                        </li>
                        @endcan
                        @can('hr.view_shifts')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.shifts.*') ? 'active' : '' }}"
                               href="{{ route('hr.shifts.index') }}">
                                <i class="bi bi-clock-history"></i> الورديات
                            </a>
                        </li>
                        @endcan
                        @can('hr.view_attendance')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.attendance.daily') ? 'active' : '' }}"
                               href="{{ route('hr.attendance.daily') }}">
                                <i class="bi bi-calendar-check"></i> الحضور اليومي
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.attendance.monthly') ? 'active' : '' }}"
                               href="{{ route('hr.attendance.monthly') }}">
                                <i class="bi bi-calendar-month"></i> سجل الحضور الشهري
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.attendance.sync*') ? 'active' : '' }}"
                               href="{{ route('hr.attendance.sync') }}">
                                <i class="bi bi-fingerprint"></i> مزامنة جهاز البصمة
                            </a>
                        </li>
                        @endcan
                        <div class="sidebar-sep"></div>
                        @can('hr.view_payroll')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.payroll.*') ? 'active' : '' }}"
                               href="{{ route('hr.payroll.index') }}">
                                <i class="bi bi-cash-stack"></i> مسير الرواتب
                            </a>
                        </li>
                        @endcan
                        @can('hr.manage_loans')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.loans.*') ? 'active' : '' }}"
                               href="{{ route('hr.loans.index') }}">
                                <i class="bi bi-coin"></i> سلف الموظفين
                            </a>
                        </li>
                        @endcan
                        @can('hr.leaves.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.leaves.*') ? 'active' : '' }}"
                               href="{{ route('hr.leaves.index') }}">
                                <i class="bi bi-calendar-check text-success"></i> الإجازات
                                @php
                                    try {
                                        $pendingLeaves = \App\Models\EmployeeLeave::where('status','pending')->count();
                                    } catch(\Throwable) {
                                        $pendingLeaves = 0;
                                    }
                                @endphp
                                @if($pendingLeaves)
                                    <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">{{ $pendingLeaves }}</span>
                                @endif
                            </a>
                        </li>
                        @endcan
                        @can('hr.eosb.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('hr.eosb.*') ? 'active' : '' }}"
                               href="{{ route('hr.eosb.index') }}">
                                <i class="bi bi-person-badge text-warning"></i> مخصصات نهاية الخدمة
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
                @endcanany

                {{-- ══════════════════════════════════════════════════════════════════ --}}
                {{-- 8. التقارير — Reports Dashboard                                   --}}
                {{-- ══════════════════════════════════════════════════════════════════ --}}
                @canany(['reports.view', 'res.view'])
                @php $ns_rep = request()->routeIs('reports.*', 'res.*') || request()->is('reports*', 'res*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$ns_rep ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns-rep"
                            aria-expanded="{{ $ns_rep ? 'true' : 'false' }}">
                        <i class="bi bi-graph-up sec-icon"></i>
                        <span>التقارير</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $ns_rep ? 'show' : '' }}" id="ns-rep">
                    <ul class="nav flex-column sub-nav">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('reports') ? 'active' : '' }}"
                               href="{{ route('reports.index') }}">
                                <i class="bi bi-grid-1x2"></i> جميع التقارير
                            </a>
                        </li>
                        @can('res.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('res.*') ? 'active' : '' }}"
                               href="{{ route('res.index') }}">
                                <i class="bi bi-file-earmark-bar-graph"></i> كشوف الإيرادات والمصروفات
                            </a>
                        </li>
                        @endcan
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.sales') ? 'active' : '' }}"
                               href="{{ route('reports.sales') }}">
                                <i class="bi bi-graph-up-arrow"></i> تقرير المبيعات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.purchases') ? 'active' : '' }}"
                               href="{{ route('reports.purchases') }}">
                                <i class="bi bi-graph-down-arrow"></i> تقرير المشتريات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.profit') ? 'active' : '' }}"
                               href="{{ route('reports.profit') }}">
                                <i class="bi bi-cash-coin"></i> تقرير الأرباح
                            </a>
                        </li>
                        <div class="sidebar-sep"></div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.ar-aging') ? 'active' : '' }}"
                               href="{{ route('reports.ar-aging') }}">
                                <i class="bi bi-person-exclamation"></i> تقادم ذمم العملاء
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.ap-aging') ? 'active' : '' }}"
                               href="{{ route('reports.ap-aging') }}">
                                <i class="bi bi-building-exclamation"></i> تقادم ذمم الموردين
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.inventory') ? 'active' : '' }}"
                               href="{{ route('reports.inventory') }}">
                                <i class="bi bi-boxes"></i> تقرير المخزون
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.top-products') ? 'active' : '' }}"
                               href="{{ route('reports.top-products') }}">
                                <i class="bi bi-star-fill text-warning"></i> أعلى الأصناف مبيعاً
                            </a>
                        </li>
                    </ul>
                </div>
                @endcan

                {{-- ══════════════════════════════════════════════════════════════════ --}}
                {{-- 9. الإعدادات — System Administration Only                         --}}
                {{-- ══════════════════════════════════════════════════════════════════ --}}
                @canany(['users.view','roles.view','settings.view','branches.view','pos_terminals.view','price_lists.view','audit_logs.view'])
                @php $ns_set = request()->routeIs('users.*','roles.*','permissions.*','settings.*','branches.*','warehouses.*','pos-terminals.*','price-lists.*','audit.*')
                           || request()->is('users*','roles*','permissions*','settings*','branches*','warehouses*','pos-terminals*','price-lists*','audit-logs*'); @endphp
                <li class="nav-item">
                    <button class="nav-section-toggle {{ !$ns_set ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse" data-bs-target="#ns-set"
                            aria-expanded="{{ $ns_set ? 'true' : 'false' }}">
                        <i class="bi bi-gear-wide-connected sec-icon"></i>
                        <span>الإعدادات</span>
                        <i class="bi bi-chevron-down chevron"></i>
                    </button>
                </li>
                <div class="collapse {{ $ns_set ? 'show' : '' }}" id="ns-set">
                    <ul class="nav flex-column sub-nav">
                        {{-- إدارة المستخدمين --}}
                        @can('users.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                               href="{{ route('users.index') }}">
                                <i class="bi bi-person-gear"></i> المستخدمون
                            </a>
                        </li>
                        @endcan
                        @can('roles.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}"
                               href="{{ route('roles.index') }}">
                                <i class="bi bi-shield-lock-fill"></i> الأدوار والصلاحيات
                            </a>
                        </li>
                        @endcan
                        {{-- البنية التشغيلية --}}
                        @can('branches.view')
                        <div class="sidebar-sep"></div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('branches.*') ? 'active' : '' }}"
                               href="{{ route('branches.index') }}">
                                <i class="bi bi-building-fill-check"></i> الفروع
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('warehouses.*') ? 'active' : '' }}"
                               href="{{ route('warehouses.index') }}">
                                <i class="bi bi-archive-fill"></i> المخازن والمعارض
                            </a>
                        </li>
                        @endcan
                        @can('pos_terminals.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pos-terminals.*') ? 'active' : '' }}"
                               href="{{ route('pos-terminals.index') }}">
                                <i class="bi bi-cash-register"></i> نقاط البيع (Terminals)
                            </a>
                        </li>
                        @endcan
                        {{-- السياسات التجارية --}}
                        @can('price_lists.view')
                        <div class="sidebar-sep"></div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('price-lists.*') ? 'active' : '' }}"
                               href="{{ route('price-lists.index') }}">
                                <i class="bi bi-tags-fill"></i> قوائم الأسعار
                            </a>
                        </li>
                        @endcan
                        @can('purchase_price_lists.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('purchase-price-lists.*') ? 'active' : '' }}"
                               href="{{ route('purchase-price-lists.index') }}">
                                <i class="bi bi-tags"></i> قوائم أسعار الشراء
                            </a>
                        </li>
                        @endcan
                        @can('currencies.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('currencies.*') ? 'active' : '' }}"
                               href="{{ route('currencies.index') }}">
                                <i class="bi bi-currency-exchange"></i> العملات
                            </a>
                        </li>
                        @endcan
                        {{-- إعدادات النظام --}}
                        @can('settings.view')
                        <div class="sidebar-sep"></div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}"
                               href="{{ route('settings.edit') }}">
                                <i class="bi bi-sliders"></i> إعدادات النظام
                            </a>
                        </li>
                        @endcan
                        {{-- سجل التدقيق — هنا وليس في المحاسبة (SAP/Oracle: Administration → Security) --}}
                        @can('audit_logs.view')
                        <div class="sidebar-sep"></div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}"
                               href="{{ route('audit.logs.index') }}">
                                <i class="bi bi-shield-check"></i> سجل التدقيق
                            </a>
                        </li>
                        @endcan
                        @can('settings.manage')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('backup.*') ? 'active' : '' }}"
                               href="{{ route('backup.index') }}">
                                <i class="bi bi-cloud-arrow-up text-info"></i> النسخ الاحتياطي
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
            <nav class="navbar navbar-light bg-light mb-4 mt-3 rounded">
                <div class="container-fluid gap-2">
                    <!-- Hamburger (mobile only) -->
                    <button class="sidebar-toggle-btn" onclick="openSidebar()" aria-label="القائمة">
                        <i class="bi bi-list"></i>
                    </button>
                    <span class="navbar-brand fw-semibold mb-0 me-auto">@yield('page-title', 'لوحة التحكم')</span>
                    <div class="d-flex align-items-center gap-2">
                        @auth
                            <span class="badge bg-primary">{{ auth()->user()->getRoleNames()->first() ?? auth()->user()->role }}</span>
                        @endauth
                        <span class="text-muted small topbar-clock"><i class="bi bi-clock"></i> {{ now()->format('Y-m-d H:i') }}</span>
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

            <div class="text-center text-muted small mt-4 pb-3 border-top pt-3">
                <i class="bi bi-check2-circle text-success"></i>
                <strong>الميزان</strong> — نظام إدارة متكامل للمحلات التجارية
                &nbsp;|&nbsp; النسخة التجريبية
                &nbsp;|&nbsp; للتواصل والدعم الفني عبر واتساب:
                <a href="https://wa.me/970592676623" target="_blank" class="text-success text-decoration-none fw-semibold">
                    <i class="bi bi-whatsapp"></i> ‎+970 592 676 623
                </a>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
    // ── Mobile sidebar ──────────────────────────────────────
    function openSidebar() {
        document.querySelector('nav.sidebar').classList.add('sidebar-open');
        document.getElementById('sidebarOverlay').classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        document.querySelector('nav.sidebar').classList.remove('sidebar-open');
        document.getElementById('sidebarOverlay').classList.remove('show');
        document.body.style.overflow = '';
    }
    // Auto-close sidebar when a nav link is clicked on mobile
    document.querySelectorAll('nav.sidebar a.nav-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 768) closeSidebar();
        });
    });
    // Close on resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) closeSidebar();
    });

    // ── Alerts: stay until user closes them manually ──────────
    // Alerts do NOT auto-dismiss — user must click ✕ to close

    // Shared DT config used by both client-side and server-side tables
    window.dtDefaults = {
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json' },
        responsive: true,
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

    /**
     * dtWireFilters — wire a filter form/container to a Yajra or custom-JSON DataTable.
     *
     * Usage (in page @section('scripts')):
     *   var table = $('#my-table').DataTable({...});
     *   dtWireFilters(table, '#filterForm');
     *
     * Behaviour:
     *   - Prevents the form's default submit (no page reload)
     *   - On any input/select change inside the container → table.ajax.reload()
     *   - Pressing Enter inside a text input also reloads (not navigates)
     *
     * The DataTable's ajax.data() function should read filter values dynamically:
     *   data: function(d) { dtCollectFilters(d, '#filterForm'); }
     */
    window.dtWireFilters = function(table, formSelector) {
        var $form = $(formSelector);
        $form.on('submit', function(e) { e.preventDefault(); table.ajax.reload(); });
        $form.find('select, input[type="date"], input[type="text"]').on('change input', function() {
            table.ajax.reload();
        });
    };

    /**
     * dtCollectFilters — collect all named inputs inside a container into a DataTables data object.
     *
     * Usage inside ajax.data():
     *   data: function(d) { dtCollectFilters(d, '#filterForm'); }
     */
    window.dtCollectFilters = function(d, formSelector) {
        $(formSelector).find('[name]').each(function() {
            var val = $(this).val();
            if (val !== '' && val !== null) {
                d[$(this).attr('name')] = val;
            }
        });
    };
</script>

@yield('scripts')

{{-- ══════════════════════════════════════════════════════════════════════
     Session Timeout — warns N seconds before expiry, force-logs out after.
     SESSION_LIFETIME is read from Laravel config (minutes) and converted
     to seconds for the JS countdown.
     ══════════════════════════════════════════════════════════════════════ --}}
@auth
<div class="modal fade" id="sessionWarningModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning text-dark py-2">
                <h6 class="modal-title mb-0">
                    <i class="bi bi-clock-history me-1"></i>انتهاء الجلسة قريباً
                </h6>
            </div>
            <div class="modal-body text-center py-3">
                <div class="fs-2 fw-bold text-danger font-monospace" id="sessionCountdown">02:00</div>
                <p class="text-muted small mt-2 mb-0">ستنتهي جلستك تلقائياً — انقر للمتابعة</p>
            </div>
            <div class="modal-footer py-2 justify-content-center gap-2">
                <button type="button" class="btn btn-success btn-sm" id="btnKeepAlive">
                    <i class="bi bi-arrow-repeat me-1"></i>متابعة العمل
                </button>
                <a href="{{ route('logout') }}"
                   onclick="event.preventDefault(); document.getElementById('logoutForm').submit();"
                   class="btn btn-outline-secondary btn-sm">خروج الآن</a>
            </div>
        </div>
    </div>
</div>
<form id="logoutForm" method="POST" action="{{ route('logout') }}" class="d-none">@csrf</form>

<script>
(function () {
    var LIFETIME   = {{ config('session.lifetime') * 60 }};  // seconds
    var WARN_SECS  = 120;   // show warning 2 minutes before
    var PING_URL   = '{{ route("session.ping") }}';
    var LOGOUT_URL = '{{ route("logout") }}';
    var CSRF       = '{{ csrf_token() }}';

    var remaining  = LIFETIME;
    var warnShown  = false;
    var modal      = null;
    var ticker     = null;

    function pad(n) { return String(n).padStart(2, '0'); }

    function updateDisplay() {
        var r = Math.max(0, remaining);
        var m = Math.floor(r / 60);
        var s = r % 60;
        var el = document.getElementById('sessionCountdown');
        if (el) el.textContent = pad(m) + ':' + pad(s);
    }

    function showWarning() {
        if (!warnShown) {
            warnShown = true;
            if (!modal) modal = new bootstrap.Modal(document.getElementById('sessionWarningModal'));
            modal.show();
        }
        updateDisplay();
    }

    function hideWarning() {
        warnShown = false;
        if (modal) modal.hide();
    }

    function forceLogout() {
        clearInterval(ticker);
        var f = document.getElementById('logoutForm');
        if (f) f.submit();
        else   window.location.href = LOGOUT_URL;
    }

    function ping() {
        fetch(PING_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' }
        }).catch(function() {});
        remaining = LIFETIME;
        hideWarning();
    }

    // Countdown ticker every second
    ticker = setInterval(function () {
        remaining--;
        if (remaining <= WARN_SECS) { showWarning(); }
        if (remaining <= 0)         { forceLogout(); }
    }, 1000);

    // Keep-alive button — resets timer + pings server
    document.getElementById('btnKeepAlive').addEventListener('click', ping);

    // Any user activity resets idle timer (throttled to once / 30 seconds)
    var lastActivity = Date.now();
    ['click', 'keydown', 'touchstart', 'scroll'].forEach(function (evt) {
        document.addEventListener(evt, function () {
            var now = Date.now();
            if (now - lastActivity > 30000) {  // only ping at most once / 30s
                lastActivity = now;
                ping();
            } else {
                remaining = LIFETIME;  // reset locally without network
                if (warnShown) hideWarning();
            }
        }, { passive: true });
    });
})();
</script>
@endauth

<script>
    // Sidebar section opener — uses URL path so it works even when
    // server-side routeIs() or Spatie permission checks miss the route.
    (function () {
        var p = window.location.pathname;
        var map = [
            { id: 'ns-sales',  paths: ['/sales', '/sales-quotations', '/sales-orders', '/sale-returns', '/customers', '/pos/shifts'] },
            { id: 'ns-purch',  paths: ['/purchases', '/purchase-orders', '/purchase-returns', '/suppliers', '/expense-invoices', '/customs-declarations'] },
            { id: 'ns-inv',    paths: ['/products', '/categories', '/inventory', '/stock-transfers', '/assemblies'] },
            { id: 'ns-treas',  paths: ['/vouchers'] },
            { id: 'ns-acc',    paths: ['/accounting', '/accounts', '/journal-entries', '/reversals', '/fixed-assets', '/fixed-asset-categories'] },
            { id: 'ns-hr',     paths: ['/hr'] },
            { id: 'ns-rep',    paths: ['/reports', '/res'] },
            { id: 'ns-set',    paths: ['/users', '/roles', '/permissions', '/settings', '/branches', '/warehouses', '/pos-terminals', '/price-lists', '/purchase-price-lists', '/currencies', '/audit-logs'] },
        ];

        for (var i = 0; i < map.length; i++) {
            var hit = map[i].paths.some(function (prefix) {
                return p === prefix || p.startsWith(prefix + '/') || p.startsWith(prefix + '?');
            });
            if (!hit) continue;

            var el = document.getElementById(map[i].id);
            if (el && !el.classList.contains('show')) {
                el.classList.add('show');
            }
            var btn = document.querySelector('[data-bs-target="#' + map[i].id + '"]');
            if (btn) {
                btn.classList.remove('collapsed');
                btn.setAttribute('aria-expanded', 'true');
            }
            break;
        }
    })();
</script>
</body>
</html>
