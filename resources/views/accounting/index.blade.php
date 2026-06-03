@extends('layouts.app')
@section('page-title', 'لوحة الحسابات')

@section('content')
<div class="container-fluid">

    {{-- Date + Branch Filter --}}
    <form class="card mb-4" method="GET" action="{{ route('accounting.index') }}">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                @include('components.branch-filter')
                <div class="col-md-2">
                    <label class="form-label small mb-1">من تاريخ</label>
                    <input type="date" name="date_from" value="{{ $dateFrom->format('Y-m-d') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ $dateTo->format('Y-m-d') }}" class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-sm" type="submit">
                        <i class="bi bi-funnel me-1"></i> تحديث
                    </button>
                    @if(request()->hasAny(['branch_id','date_from','date_to']))
                        <a href="{{ route('accounting.index') }}" class="btn btn-outline-secondary btn-sm ms-1">
                            <i class="bi bi-x"></i>
                        </a>
                    @endif
                </div>
                @if($branchId ?? false)
                <div class="col-auto">
                    <span class="badge bg-primary">
                        <i class="bi bi-building me-1"></i>
                        {{ ($branches ?? collect())->firstWhere('id', $branchId)?->name ?? '' }}
                    </span>
                </div>
                @endif
            </div>
        </div>
    </form>

    {{-- KPI Cards --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-2">إجمالي المبيعات</h6>
                        <h3 class="mb-0">{{ number_format($salesRevenue, 2) }}</h3>
                        <small class="opacity-75">{{ $salesCount }} فاتورة</small>
                    </div>
                    <div class="fs-1"><i class="bi bi-receipt"></i></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-2">مجمل الربح</h6>
                        <h3 class="mb-0">{{ number_format($grossProfit, 2) }}</h3>
                        <small class="opacity-75">هامش {{ number_format($grossMargin, 1) }}%</small>
                    </div>
                    <div class="fs-1"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-2">قيمة المخزون</h6>
                        <h3 class="mb-0">{{ number_format($inventoryValue, 2) }}</h3>
                        <small class="opacity-75">
                            @if($lowStockCount > 0)
                                <i class="bi bi-exclamation-triangle-fill"></i> {{ $lowStockCount }} منتج منخفض
                            @else
                                المخزون طبيعي
                            @endif
                        </small>
                    </div>
                    <div class="fs-1"><i class="bi bi-box-seam"></i></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-2">مستحقات الموردين</h6>
                        <h3 class="mb-0">{{ number_format($purchaseOutstanding, 2) }}</h3>
                        <small class="opacity-75">من {{ number_format($purchaseValue, 2) }} مشتريات</small>
                    </div>
                    <div class="fs-1"><i class="bi bi-truck"></i></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Second Row: Payment breakdown + Operational summary + Purchases --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-1"></i> توزيع طرق الدفع</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1">
                        <span>نقدي</span>
                        <strong>{{ number_format($paymentBreakdown['cash'], 2) }}</strong>
                    </div>
                    <div class="progress mb-3" style="height:8px">
                        <div class="progress-bar bg-success" style="width:{{ $cashShare }}%"></div>
                    </div>

                    <div class="d-flex justify-content-between mb-1">
                        <span>بطاقة</span>
                        <strong>{{ number_format($paymentBreakdown['card'], 2) }}</strong>
                    </div>
                    <div class="progress mb-3" style="height:8px">
                        <div class="progress-bar bg-primary" style="width:{{ $cardShare }}%"></div>
                    </div>

                    <div class="d-flex justify-content-between mb-1">
                        <span>محفظة إلكترونية</span>
                        <strong>{{ number_format($paymentBreakdown['mobile_wallet'], 2) }}</strong>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-info" style="width:{{ $walletShare }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-1"></i> قراءة تشغيلية</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">عدد الفواتير</td>
                                <td class="text-end fw-semibold">{{ $salesCount }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">تكلفة البضاعة المباعة</td>
                                <td class="text-end fw-semibold">{{ number_format($costOfGoods, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">هامش الربح الإجمالي</td>
                                <td class="text-end fw-semibold">{{ number_format($grossMargin, 1) }}%</td>
                            </tr>
                            <tr>
                                <td class="text-muted">منتجات منخفضة المخزون</td>
                                <td class="text-end fw-semibold {{ $lowStockCount > 0 ? 'text-danger' : '' }}">
                                    {{ $lowStockCount }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">قرب انتهاء الصلاحية (30 يوم)</td>
                                <td class="text-end fw-semibold {{ $expiringSoonCount > 0 ? 'text-warning' : '' }}">
                                    {{ $expiringSoonCount }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-credit-card me-1"></i> المشتريات والالتزامات</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">إجمالي المشتريات</td>
                                <td class="text-end fw-semibold">{{ number_format($purchaseValue, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">المدفوع للموردين</td>
                                <td class="text-end fw-semibold text-success">{{ number_format($purchasePaid, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">المستحق غير المسدد</td>
                                <td class="text-end fw-semibold text-danger">{{ number_format($purchaseOutstanding, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">صافي المبيعات − المشتريات</td>
                                <td class="text-end fw-semibold">{{ number_format($salesRevenue - $purchaseValue, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Third Row: Daily sales table + Supplier balances + Recent activity --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-calendar3 me-1"></i> الحركة اليومية للمبيعات</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>التاريخ</th>
                                    <th class="text-center">عدد الفواتير</th>
                                    <th class="text-end">إجمالي المبيعات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dailySales as $day)
                                <tr>
                                    <td>{{ $day->date }}</td>
                                    <td class="text-center">{{ $day->invoices_count }}</td>
                                    <td class="text-end font-monospace">{{ number_format($day->sales_total, 2) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">لا توجد مبيعات في الفترة المحددة</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-building me-1"></i> أعلى مستحقات الموردين</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>المورد</th>
                                <th class="text-end">المستحق</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($supplierBalances as $balance)
                            <tr>
                                <td>{{ optional($balance->supplier)->name ?? '—' }}</td>
                                <td class="text-end font-monospace text-danger">
                                    {{ number_format($balance->balance, 2) }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted py-3">لا توجد مستحقات حالية</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-1"></i> آخر الحركات</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>النوع</th>
                                <th>رقم الفاتورة</th>
                                <th class="text-end">المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentSales->take(3) as $sale)
                            <tr>
                                <td><span class="badge bg-success">بيع</span></td>
                                <td>{{ $sale->invoice_number }}</td>
                                <td class="text-end font-monospace">{{ number_format($sale->total_amount, 2) }}</td>
                            </tr>
                            @endforeach
                            @foreach($recentPurchases->take(3) as $purchase)
                            <tr>
                                <td><span class="badge bg-warning text-dark">شراء</span></td>
                                <td>{{ $purchase->invoice_number }}</td>
                                <td class="text-end font-monospace">{{ number_format($purchase->total_amount, 2) }}</td>
                            </tr>
                            @endforeach
                            @if($recentSales->isEmpty() && $recentPurchases->isEmpty())
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">لا توجد حركات</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Fourth Row: Account chart + Recent journal entries --}}
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-columns-reverse me-1"></i> دليل الحسابات</h5>
                    <span class="badge bg-secondary">{{ $accounts->count() }} حساب</span>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        @foreach(['asset'=>['الأصول','primary'],'liability'=>['الالتزامات','warning'],'equity'=>['حقوق الملكية','info'],'revenue'=>['الإيرادات','success'],'expense'=>['المصروفات','danger']] as $type => [$label,$color])
                        <div class="col-6 col-md-4">
                            <div class="card border text-center py-2">
                                <div class="small text-muted">{{ $label }}</div>
                                <div class="fw-bold fs-5 text-{{ $color }}">{{ $accountSummary[$type] ?? 0 }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>الكود</th>
                                    <th>اسم الحساب</th>
                                    <th>النوع</th>
                                    <th class="text-center">الحركات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($accounts->take(15) as $account)
                                <tr>
                                    <td><code>{{ $account->code }}</code></td>
                                    <td>{{ $account->name }}</td>
                                    <td>
                                        @php $colors = ['asset'=>'primary','liability'=>'warning','equity'=>'info','revenue'=>'success','expense'=>'danger']; @endphp
                                        <span class="badge bg-{{ $colors[$account->type] ?? 'secondary' }}">
                                            {{ ['asset'=>'أصل','liability'=>'التزام','equity'=>'حقوق ملكية','revenue'=>'إيراد','expense'=>'مصروف'][$account->type] ?? $account->type }}
                                        </span>
                                    </td>
                                    <td class="text-center">{{ $account->journal_entry_lines_count }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">لا توجد حسابات مسجلة</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-journal-text me-1"></i> آخر القيود اليومية</h5>
                    @can('journal_entries.view')
                    <a href="{{ route('journal_entries.index') }}" class="btn btn-outline-secondary btn-sm">
                        عرض الكل
                    </a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>رقم القيد</th>
                                <th>التاريخ</th>
                                <th class="text-end">المدين</th>
                                <th class="text-center">متوازن</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($journalEntries as $entry)
                            <tr>
                                <td>
                                    @can('journal_entries.view')
                                    <a href="{{ route('journal_entries.show', $entry->id) }}"
                                       class="text-decoration-none font-monospace">
                                        {{ $entry->entry_number }}
                                    </a>
                                    @else
                                    <span class="font-monospace">{{ $entry->entry_number }}</span>
                                    @endcan
                                </td>
                                <td class="text-muted small">{{ $entry->entry_date?->format('Y-m-d') }}</td>
                                <td class="text-end font-monospace small">
                                    {{ number_format($entry->debit_total, 2) }}
                                </td>
                                <td class="text-center">
                                    @if($entry->is_balanced)
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    @else
                                        <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">لم يتم تسجيل قيود بعد</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
