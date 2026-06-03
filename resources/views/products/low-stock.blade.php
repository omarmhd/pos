@extends('layouts.app')
@section('page-title', 'تنبيهات المخزون')

@section('content')
<div class="card border-warning">
    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>تنبيهات المخزون — دون الحد الأدنى
            </h5>
            <small class="opacity-75">
                يُحسَّب لكل مخزن على حدة (stock_levels per warehouse) — وليس الإجمالي الكلي
            </small>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-dark btn-sm">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>

    {{-- Branch filter --}}
    @if(isset($branches) && $branches->count() >= 1)
    <div class="card-body border-bottom py-2">
        <form method="GET" class="row g-2 align-items-end">
            @include('components.branch-filter')
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-funnel"></i> تصفية
                </button>
            </div>
        </form>
    </div>
    @endif

    <div class="card-body">
        @if($lowLevels->count() > 0)
            <div class="alert alert-warning mb-3">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong>{{ $lowLevels->total() }}</strong> صنف في مخازن مختلفة دون الحد الأدنى ويحتاج لإعادة تعبئة.
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                    <tr>
                        <th>الصنف</th>
                        <th>المخزن</th>
                        <th>الفرع</th>
                        <th class="text-center">الكمية الحالية</th>
                        <th class="text-center">الحد الأدنى</th>
                        <th class="text-center">الفجوة</th>
                        <th class="text-center">الحالة</th>
                        <th class="text-center">إجراءات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($lowLevels as $level)
                    @php
                        $product = $level->product;
                        $gap     = (float)$level->min_quantity - (float)$level->quantity;
                    @endphp
                    <tr class="{{ (float)$level->quantity == 0 ? 'table-danger' : 'table-warning' }}">
                        <td>
                            <strong>{{ $product->name }}</strong>
                            <br>
                            <small class="text-muted font-monospace">{{ $product->barcode ?? '—' }}</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <i class="bi bi-archive me-1"></i>{{ $level->warehouse?->name ?? '—' }}
                            </span>
                        </td>
                        <td class="small text-muted">
                            {{ $level->warehouse?->branch?->name ?? '—' }}
                        </td>
                        <td class="text-center">
                            <span class="badge {{ (float)$level->quantity == 0 ? 'bg-danger' : 'bg-warning text-dark' }} fs-6">
                                {{ number_format($level->quantity, 0) + 0 }}
                            </span>
                        </td>
                        <td class="text-center text-muted">
                            {{ number_format($level->min_quantity, 0) + 0 }}
                        </td>
                        <td class="text-center">
                            <span class="text-danger fw-bold">-{{ number_format($gap, 0) + 0 }}</span>
                        </td>
                        <td class="text-center">
                            @if((float)$level->quantity == 0)
                                <span class="badge bg-danger">نفذ بالكامل</span>
                            @elseif($gap > $level->min_quantity * 0.5)
                                <span class="badge bg-danger">خطير جداً</span>
                            @else
                                <span class="badge bg-warning text-dark">منخفض</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route('products.show', $product) }}" class="btn btn-sm btn-info btn-action" title="عرض">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('purchases.create') }}?product={{ $product->id }}" class="btn btn-sm btn-success btn-action" title="إنشاء أمر شراء">
                                <i class="bi bi-cart-plus"></i>
                            </a>
                            <a href="{{ route('stock-transfers.create') }}" class="btn btn-sm btn-warning btn-action" title="تحويل من مخزن آخر">
                                <i class="bi bi-arrow-left-right"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{ $lowLevels->links() }}

        @else
            <div class="text-center py-5">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                <h4 class="mt-3 text-success">ممتاز!</h4>
                <p class="text-muted">
                    جميع المخازن فوق الحد الأدنى
                    @if(isset($branchId) && $branchId)
                        <span class="badge bg-primary ms-1">{{ \App\Models\Branch::find($branchId)?->name }}</span>
                    @endif
                </p>
                <a href="{{ route('products.index') }}" class="btn btn-primary mt-2">
                    <i class="bi bi-box-seam"></i> الأصناف
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
