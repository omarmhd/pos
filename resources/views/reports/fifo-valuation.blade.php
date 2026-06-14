@extends('layouts.app')

@section('page-title', 'تقييم FIFO')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-layers text-success"></i> تقييم المخزون بطريقة FIFO (الوارد أولاً صادر أولاً)</h5>
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> التقارير</a>
</div>

<div class="alert alert-light border small d-print-none mb-3">
    <i class="bi bi-info-circle text-primary"></i>
    وفق IAS 2 §25، يُقيَّم المخزون المتبقي بطريقة FIFO من <strong>أحدث طبقات الشراء</strong> (لأن الأقدم خرج أولاً للبيع).
    هذا التقرير للتحليل والمقارنة فقط — التكلفة المعتمدة في الترحيل تبقى المتوسط المرجّح (AVCO).
</div>

<div class="row g-2 mb-3">
    <div class="col-md-4"><div class="alert alert-success mb-0 text-center py-2"><div class="small">قيمة المخزون FIFO</div><div class="fs-5 fw-bold">{{ number_format($totals['fifo'], 2) }} {{ $cur }}</div></div></div>
    <div class="col-md-4"><div class="alert alert-secondary mb-0 text-center py-2"><div class="small">قيمة المخزون AVCO (المعتمدة)</div><div class="fs-5 fw-bold">{{ number_format($totals['avco'], 2) }} {{ $cur }}</div></div></div>
    <div class="col-md-4"><div class="alert alert-info mb-0 text-center py-2"><div class="small">الفرق (FIFO − AVCO)</div><div class="fs-5 fw-bold">{{ number_format($totals['fifo'] - $totals['avco'], 2) }} {{ $cur }}</div></div></div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="تقييم FIFO">
                <thead><tr>
                    <th>الصنف</th><th>الفئة</th><th>الكمية</th>
                    <th>قيمة FIFO ({{ $cur }})</th><th>قيمة AVCO ({{ $cur }})</th><th>الفرق</th>
                </tr></thead>
                <tbody>
                    @foreach($rows as $r)
                    @php $diff = $r->fifo_value - $r->avco_value; @endphp
                    <tr>
                        <td>{{ $r->product->name }}</td>
                        <td>{{ $r->product->category?->name ?? '—' }}</td>
                        <td>{{ number_format($r->qty, 2) }}</td>
                        <td class="fw-bold">{{ number_format($r->fifo_value, 2) }}</td>
                        <td>{{ number_format($r->avco_value, 2) }}</td>
                        <td class="{{ $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : '') }}">{{ ($diff>0?'+':'').number_format($diff, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
