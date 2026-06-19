@php
    $stock  = (float) $product->quantity;
    $cost   = (float) $product->cost_price;
    $price  = (float) $product->selling_price;
    $value  = $stock * $cost;
    $margin = $price - $cost;
    $marginPct = $cost > 0 ? ($margin / $cost) * 100 : 0;
@endphp

<div class="d-flex align-items-center gap-3 mb-3">
    @if($product->image)
        <img src="{{ asset('storage/' . $product->image) }}" width="56" height="56" class="rounded object-fit-cover">
    @else
        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:56px;height:56px">
            <i class="bi bi-box-seam text-muted fs-4"></i>
        </div>
    @endif
    <div>
        <h5 class="mb-0">{{ $product->name }}</h5>
        <div class="small text-muted">
            <span class="font-monospace">{{ $product->barcode }}</span>
            · <span class="badge bg-info">{{ $product->category?->name ?? '—' }}</span>
        </div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="border rounded p-2 text-center">
            <div class="small text-muted">المخزون الحالي</div>
            <div class="fs-5 fw-bold {{ $product->isLowStock() ? 'text-danger' : 'text-success' }}">{{ number_format($stock, 2) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded p-2 text-center">
            <div class="small text-muted">القيمة بالتكلفة</div>
            <div class="fs-6 fw-bold">{{ number_format($value, 2) }} <small>{{ $currency }}</small></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded p-2 text-center">
            <div class="small text-muted">سعر البيع</div>
            <div class="fs-6 fw-bold text-success">{{ number_format($price, 2) }} <small>{{ $currency }}</small></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded p-2 text-center">
            <div class="small text-muted">هامش الربح</div>
            <div class="fs-6 fw-bold">{{ number_format($margin, 2) }} <small>({{ number_format($marginPct, 1) }}%)</small></div>
        </div>
    </div>
</div>

<table class="table table-sm mb-0">
    <tr>
        <td class="text-muted">إجمالي المشتريات</td>
        <td class="text-end fw-semibold">{{ number_format($purch->c) }} حركة · {{ number_format($purch->t, 2) }} {{ $currency }}</td>
    </tr>
    <tr>
        <td class="text-muted">إجمالي المبيعات</td>
        <td class="text-end fw-semibold">{{ number_format($sales->c) }} حركة · {{ number_format($sales->t, 2) }} {{ $currency }}</td>
    </tr>
    <tr>
        <td class="text-muted">سعر الشراء (التكلفة الحالية)</td>
        <td class="text-end">{{ number_format($cost, 2) }} {{ $currency }}</td>
    </tr>
    <tr>
        <td class="text-muted">آخر حركة</td>
        <td class="text-end">{{ $lastMove ? \Illuminate\Support\Carbon::parse($lastMove)->format('Y-m-d H:i') : 'لا حركات' }}</td>
    </tr>
    @if($product->expiry_date)
    <tr>
        <td class="text-muted">الصلاحية</td>
        <td class="text-end">{{ $product->expiry_date->format('Y-m-d') }}</td>
    </tr>
    @endif
</table>
