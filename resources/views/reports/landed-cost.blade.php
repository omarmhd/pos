@extends('layouts.app')

@section('page-title', 'تكلفة الاستيراد')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-airplane text-primary"></i> حاسبة تكلفة الاستيراد (Landed Cost)</h5>
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> التقارير</a>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white"><strong>مدخلات الاستيراد</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">الكمية</label>
                        <input type="number" id="qty" class="form-control" value="1" step="0.01" min="0" oninput="calc()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">سعر الوحدة (عملة المورد)</label>
                        <input type="number" id="unit" class="form-control" value="0" step="0.01" min="0" oninput="calc()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">سعر صرف عملة المورد</label>
                        <input type="number" id="fx" class="form-control" value="1" step="0.000001" min="0" oninput="calc()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">الشحن والتأمين ({{ $cur }})</label>
                        <input type="number" id="freight" class="form-control" value="0" step="0.01" min="0" oninput="calc()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">الرسوم الجمركية ({{ $cur }})</label>
                        <input type="number" id="customs" class="form-control" value="0" step="0.01" min="0" oninput="calc()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">مصاريف أخرى ({{ $cur }})</label>
                        <input type="number" id="other" class="form-control" value="0" step="0.01" min="0" oninput="calc()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">هامش الربح المطلوب %</label>
                        <input type="number" id="margin" class="form-control" value="20" step="0.01" min="0" oninput="calc()">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white"><strong>النتيجة</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td>قيمة البضاعة (بالعملة الأساسية)</td><td class="text-end" id="rGoods">0.00</td></tr>
                    <tr><td>+ الشحن والتأمين</td><td class="text-end" id="rFreight">0.00</td></tr>
                    <tr><td>+ الرسوم الجمركية</td><td class="text-end" id="rCustoms">0.00</td></tr>
                    <tr><td>+ مصاريف أخرى</td><td class="text-end" id="rOther">0.00</td></tr>
                    <tr class="table-light fw-bold"><td>إجمالي التكلفة الكلية</td><td class="text-end" id="rTotal">0.00</td></tr>
                    <tr class="fw-bold text-primary"><td>تكلفة الوحدة الكلية (Landed)</td><td class="text-end" id="rUnit">0.00</td></tr>
                    <tr class="fw-bold text-success"><td>سعر البيع المقترح للوحدة</td><td class="text-end" id="rSell">0.00</td></tr>
                </table>
                <div class="form-text mt-2">سعر البيع = تكلفة الوحدة الكلية ÷ (1 − الهامش%).</div>
            </div>
        </div>
    </div>
</div>

<script>
function n(id){ return parseFloat(document.getElementById(id).value) || 0; }
function f(x){ return x.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
function calc(){
    const qty=n('qty'), unit=n('unit'), fx=n('fx'), freight=n('freight'), customs=n('customs'), other=n('other'), margin=n('margin');
    const goods = qty*unit*fx;
    const total = goods+freight+customs+other;
    const unitCost = qty>0 ? total/qty : 0;
    const sell = margin<100 ? unitCost/(1-margin/100) : 0;
    document.getElementById('rGoods').textContent=f(goods);
    document.getElementById('rFreight').textContent=f(freight);
    document.getElementById('rCustoms').textContent=f(customs);
    document.getElementById('rOther').textContent=f(other);
    document.getElementById('rTotal').textContent=f(total);
    document.getElementById('rUnit').textContent=f(unitCost);
    document.getElementById('rSell').textContent=f(sell);
}
calc();
</script>
@endsection
