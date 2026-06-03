@extends('layouts.app')
@section('page-title', 'تقرير المخزون')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-box-seam text-primary"></i> تقرير المخزون</h5>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary no-print">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>
    <div class="card-body">

        {{-- Filters --}}
        <form method="GET" class="row g-2 mb-4 no-print align-items-end">
            @include('components.branch-filter')
            @if(isset($warehouses) && $warehouses->count() > 1)
            <div class="col-auto">
                <label class="form-label small mb-1"><i class="bi bi-archive me-1"></i>المخزن</label>
                <select name="warehouse_id" class="form-select form-select-sm" style="min-width:150px">
                    <option value="">كل المخازن</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ ($warehouseId ?? null) == $wh->id ? 'selected':'' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search"></i> عرض
                </button>
                @if(request()->hasAny(['branch_id','warehouse_id']))
                    <a href="{{ route('reports.inventory') }}" class="btn btn-outline-danger btn-sm ms-1">
                        <i class="bi bi-x"></i>
                    </a>
                @endif
            </div>
        </form>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card primary">
                    <h6>قيمة المخزون الإجمالية</h6>
                    <h3>{{ number_format($totalValue, 2) }} {{ $cur }}</h3>
                    <small>سعر التكلفة</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card success">
                    <h6>الربح المحتمل</h6>
                    <h3>{{ number_format($totalPotentialProfit, 2) }} {{ $cur }}</h3>
                    <small>عند بيع كل المخزون</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card warning">
                    <h6>منتجات تحت الحد الأدنى</h6>
                    <h3>{{ $lowStockCount }}</h3>
                    <small>تحتاج إعادة طلب</small>
                </div>
            </div>
        </div>

        <div class="d-none d-print-block mb-3 text-center border-bottom pb-2">
            <strong>تقرير المخزون</strong> — {{ now()->format('Y-m-d') }}
        </div>

        <div class="table-responsive">
            <table class="table table-hover dt-table" data-title="تقرير المخزون">
                <thead class="table-light">
                    <tr>
                        <th>المنتج</th>
                        <th>الفئة</th>
                        <th class="text-center">الكمية</th>
                        <th class="text-end">سعر التكلفة ({{ $cur }})</th>
                        <th class="text-end">سعر البيع ({{ $cur }})</th>
                        <th class="text-end">قيمة المخزون ({{ $cur }})</th>
                        <th class="text-end">الربح المحتمل ({{ $cur }})</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($products as $product)
                    <tr class="{{ $product->quantity <= $product->min_quantity ? 'table-warning' : '' }}">
                        <td>
                            <strong>{{ $product->name }}</strong>
                            @if($product->quantity <= $product->min_quantity)
                                <span class="badge bg-danger ms-1">مخزون منخفض</span>
                            @endif
                        </td>
                        <td><span class="badge bg-info">{{ $product->category->name }}</span></td>
                        <td class="text-center">
                            @if($product->quantity <= $product->min_quantity)
                                <span class="badge bg-danger">{{ $product->quantity }}</span>
                            @else
                                {{ $product->quantity }}
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($product->cost_price, 2) }}</td>
                        <td class="text-end">{{ number_format($product->selling_price, 2) }}</td>
                        <td class="text-end fw-bold">{{ number_format($product->inventory_value, 2) }}</td>
                        <td class="text-end text-success">{{ number_format($product->potential_profit, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">لا توجد منتجات</td>
                    </tr>
                @endforelse
                </tbody>
                @if($products->count())
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="5">الإجمالي</td>
                        <td class="text-end">{{ number_format($totalValue, 2) }}</td>
                        <td class="text-end text-success">{{ number_format($totalPotentialProfit, 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

    </div>
</div>
@endsection
