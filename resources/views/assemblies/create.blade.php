@extends('layouts.app')

@section('page-title', 'أمر تصنيع جديد')

@section('content')
<div class="row">
    <div class="col-lg-9 mx-auto">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-gear-wide-connected"></i> أمر تصنيع / تجميع جديد</h5>
            </div>
            <div class="card-body">
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @if($products->isEmpty())
                    <div class="alert alert-warning mb-0">
                        لا توجد أصناف لها معادلة تصنيع. عرّف مكونات الصنف أولاً من
                        <a href="{{ route('products.index') }}">بطاقة الصنف</a> (قسم "معادلة التصنيع").
                    </div>
                @else
                <form action="{{ route('assemblies.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">الصنف المنتَج <span class="text-danger">*</span></label>
                            <select name="product_id" id="asmProduct" class="form-select" required onchange="renderBom()">
                                <option value="">اختر الصنف</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}" {{ old('product_id') == $p->id ? 'selected' : '' }}>
                                        {{ $p->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الكمية المنتجة <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="asmQty" class="form-control"
                                   value="{{ old('quantity', 1) }}" step="0.001" min="0.001" required oninput="renderBom()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">التاريخ <span class="text-danger">*</span></label>
                            <input type="date" name="assembly_date" class="form-control"
                                   value="{{ old('assembly_date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">المخزن <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="form-select" required>
                                @foreach($warehouses as $w)
                                    <option value="{{ $w->id }}" {{ old('warehouse_id') == $w->id ? 'selected' : ($w->is_default ? 'selected' : '') }}>
                                        {{ $w->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">ملاحظات</label>
                            <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" maxlength="1000">
                        </div>

                        <div class="col-12">
                            <h6 class="text-primary"><i class="bi bi-diagram-3"></i> المكونات المطلوبة</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="bomTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>المكوّن</th>
                                            <th>لكل وحدة</th>
                                            <th>الإجمالي المطلوب</th>
                                            <th>تكلفة الوحدة</th>
                                            <th>إجمالي التكلفة ({{ $currency }})</th>
                                        </tr>
                                    </thead>
                                    <tbody><tr><td colspan="5" class="text-muted text-center">اختر الصنف أولاً</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('تنفيذ التصنيع سيخصم المكونات من المخزون ويضيف الصنف المنتَج ويرحّل القيد. متابعة؟')">
                                <i class="bi bi-check2-circle"></i> تنفيذ التصنيع
                            </button>
                            <a href="{{ route('assemblies.index') }}" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> إلغاء
                            </a>
                        </div>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>

<div id="__bomData" style="display:none">{{ json_encode($products->mapWithKeys(fn($p) => [$p->id => $p->components->map(fn($c) => [
    'name' => $c->component->name,
    'per'  => (float) $c->quantity,
    'cost' => (float) $c->component->cost_price,
])])) }}</div>
@endsection

@section('scripts')
<script>
const BOM = JSON.parse(document.getElementById('__bomData').textContent);

function renderBom() {
    const pid  = document.getElementById('asmProduct').value;
    const qty  = parseFloat(document.getElementById('asmQty').value) || 0;
    const tbody = document.querySelector('#bomTable tbody');
    if (!pid || !BOM[pid]) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">اختر الصنف أولاً</td></tr>';
        return;
    }
    let total = 0;
    const rows = BOM[pid].map(c => {
        const need = c.per * qty;
        const cost = need * c.cost;
        total += cost;
        return `<tr>
            <td>${c.name}</td>
            <td>${c.per}</td>
            <td><strong>${need.toFixed(3)}</strong></td>
            <td>${c.cost.toFixed(2)}</td>
            <td>${cost.toFixed(2)}</td>
        </tr>`;
    }).join('');
    tbody.innerHTML = rows + `<tr class="table-light fw-bold">
        <td colspan="4">إجمالي تكلفة الإنتاج${qty > 0 ? ' (للوحدة: ' + (total / qty).toFixed(4) + ')' : ''}</td>
        <td>${total.toFixed(2)}</td></tr>`;
}

document.addEventListener('DOMContentLoaded', renderBom);
</script>
@endsection
