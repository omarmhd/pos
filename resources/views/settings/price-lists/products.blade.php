@extends('layouts.app')
@section('page-title', 'أسعار الأصناف — ' . $priceList->name)

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">
                <i class="bi bi-currency-dollar text-info me-2"></i>
                أسعار الأصناف في قائمة: <strong>{{ $priceList->name }}</strong>
                <span class="badge bg-{{ match($priceList->type) {
                    'retail'    => 'primary',
                    'wholesale' => 'warning text-dark',
                    default     => 'secondary',
                } }} ms-2">{{ $priceList->typeLabel() }}</span>
            </h5>
            <p class="text-muted small mb-0 mt-1">
                اتركه فارغاً لاستخدام سعر المنتج الأساسي — أدخل سعراً للتجاوز (Override).
            </p>
        </div>
        <a href="{{ route('price-lists.index') }}" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>
    <div class="card-body">
        @can('price_lists.manage')
        <form action="{{ route('price-lists.products', $priceList) }}" method="POST">
            @csrf @method('POST')
        @endcan

            <div class="mb-3 d-flex gap-2">
                <input type="text" id="productSearch" class="form-control" style="max-width:300px"
                       placeholder="بحث في الأصناف…">
                <span class="text-muted small d-flex align-items-center">
                    {{ $products->count() }} صنف
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-sm" id="pricesTable" style="min-width:700px;">
                    <thead class="table-light">
                    <tr>
                        <th>الصنف</th>
                        <th>الباركود</th>
                        <th class="text-end">سعر التجزئة الأساسي</th>
                        <th class="text-end" style="min-width:160px">
                            سعر قائمة <span class="text-info">{{ $priceList->name }}</span>
                        </th>
                        <th class="text-end" style="min-width:110px">الحد الأدنى للكمية</th>
                        <th class="text-center">الفرق</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($products as $product)
                    @php
                        $entry = $product->prices->first();
                        $overridePrice = $entry?->selling_price;
                        $diffPct = $overridePrice
                            ? round(($overridePrice / max(0.01, $product->selling_price) - 1) * 100, 1)
                            : null;
                    @endphp
                    <tr class="product-row" data-name="{{ strtolower($product->name) }}">
                        <td class="fw-semibold">{{ $product->name }}</td>
                        <td class="text-muted small font-monospace">{{ $product->barcode ?? '—' }}</td>
                        <td class="text-end text-muted">
                            {{ number_format($product->selling_price, 2) }} {{ $currency }}
                        </td>
                        <td class="text-end">
                            @can('price_lists.manage')
                            <input type="number"
                                   name="prices[{{ $product->id }}][price]"
                                   class="form-control form-control-sm text-end price-input"
                                   value="{{ $overridePrice !== null ? number_format((float)$overridePrice, 2, '.', '') : '' }}"
                                   step="0.01" min="0"
                                   placeholder="{{ number_format($product->selling_price, 2) }}"
                                   style="max-width:130px;margin-right:auto;">
                            @else
                            <span class="{{ $overridePrice !== null ? 'fw-bold text-info' : 'text-muted' }}">
                                {{ $overridePrice !== null
                                    ? number_format($overridePrice, 2).' '.$currency
                                    : '— (الأساسي)' }}
                            </span>
                            @endcan
                        </td>
                        <td class="text-end">
                            @can('price_lists.manage')
                            <input type="number"
                                   name="prices[{{ $product->id }}][min_qty]"
                                   class="form-control form-control-sm text-end"
                                   value="{{ $entry?->min_quantity ?? 1 }}"
                                   step="0.001" min="1"
                                   style="max-width:90px;margin-right:auto;">
                            @else
                            <span class="text-muted">{{ $entry?->min_quantity ?? 1 }}</span>
                            @endcan
                        </td>
                        <td class="text-center">
                            @if($diffPct !== null)
                                <span class="badge {{ $diffPct < 0 ? 'bg-success' : ($diffPct > 0 ? 'bg-danger' : 'bg-secondary') }}">
                                    {{ $diffPct > 0 ? '+' : '' }}{{ $diffPct }}%
                                </span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

        @can('price_lists.manage')
            <div class="mt-3">
                <button type="submit" class="btn btn-info text-white">
                    <i class="bi bi-save me-1"></i> حفظ كل الأسعار
                </button>
                <span class="text-muted small ms-2">الحقول الفارغة = استخدام سعر المنتج الأساسي</span>
            </div>
        </form>
        @endcan
    </div>
</div>
@endsection

@section('scripts')
<script>
$('#productSearch').on('input', function() {
    const q = $(this).val().toLowerCase();
    $('.product-row').each(function() {
        const name = $(this).data('name');
        $(this).toggle(name.includes(q));
    });
});
</script>
@endsection
