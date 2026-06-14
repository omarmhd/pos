@extends('layouts.app')

@section('page-title', 'تنبيهات المخزون')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-exclamation-triangle text-danger"></i> تنبيهات المخزون</h5>
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> التقارير</a>
</div>

<div class="row g-2 mb-3">
    <div class="col-md-6">
        <div class="alert alert-warning mb-0 text-center py-2">
            <div class="small">أصناف دون حد إعادة الطلب</div>
            <div class="fs-4 fw-bold">{{ $belowReorder->count() }}</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="alert alert-info mb-0 text-center py-2">
            <div class="small">أصناف فوق الحد الأقصى</div>
            <div class="fs-4 fw-bold">{{ $overstock->count() }}</div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-warning bg-opacity-10"><strong>دون حد إعادة الطلب (يجب إعادة الطلب)</strong></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="أصناف دون حد إعادة الطلب">
                <thead><tr>
                    <th>الباركود</th><th>الصنف</th><th>الفئة</th>
                    <th>الرصيد</th><th>حد الطلب</th><th>الحد الأدنى</th><th>النقص</th>
                </tr></thead>
                <tbody>
                    @foreach($belowReorder as $p)
                    @php $level = $p->reorder_level ?? $p->min_quantity; @endphp
                    <tr>
                        <td class="font-monospace">{{ $p->barcode ?? '—' }}</td>
                        <td>{{ $p->name }}</td>
                        <td>{{ $p->category?->name ?? '—' }}</td>
                        <td class="text-danger fw-bold">{{ number_format($p->quantity, 2) }}</td>
                        <td>{{ $p->reorder_level !== null ? number_format($p->reorder_level, 2) : '—' }}</td>
                        <td>{{ $p->min_quantity !== null ? number_format($p->min_quantity, 2) : '—' }}</td>
                        <td class="text-danger">{{ number_format(max(0, (float)$level - (float)$p->quantity), 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-info bg-opacity-10"><strong>فوق الحد الأقصى (تكدّس / رأس مال معطّل)</strong></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="أصناف فوق الحد الأقصى">
                <thead><tr>
                    <th>الباركود</th><th>الصنف</th><th>الفئة</th>
                    <th>الرصيد</th><th>الحد الأقصى</th><th>الزيادة</th><th>قيمة الزيادة ({{ $cur }})</th>
                </tr></thead>
                <tbody>
                    @foreach($overstock as $p)
                    @php $excess = max(0, (float)$p->quantity - (float)$p->max_quantity); @endphp
                    <tr>
                        <td class="font-monospace">{{ $p->barcode ?? '—' }}</td>
                        <td>{{ $p->name }}</td>
                        <td>{{ $p->category?->name ?? '—' }}</td>
                        <td class="fw-bold">{{ number_format($p->quantity, 2) }}</td>
                        <td>{{ number_format($p->max_quantity, 2) }}</td>
                        <td class="text-info">{{ number_format($excess, 2) }}</td>
                        <td>{{ number_format($excess * (float)$p->cost_price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
