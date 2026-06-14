@extends('layouts.app')

@section('page-title', 'الأصناف الراكدة')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-hourglass-bottom"></i> الأصناف الراكدة (رصيد دون مبيعات)</h5>
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> التقارير</a>
</div>

<form method="GET" class="card card-body mb-3 d-print-none">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label mb-1">دون مبيعات منذ (يوم)</label>
            <input type="number" min="1" name="days" value="{{ $days }}" class="form-control">
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary"><i class="bi bi-funnel"></i> تطبيق</button>
        </div>
        <div class="col-md-6 text-md-end">
            <span class="badge bg-dark fs-6">رأس مال معطّل: {{ number_format($totalTied, 2) }} {{ $cur }}</span>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="الأصناف الراكدة">
                <thead><tr>
                    <th>الباركود</th><th>الصنف</th><th>الفئة</th>
                    <th>الرصيد</th><th>تكلفة الوحدة</th><th>القيمة المعطّلة ({{ $cur }})</th>
                </tr></thead>
                <tbody>
                    @foreach($products as $p)
                    <tr>
                        <td class="font-monospace">{{ $p->barcode ?? '—' }}</td>
                        <td>{{ $p->name }}</td>
                        <td>{{ $p->category?->name ?? '—' }}</td>
                        <td class="fw-bold">{{ number_format($p->quantity, 2) }}</td>
                        <td>{{ number_format($p->cost_price, 2) }}</td>
                        <td class="text-danger">{{ number_format((float)$p->quantity * (float)$p->cost_price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($products->isEmpty())
            <p class="text-muted text-center mb-0">لا توجد أصناف راكدة خلال آخر {{ $days }} يوم. 👍</p>
        @endif
    </div>
</div>
@endsection
