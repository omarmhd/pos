{{-- resources/views/dashboard.blade.php --}}
@extends('layouts.app')

@section('page-title', 'لوحة التحكم')

@section('content')
    <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2">مبيعات اليوم</h6>
                            <h3 class="mb-0">{{ number_format($todaySales, 2) }} ج.م</h3>
                        </div>
                        <div class="fs-1">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2">مبيعات الشهر</h6>
                            <h3 class="mb-0">{{ number_format($monthSales, 2) }} ج.م</h3>
                        </div>
                        <div class="fs-1">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2">إجمالي المنتجات</h6>
                            <h3 class="mb-0">{{ $totalProducts }}</h3>
                        </div>
                        <div class="fs-1">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2">منتجات أوشكت على النفاذ</h6>
                            <h3 class="mb-0">{{ $lowStockProducts }}</h3>
                        </div>
                        <div class="fs-1">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Recent Sales -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-receipt"></i> آخر المبيعات</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>رقم الفاتورة</th>
                                    <th>الكاشير</th>
                                    <th>المبلغ</th>
                                    <th>طريقة الدفع</th>
                                    <th>الوقت</th>
                                    <th>إجراءات</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($recentSales as $sale)
                                    <tr>
                                        <td><strong>{{ $sale->invoice_number }}</strong></td>
                                        <td>{{ $sale->user->name }}</td>
                                        <td><strong>{{ number_format($sale->total_amount, 2) }} ج.م</strong></td>
                                        <td>
                                            @if($sale->payment_method == 'cash')
                                                <span class="badge bg-success">نقدي</span>
                                            @elseif($sale->payment_method == 'card')
                                                <span class="badge bg-primary">بطاقة</span>
                                            @else
                                                <span class="badge bg-info">محفظة إلكترونية</span>
                                            @endif
                                        </td>
                                        <td>{{ $sale->created_at->format('H:i') }}</td>
                                        <td>
                                            <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-primary btn-action">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                            لا توجد مبيعات اليوم
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Products -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-star"></i> أكثر المنتجات مبيعاً</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @forelse($topProducts as $product)
                                <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                    <div>
                                        <strong>{{ $product->name }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $product->sale_items_count }} عملية بيع</small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">{{ $product->quantity }}</span>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    لا توجد بيانات
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        @if($lowStockProducts > 0 || $expiringProducts->count() > 0)
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> تنبيهات مهمة</h5>
                        </div>
                        <div class="card-body">
                            @if($lowStockProducts > 0)
                                <div class="alert alert-warning d-flex align-items-center" role="alert">
                                    <i class="bi bi-exclamation-triangle fs-4 me-3"></i>
                                    <div>
                                        <strong>تحذير!</strong> يوجد {{ $lowStockProducts }} منتج أوشك على النفاذ.
                                        <a href="{{ route('products.low-stock') }}" class="alert-link">عرض المنتجات</a>
                                    </div>
                                </div>
                            @endif

                            @if($expiringProducts->count() > 0)
                                <div class="alert alert-danger d-flex align-items-center" role="alert">
                                    <i class="bi bi-calendar-x fs-4 me-3"></i>
                                    <div>
                                        <strong>تحذير!</strong> يوجد {{ $expiringProducts->count() }} منتج تقترب صلاحيته من الانتهاء.
                                        <a href="{{ route('products.expiring') }}" class="alert-link">عرض المنتجات</a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
