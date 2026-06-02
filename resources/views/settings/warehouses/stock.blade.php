@extends('layouts.app')
@section('page-title', 'رصيد المخزون — ' . $warehouse->name)

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">
                <i class="bi bi-boxes text-success me-2"></i>
                رصيد المخزون — <strong>{{ $warehouse->name }}</strong>
                @if($warehouse->branch)
                    <span class="badge bg-secondary ms-2">{{ $warehouse->branch->name }}</span>
                @endif
            </h5>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('warehouses.index') }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-right"></i> المخازن
            </a>
        </div>
    </div>
    <div class="card-body">

        {{-- Summary row --}}
        @php
            $totalQty   = $stockItems->sum('quantity');
            $totalValue = $stockItems->sum(fn($s) => $s->quantity * ($s->product->cost_price ?? 0));
            $lowStock   = $stockItems->filter(fn($s) => $s->isLowStock())->count();
        @endphp
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-success bg-opacity-10 border-0 text-center p-3">
                    <div class="fs-4 fw-bold">{{ number_format($stockItems->count()) }}</div>
                    <div class="text-muted small">عدد الأصناف</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-primary bg-opacity-10 border-0 text-center p-3">
                    <div class="fs-4 fw-bold">{{ number_format($totalQty, 2) }}</div>
                    <div class="text-muted small">إجمالي الكميات</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning bg-opacity-10 border-0 text-center p-3">
                    <div class="fs-4 fw-bold">{{ number_format($totalValue, 2) }} {{ $currency }}</div>
                    <div class="text-muted small">قيمة المخزون (بالتكلفة)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger bg-opacity-10 border-0 text-center p-3">
                    <div class="fs-4 fw-bold {{ $lowStock > 0 ? 'text-danger' : 'text-success' }}">
                        {{ $lowStock }}
                    </div>
                    <div class="text-muted small">أصناف دون الحد الأدنى</div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%"
                   data-title="رصيد مخزون — {{ $warehouse->name }}">
                <thead class="table-light">
                <tr>
                    <th>المنتج</th>
                    <th>الباركود</th>
                    <th>الوحدة</th>
                    <th class="text-center">الكمية</th>
                    <th class="text-center">الحد الأدنى</th>
                    <th class="text-end">سعر التكلفة</th>
                    <th class="text-end">القيمة</th>
                    <th class="text-center">الحالة</th>
                </tr>
                </thead>
                <tbody>
                @foreach($stockItems as $item)
                @php $product = $item->product; @endphp
                <tr class="{{ $item->isLowStock() ? 'table-warning' : '' }}">
                    <td class="fw-semibold">{{ $product->name }}</td>
                    <td class="text-muted small font-monospace">{{ $product->barcode ?? '—' }}</td>
                    <td>
                        {{ $product->unit instanceof \App\Enums\ProductUnit ? $product->unit->label() : $product->unit }}
                    </td>
                    <td class="text-center fw-bold {{ $item->quantity <= 0 ? 'text-danger' : '' }}">
                        {{ number_format($item->quantity, 2) + 0 }}
                    </td>
                    <td class="text-center text-muted">{{ number_format($item->min_quantity, 2) + 0 }}</td>
                    <td class="text-end">{{ number_format($product->cost_price, 2) }} {{ $currency }}</td>
                    <td class="text-end">
                        {{ number_format($item->quantity * $product->cost_price, 2) }} {{ $currency }}
                    </td>
                    <td class="text-center">
                        @if($item->quantity <= 0)
                            <span class="badge bg-danger">نفذ</span>
                        @elseif($item->isLowStock())
                            <span class="badge bg-warning text-dark">منخفض</span>
                        @else
                            <span class="badge bg-success">متوفر</span>
                        @endif
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
