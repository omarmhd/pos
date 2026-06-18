@extends('layouts.app')
@section('page-title', 'لوحة التحكم')

@push('styles')
<style>
/* ── Launcher greeting ── */
.launcher-greeting {
    background: linear-gradient(135deg, #1a2535 0%, #243447 100%);
    border-radius: 14px;
    padding: 24px 28px;
    color: #fff;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.launcher-greeting::after {
    content: '';
    position: absolute;
    left: -40px; top: -40px;
    width: 200px; height: 200px;
    background: rgba(52,152,219,.12);
    border-radius: 50%;
}
.launcher-greeting .greet-name { font-size: 1.3rem; font-weight: 700; margin-bottom: 2px; }
.launcher-greeting .greet-sub  { font-size: .82rem; color: rgba(255,255,255,.5); }
.launcher-greeting .greet-shift {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(46,204,113,.2); color: #2ecc71;
    border: 1px solid rgba(46,204,113,.3);
    border-radius: 20px; font-size: .75rem; padding: 3px 10px; margin-top: 8px;
}

/* ── KPI cards ── */
.kpi-card {
    background: #fff;
    border-radius: 12px;
    padding: 18px 20px;
    border: 1px solid #e9ecef;
    height: 100%;
    transition: box-shadow .18s;
}
.kpi-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.08); }
.kpi-card .kpi-label { font-size: .75rem; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px; }
.kpi-card .kpi-value { font-size: 1.6rem; font-weight: 800; color: #1a2535; line-height: 1.1; }
.kpi-card .kpi-delta { font-size: .75rem; margin-top: 4px; }
.kpi-card .kpi-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
    float: left; margin-right: 0; margin-left: 12px;
}
.kpi-up   { color: #27ae60; } .kpi-down { color: #e74c3c; } .kpi-neutral { color: #6c757d; }

/* ── Quick tiles ── */
.tiles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; }
.q-tile {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 18px 12px 14px;
    text-align: center;
    cursor: pointer;
    transition: all .18s;
    text-decoration: none; color: inherit;
    display: block;
}
.q-tile:hover { border-color: #3498db; box-shadow: 0 4px 16px rgba(52,152,219,.15); transform: translateY(-2px); color: inherit; text-decoration: none; }
.q-tile-icon {
    width: 46px; height: 46px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; margin: 0 auto 10px;
}
.q-tile-name  { font-size: .78rem; font-weight: 700; color: #1a2535; }
.q-tile-badge {
    font-size: .65rem; padding: 1px 6px; border-radius: 10px;
    margin-top: 4px; display: inline-block;
}

/* ── Quick action buttons ── */
.qa-btn {
    display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1px solid #e9ecef;
    border-radius: 10px; padding: 10px 14px;
    font-size: .82rem; color: #495057; cursor: pointer;
    text-decoration: none; transition: all .15s;
}
.qa-btn:hover { border-color: #3498db; color: #2980b9; background: #f0f7ff; text-decoration: none; }
.qa-btn i { font-size: 1rem; }

/* ── Section title ── */
.dash-section-title {
    font-size: .72rem; font-weight: 700; color: #adb5bd;
    text-transform: uppercase; letter-spacing: .5px;
    margin: 20px 0 10px;
}

/* ── Recent sales table ── */
.recent-table th { font-size: .75rem; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: .3px; border-top: none; }
.recent-table td { vertical-align: middle; font-size: .85rem; }

/* ── Alert chip ── */
.alert-chip {
    display: flex; align-items: center; gap: 8px;
    background: #fff8e1; border: 1px solid #ffe082;
    border-radius: 8px; padding: 10px 14px; font-size: .83rem;
    margin-bottom: 8px;
}
.alert-chip.danger { background: #fff3f3; border-color: #ffcdd2; }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- ── Greeting Banner ── --}}
    <div class="launcher-greeting">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <div class="greet-name">مرحباً، {{ auth()->user()->name }} 👋</div>
                <div class="greet-sub">
                    {{ now()->translatedFormat('l، j F Y') }}
                    @if(auth()->user()->branch)
                         — {{ auth()->user()->branch->name }}
                    @endif
                </div>
                @if($openShift)
                <div class="greet-shift">
                    <i class="bi bi-circle-fill" style="font-size:.5rem"></i>
                    وردية مفتوحة منذ {{ $openShift->opened_at->format('H:i') }}
                </div>
                @endif
            </div>
            <div class="d-flex gap-2 flex-wrap" style="position:relative;z-index:1">
                @can('sales.create')
                <a href="{{ route('pos.index') }}" class="btn btn-success btn-sm fw-bold px-3">
                    <i class="bi bi-cart-check-fill me-1"></i> فتح الكاشير
                </a>
                @endcan
                @can('journal_entries.create')
                <a href="{{ route('journal_entries.create') }}" class="btn btn-outline-light btn-sm px-3">
                    <i class="bi bi-plus-circle me-1"></i> قيد جديد
                </a>
                @endcan
            </div>
        </div>
    </div>

    {{-- ── KPI Row ── --}}
    <div class="row g-3 mb-2">
        @can('sales.view')
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#e8f8f0; color:#27ae60; float:left; margin-right:0; margin-left:0; margin-bottom:8px">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div class="kpi-label">مبيعات اليوم</div>
                <div class="kpi-value">{{ number_format($todaySales, 0) }}</div>
                <div class="kpi-delta">
                    @php $diff = $yesterdaySales > 0 ? round((($todaySales - $yesterdaySales)/$yesterdaySales)*100) : 0; @endphp
                    @if($diff > 0) <span class="kpi-up"><i class="bi bi-arrow-up-short"></i>{{ $diff }}% عن أمس</span>
                    @elseif($diff < 0) <span class="kpi-down"><i class="bi bi-arrow-down-short"></i>{{ abs($diff) }}% عن أمس</span>
                    @else <span class="kpi-neutral">مساوٍ لأمس</span> @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">مبيعات الشهر</div>
                <div class="kpi-value">{{ number_format($monthSales, 0) }}</div>
                <div class="kpi-delta kpi-neutral">{{ now()->format('F Y') }}</div>
            </div>
        </div>
        @endcan
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">فواتير اليوم</div>
                <div class="kpi-value">{{ $todayInvoices }}</div>
                <div class="kpi-delta kpi-neutral">عملية بيع</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">
                    @if($lowStockProducts > 0)
                        <span class="text-warning"><i class="bi bi-exclamation-triangle"></i></span>
                    @endif
                    تنبيهات المخزون
                </div>
                <div class="kpi-value {{ $lowStockProducts > 0 ? 'text-warning' : '' }}">{{ $lowStockProducts }}</div>
                <div class="kpi-delta kpi-neutral">صنف أوشك على النفاذ</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- ── Left Column: Tiles + Quick Actions ── --}}
        <div class="col-lg-4">

            {{-- Quick Access Tiles --}}
            <div class="dash-section-title">وصول سريع</div>
            <div class="tiles-grid">
                @can('sales.create')
                <a href="{{ route('pos.index') }}" class="q-tile">
                    <div class="q-tile-icon" style="background:#e8f8f0;color:#27ae60"><i class="bi bi-cart-check-fill"></i></div>
                    <div class="q-tile-name">الكاشير</div>
                    @if($openShift)<div class="q-tile-badge" style="background:#d4edda;color:#155724">مفتوحة</div>@endif
                </a>
                @endcan
                @can('sales.view')
                <a href="{{ route('sales.index') }}" class="q-tile">
                    <div class="q-tile-icon" style="background:#e3f2fd;color:#1565c0"><i class="bi bi-receipt"></i></div>
                    <div class="q-tile-name">فواتير البيع</div>
                    @if($todayInvoices > 0)<div class="q-tile-badge" style="background:#cfe2ff;color:#084298">{{ $todayInvoices }} اليوم</div>@endif
                </a>
                @endcan
                @can('purchases.view')
                <a href="{{ route('purchases.index') }}" class="q-tile">
                    <div class="q-tile-icon" style="background:#fff3e0;color:#e65100"><i class="bi bi-bag-plus"></i></div>
                    <div class="q-tile-name">فواتير الشراء</div>
                </a>
                @endcan
                @can('products.view')
                <a href="{{ route('products.index') }}" class="q-tile">
                    <div class="q-tile-icon" style="background:#f3e5f5;color:#6a1b9a"><i class="bi bi-box-seam"></i></div>
                    <div class="q-tile-name">المنتجات</div>
                    <div class="q-tile-badge" style="background:#e1bee7;color:#4a148c">{{ $totalProducts }}</div>
                </a>
                @endcan
                @can('customers.view')
                <a href="{{ route('customers.index') }}" class="q-tile">
                    <div class="q-tile-icon" style="background:#e0f7fa;color:#00695c"><i class="bi bi-person-lines-fill"></i></div>
                    <div class="q-tile-name">العملاء</div>
                </a>
                @endcan
                @can('suppliers.view')
                <a href="{{ route('suppliers.index') }}" class="q-tile">
                    <div class="q-tile-icon" style="background:#fce4ec;color:#880e4f"><i class="bi bi-building-fill"></i></div>
                    <div class="q-tile-name">الموردون</div>
                </a>
                @endcan
                @can('journal_entries.view')
                <a href="{{ route('journal_entries.index') }}" class="q-tile">
                    <div class="q-tile-icon" style="background:#e8eaf6;color:#283593"><i class="bi bi-journal-text"></i></div>
                    <div class="q-tile-name">القيود</div>
                </a>
                @endcan
                @can('financial_statements.view')
                <a href="{{ route('accounting.income-statement') }}" class="q-tile">
                    <div class="q-tile-icon" style="background:#e0f2f1;color:#004d40"><i class="bi bi-bar-chart-line-fill"></i></div>
                    <div class="q-tile-name">قائمة الدخل</div>
                </a>
                @endcan
                @can('reports.view')
                <a href="{{ route('reports.index') }}" class="q-tile">
                    <div class="q-tile-icon" style="background:#fff8e1;color:#f57f17"><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="q-tile-name">التقارير</div>
                </a>
                @endcan
                @can('hr.view_payroll')
                <a href="{{ route('hr.payroll.index') }}" class="q-tile">
                    <div class="q-tile-icon" style="background:#fff3e0;color:#bf360c"><i class="bi bi-cash-stack"></i></div>
                    <div class="q-tile-name">الرواتب</div>
                </a>
                @endcan
                @can('inventory.view')
                <a href="{{ route('products.low-stock') }}" class="q-tile">
                    <div class="q-tile-icon" style="background:#fff8e1;color:#f9a825"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="q-tile-name">المخزون</div>
                    @if($lowStockProducts > 0)<div class="q-tile-badge" style="background:#fff3cd;color:#856404">{{ $lowStockProducts }} تنبيه</div>@endif
                </a>
                @endcan
                <a href="{{ route('help') }}" class="q-tile">
                    <div class="q-tile-icon" style="background:#f5f5f5;color:#616161"><i class="bi bi-question-circle"></i></div>
                    <div class="q-tile-name">الدليل</div>
                </a>
            </div>

            {{-- Quick Actions --}}
            <div class="dash-section-title">إجراءات سريعة</div>
            <div class="d-flex flex-column gap-2">
                @can('sales.create')
                <a href="{{ route('sales.create') }}" class="qa-btn"><i class="bi bi-plus-circle text-success"></i> فاتورة بيع جديدة</a>
                @endcan
                @can('purchases.create')
                <a href="{{ route('purchases.create') }}" class="qa-btn"><i class="bi bi-plus-circle text-warning"></i> فاتورة شراء جديدة</a>
                @endcan
                @can('journal_entries.create')
                <a href="{{ route('journal_entries.create') }}" class="qa-btn"><i class="bi bi-plus-circle text-primary"></i> قيد يومية جديد</a>
                @endcan
                @can('vouchers.view')
                <a href="{{ route('vouchers.receipts.create') }}" class="qa-btn"><i class="bi bi-arrow-down-circle-fill text-success"></i> سند قبض جديد</a>
                @endcan
                @can('reports.view')
                <a href="{{ route('reports.sales') }}" class="qa-btn"><i class="bi bi-graph-up-arrow text-info"></i> تقرير المبيعات</a>
                @endcan
            </div>

            {{-- Alerts --}}
            @if($lowStockProducts > 0 || $expiringProducts->count() > 0 || $pendingLeaves > 0)
            <div class="dash-section-title">تنبيهات</div>
            @if($lowStockProducts > 0)
            <a href="{{ route('products.low-stock') }}" class="alert-chip text-decoration-none text-reset">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                <span>{{ $lowStockProducts }} صنف أوشك على النفاذ</span>
                <i class="bi bi-chevron-left ms-auto text-muted small"></i>
            </a>
            @endif
            @if($expiringProducts->count() > 0)
            <a href="{{ route('products.expiring') }}" class="alert-chip danger text-decoration-none text-reset">
                <i class="bi bi-calendar-x-fill text-danger fs-5"></i>
                <span>{{ $expiringProducts->count() }} صنف قرب انتهاء صلاحيته</span>
                <i class="bi bi-chevron-left ms-auto text-muted small"></i>
            </a>
            @endif
            @if($pendingLeaves > 0)
            <a href="{{ route('hr.leaves.index') }}" class="alert-chip text-decoration-none text-reset">
                <i class="bi bi-calendar-check text-primary fs-5"></i>
                <span>{{ $pendingLeaves }} طلب إجازة بانتظار الموافقة</span>
                <i class="bi bi-chevron-left ms-auto text-muted small"></i>
            </a>
            @endif
            @endif
        </div>

        {{-- ── Right Column: Recent Sales + Top Products ── --}}
        <div class="col-lg-8">
            <div class="dash-section-title">آخر المبيعات</div>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover recent-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>رقم الفاتورة</th>
                                    <th>الكاشير</th>
                                    <th>المبلغ</th>
                                    <th>الدفع</th>
                                    <th>الوقت</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($recentSales as $sale)
                            <tr>
                                <td class="fw-bold font-monospace small">{{ $sale->invoice_number }}</td>
                                <td class="text-muted small">{{ $sale->user?->name ?? '—' }}</td>
                                <td><strong>{{ number_format($sale->total_amount, 2) }}</strong> <small class="text-muted">{{ $currency }}</small></td>
                                <td>
                                    @php $pm = $sale->payment_method; @endphp
                                    @if($pm=='cash') <span class="badge bg-success">نقدي</span>
                                    @elseif($pm=='card') <span class="badge bg-primary">بطاقة</span>
                                    @elseif($pm=='deposit_balance') <span class="badge bg-info">رصيد إيداع</span>
                                    @else <span class="badge bg-secondary">{{ $pm }}</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $sale->created_at->format('H:i') }}</td>
                                <td>
                                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>لا توجد مبيعات اليوم
                            </td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @can('sales.view')
                <div class="card-footer bg-white border-top-0 text-center py-2">
                    <a href="{{ route('sales.index') }}" class="text-primary small">عرض جميع الفواتير ←</a>
                </div>
                @endcan
            </div>

            {{-- Top Products --}}
            <div class="dash-section-title">أكثر الأصناف مبيعاً</div>
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    @forelse($topProducts as $i => $product)
                    <div class="d-flex align-items-center gap-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div style="width:24px;height:24px;border-radius:6px;background:{{ ['#e8f8f0','#e3f2fd','#fff3e0','#f3e5f5','#e8eaf6'][$i] ?? '#f5f5f5' }};display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#555;flex-shrink:0">
                            {{ $i + 1 }}
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small">{{ $product->name }}</div>
                            <div class="text-muted" style="font-size:.72rem">{{ $product->sale_items_count }} عملية بيع</div>
                        </div>
                        <div>
                            <div style="width:80px;height:5px;background:#f0f0f0;border-radius:3px;overflow:hidden">
                                @php $maxCount = $topProducts->max('sale_items_count'); @endphp
                                <div style="width:{{ $maxCount > 0 ? round(($product->sale_items_count / $maxCount)*100) : 0 }}%;height:100%;background:#3498db;border-radius:3px"></div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-4 text-muted small">لا توجد بيانات مبيعات بعد</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
