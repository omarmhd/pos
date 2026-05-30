@extends('layouts.app')
@section('page-title', 'المنتجات الأكثر مبيعاً')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-trophy text-warning"></i> المنتجات الأكثر مبيعاً</h5>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary no-print">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>
    <div class="card-body">

        <form method="GET" class="row g-3 mb-4 no-print">
            <div class="col-md-4">
                <label class="form-label fw-semibold small">من تاريخ</label>
                <input type="date" name="date_from" class="form-control"
                       value="{{ request('date_from', \Carbon\Carbon::parse($dateFrom)->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">إلى تاريخ</label>
                <input type="date" name="date_to" class="form-control"
                       value="{{ request('date_to', \Carbon\Carbon::parse($dateTo)->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> عرض التقرير
                </button>
            </div>
        </form>

        @if($products->count())
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card bg-warning bg-opacity-10 border-warning h-100">
                    <div class="card-body">
                        <h6 class="fw-bold"><i class="bi bi-trophy-fill text-warning"></i> المنتج الأكثر مبيعاً</h6>
                        <h5 class="mb-1">{{ $products->first()->name }}</h5>
                        <p class="mb-0 small text-muted">
                            <strong>{{ $products->first()->total_quantity }}</strong> وحدة —
                            إيرادات <strong>{{ number_format($products->first()->total_revenue, 2) }} {{ $cur }}</strong>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card primary">
                    <h6>إجمالي الكميات</h6>
                    <h3>{{ $products->sum('total_quantity') }}</h3>
                    <small>وحدة مباعة</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <h6>إجمالي الإيرادات</h6>
                    <h3>{{ number_format($products->sum('total_revenue'), 2) }}</h3>
                    <small>{{ $cur }}</small>
                </div>
            </div>
        </div>
        @endif

        <div class="d-none d-print-block mb-3 text-center border-bottom pb-2">
            <strong>أكثر المنتجات مبيعاً</strong> —
            من {{ \Carbon\Carbon::parse($dateFrom)->format('Y-m-d') }}
            إلى {{ \Carbon\Carbon::parse($dateTo)->format('Y-m-d') }}
        </div>

        <div class="table-responsive">
            <table class="table table-hover dt-table" data-title="أكثر المنتجات مبيعاً">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">#</th>
                        <th>المنتج</th>
                        <th>الفئة</th>
                        <th class="text-center">الكمية المباعة</th>
                        <th class="text-center">عدد المرات</th>
                        <th class="text-end">الإيرادات ({{ $cur }})</th>
                        <th class="text-end">متوسط السعر ({{ $cur }})</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($products as $index => $product)
                    <tr>
                        <td class="text-center">
                            @if($index === 0)
                                <span class="badge bg-warning text-dark fs-6">🥇 1</span>
                            @elseif($index === 1)
                                <span class="badge bg-secondary fs-6">🥈 2</span>
                            @elseif($index === 2)
                                <span class="badge" style="background:#cd7f32;color:#fff;font-size:.85rem">🥉 3</span>
                            @else
                                <span class="text-muted">{{ $index + 1 }}</span>
                            @endif
                        </td>
                        <td><strong>{{ $product->name }}</strong></td>
                        <td><span class="badge bg-info">{{ $product->category->name }}</span></td>
                        <td class="text-center"><span class="badge bg-primary">{{ $product->total_quantity }}</span></td>
                        <td class="text-center">{{ $product->times_sold }} مرة</td>
                        <td class="text-end fw-bold">{{ number_format($product->total_revenue, 2) }}</td>
                        <td class="text-end text-muted">
                            {{ number_format($product->total_revenue / max($product->total_quantity, 1), 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">لا توجد مبيعات في هذه الفترة</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>
@endsection
