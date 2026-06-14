@extends('layouts.app')

@section('page-title', 'حد إعادة الطلب')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-arrow-repeat text-warning"></i> أصناف بلغت حد إعادة الطلب ({{ $reorderProducts->count() }})</h5>
            </div>
            <div class="card-body">
                @if($reorderProducts->isEmpty())
                    <p class="text-muted mb-0">لا توجد أصناف بلغت حد إعادة الطلب.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-hover dt-table" style="width:100%" data-title="حد إعادة الطلب">
                        <thead>
                            <tr>
                                <th>الصنف</th>
                                <th>الفئة</th>
                                <th>الرصيد الحالي</th>
                                <th>حد إعادة الطلب</th>
                                <th>الحد الأقصى</th>
                                <th>الكمية المقترح طلبها</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reorderProducts as $p)
                            <tr>
                                <td><a href="{{ route('products.show', $p) }}">{{ $p->name }}</a></td>
                                <td>{{ $p->category?->name ?? '—' }}</td>
                                <td><span class="badge bg-danger">{{ number_format($p->quantity, 2) }}</span></td>
                                <td>{{ number_format($p->reorder_level, 2) }}</td>
                                <td>{{ $p->max_quantity !== null ? number_format($p->max_quantity, 2) : '—' }}</td>
                                <td>
                                    @php
                                        $target = $p->max_quantity !== null && $p->max_quantity > 0
                                            ? (float) $p->max_quantity
                                            : (float) $p->reorder_level * 2;
                                        $suggested = max(0, $target - (float) $p->quantity);
                                    @endphp
                                    <strong>{{ number_format($suggested, 2) }}</strong>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-box-seam text-danger"></i> أصناف تجاوزت الحد الأقصى للتخزين ({{ $overMaxProducts->count() }})</h5>
            </div>
            <div class="card-body">
                @if($overMaxProducts->isEmpty())
                    <p class="text-muted mb-0">لا توجد أصناف متجاوزة للحد الأقصى.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-hover dt-table" style="width:100%" data-title="تجاوز الحد الأقصى">
                        <thead>
                            <tr>
                                <th>الصنف</th>
                                <th>الفئة</th>
                                <th>الرصيد الحالي</th>
                                <th>الحد الأقصى</th>
                                <th>الفائض</th>
                                <th>قيمة الفائض ({{ $currency }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($overMaxProducts as $p)
                            @php $excess = (float) $p->quantity - (float) $p->max_quantity; @endphp
                            <tr>
                                <td><a href="{{ route('products.show', $p) }}">{{ $p->name }}</a></td>
                                <td>{{ $p->category?->name ?? '—' }}</td>
                                <td><span class="badge bg-warning text-dark">{{ number_format($p->quantity, 2) }}</span></td>
                                <td>{{ number_format($p->max_quantity, 2) }}</td>
                                <td>{{ number_format($excess, 2) }}</td>
                                <td>{{ number_format($excess * (float) $p->cost_price, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
