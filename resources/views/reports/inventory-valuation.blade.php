@extends('layouts.app')

@section('page-title', 'تقييم المخزون (IAS 2)')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-clipboard-data text-success"></i> تقييم المخزون — التكلفة مقابل صافي القيمة البيعية (IAS 2)</h5>
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> التقارير</a>
</div>

<div class="alert alert-light border small d-print-none mb-3">
    <i class="bi bi-info-circle text-primary"></i>
    وفق معيار المحاسبة الدولي IAS 2، يُقاس المخزون بـ<strong>أقل من: التكلفة أو صافي القيمة البيعية (NRV)</strong>.
    صافي القيمة البيعية = سعر البيع المقدّر ناقص تكاليف بيع تقديرية ({{ number_format($sellCostPct, 2) }}% — تُضبط من مفتاح الإعداد <code>nrv_selling_cost_percent</code>).
</div>

<div class="row g-2 mb-3">
    <div class="col-md-3"><div class="alert alert-secondary mb-0 text-center py-2"><div class="small">قيمة التكلفة</div><div class="fs-5 fw-bold">{{ number_format($totals['cost'], 2) }}</div></div></div>
    <div class="col-md-3"><div class="alert alert-info mb-0 text-center py-2"><div class="small">صافي القيمة البيعية</div><div class="fs-5 fw-bold">{{ number_format($totals['nrv'], 2) }}</div></div></div>
    <div class="col-md-3"><div class="alert alert-success mb-0 text-center py-2"><div class="small">القيمة المعتمدة (الأقل)</div><div class="fs-5 fw-bold">{{ number_format($totals['lcm'], 2) }}</div></div></div>
    <div class="col-md-3"><div class="alert {{ $totals['writedown'] > 0 ? 'alert-danger' : 'alert-light border' }} mb-0 text-center py-2"><div class="small">هبوط القيمة الواجب</div><div class="fs-5 fw-bold">{{ number_format($totals['writedown'], 2) }}</div></div></div>
</div>

<form method="GET" class="mb-3 d-print-none">
    <div class="form-check">
        <input type="checkbox" class="form-check-input" id="ow" name="only_writedown" value="1" {{ $onlyWritedown ? 'checked' : '' }} onchange="this.form.submit()">
        <label class="form-check-label" for="ow">عرض الأصناف التي تحتاج هبوط قيمة فقط (NRV أقل من التكلفة)</label>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="تقييم المخزون IAS2">
                <thead><tr>
                    <th>الصنف</th><th>الفئة</th><th>الكمية</th>
                    <th>تكلفة الوحدة ({{ $cur }})</th><th>NRV الوحدة</th>
                    <th>قيمة التكلفة</th><th>القيمة المعتمدة</th><th>هبوط القيمة</th>
                </tr></thead>
                <tbody>
                    @foreach($rows as $r)
                    <tr class="{{ $r->writedown > 0.005 ? 'table-danger' : '' }}">
                        <td>{{ $r->product->name }}</td>
                        <td>{{ $r->product->category?->name ?? '—' }}</td>
                        <td>{{ number_format($r->qty, 2) }}</td>
                        <td>{{ number_format($r->cost_unit, 2) }}</td>
                        <td class="{{ $r->nrv_unit < $r->cost_unit ? 'text-danger fw-bold' : '' }}">{{ number_format($r->nrv_unit, 2) }}</td>
                        <td>{{ number_format($r->cost_value, 2) }}</td>
                        <td class="fw-bold">{{ number_format($r->lcm_value, 2) }}</td>
                        <td class="{{ $r->writedown > 0.005 ? 'text-danger fw-bold' : 'text-muted' }}">{{ number_format($r->writedown, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($totals['writedown'] > 0)
<div class="alert alert-warning mt-3">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>قيد هبوط القيمة المقترح (IAS 2):</strong>
    <div class="mt-2" style="max-width:480px">
        <table class="table table-sm mb-0">
            <tr><td>مدين: مصروف هبوط قيمة المخزون</td><td class="text-end">{{ number_format($totals['writedown'], 2) }}</td></tr>
            <tr><td>دائن: مخصّص هبوط قيمة المخزون</td><td class="text-end">{{ number_format($totals['writedown'], 2) }}</td></tr>
        </table>
    </div>
    <small class="text-muted">يُرحَّل يدويًا من القيود اليومية بعد مراجعة الأصناف (خاصة قرب انتهاء الصلاحية/التالفة).</small>
</div>
@endif
@endsection
