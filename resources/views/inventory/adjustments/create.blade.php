@extends('layouts.app')
@section('page-title', 'تعديل مخزون جديد')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-7">

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-box-seam"></i> تعديل مخزون يدوي</span>
        <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>
    <div class="card-body">

        <form method="POST" action="{{ route('inventory.adjustments.store') }}" id="adjForm">
            @csrf

            {{-- Warehouse selector (only shown when >1 warehouse) --}}
            @if(isset($warehouses) && $warehouses->count() > 1)
            <div class="mb-3">
                <label class="form-label fw-bold">
                    <i class="bi bi-archive text-success me-1"></i>المخزن
                </label>
                <select name="warehouse_id" id="warehouseSelect" class="form-select">
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}"
                            {{ old('warehouse_id', $defaultWarehouseId ?? '') == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}@if($wh->branch) ({{ $wh->branch->name }})@endif
                            @if($wh->is_default) ⭐@endif
                        </option>
                    @endforeach
                </select>
                <div class="form-text">الكمية الحالية ستُعرض بناءً على رصيد المخزن المحدد</div>
            </div>
            @else
            <input type="hidden" name="warehouse_id" value="{{ $defaultWarehouseId ?? '' }}">
            @endif

            {{-- AJAX product search (no preload of all products) --}}
            <div class="mb-3">
                <label class="form-label fw-bold">بحث بالاسم أو الباركود <span class="text-danger">*</span></label>
                <input type="text" id="productSearch" class="form-control"
                       placeholder="اكتب اسم المنتج أو امسح الباركود…"
                       autocomplete="off" autofocus>
                <input type="hidden" name="product_id" id="productId" required>
                <div id="searchResults" class="list-group mt-1" style="max-height:220px;overflow-y:auto;display:none;position:absolute;z-index:1000;width:calc(100% - 2rem)"></div>
                <div id="selectedProduct" class="alert alert-info py-2 mt-2 d-none small">
                    <strong id="selName"></strong>
                    <span class="ms-2 text-muted" id="selBarcode"></span>
                </div>
                @error('product_id') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            {{-- Current stock info --}}
            <div id="stockInfo" class="alert alert-info d-none mb-3">
                <div class="d-flex justify-content-between">
                    <span>الكمية الحالية في النظام:</span>
                    <strong id="currentQtyDisplay">—</strong>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <span>تكلفة الوحدة:</span>
                    <strong id="costDisplay">—</strong>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">الكمية الفعلية (بعد التعديل) <span class="text-danger">*</span></label>
                <input type="number" name="quantity_after" id="quantityAfter"
                       class="form-control" min="0" step="0.001"
                       placeholder="أدخل الكمية الفعلية الموجودة"
                       oninput="calcDiff()" required>
                @error('quantity_after') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            {{-- Live difference --}}
            <div id="diffBox" class="alert d-none mb-3">
                <div class="d-flex justify-content-between">
                    <span>الفرق:</span>
                    <strong id="diffDisplay">—</strong>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <span>القيمة المالية للفرق:</span>
                    <strong id="diffValue">—</strong>
                </div>
                <div class="mt-2 small" id="glHint"></div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">سبب التعديل <span class="text-danger">*</span></label>
                <select name="reason" class="form-select" required>
                    <option value="">— اختر السبب —</option>
                    @foreach(\App\Models\InventoryAdjustment::$reasons as $k => $v)
                        <option value="{{ $k }}" {{ old('reason') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
                @error('reason') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"
                          placeholder="وصف اختياري للتعديل...">{{ old('notes') }}</textarea>
            </div>

            <div class="alert alert-warning small mb-3">
                <i class="bi bi-exclamation-triangle"></i>
                سيتم إنشاء <strong>قيد محاسبي تلقائي</strong> عند حفظ التعديل.
                تأكد من صحة البيانات قبل الحفظ.
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-save"></i> حفظ التعديل وإنشاء القيد
                </button>
                <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-outline-secondary">
                    إلغاء
                </a>
            </div>
        </form>

    </div>
</div>

</div>
</div>
@endsection

@section('scripts')
<script>
const CUR = '{{ $currency }}';
let currentQty = 0, costPerUnit = 0, searchTimer;

// AJAX product search
const searchInput   = document.getElementById('productSearch');
const searchResults = document.getElementById('searchResults');
const productIdInput= document.getElementById('productId');
const selBox        = document.getElementById('selectedProduct');

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 1) { searchResults.style.display = 'none'; return; }
    searchTimer = setTimeout(async () => {
        const res  = await fetch(`{{ route('inventory.adjustments.search') }}?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!data.length) { searchResults.style.display = 'none'; return; }
        searchResults.innerHTML = data.map(p =>
            `<button type="button" class="list-group-item list-group-item-action py-1 px-2 small"
                     data-id="${p.id}" data-qty="${p.quantity}" data-cost="${p.cost_price}"
                     data-unit="${p.unit}" data-barcode="${p.barcode ?? ''}" data-name="${p.name}">
                <strong>${p.name}</strong>
                ${p.barcode ? `<small class="text-muted ms-2">${p.barcode}</small>` : ''}
                <span class="float-start text-muted">${p.quantity} ${p.unit}</span>
             </button>`
        ).join('');
        searchResults.style.display = 'block';
    }, 200);
});

searchResults.addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;
    selectProduct(btn.dataset);
});

function selectProduct(d) {
    productIdInput.value   = d.id;
    currentQty             = parseFloat(d.qty)  || 0;
    costPerUnit            = parseFloat(d.cost) || 0;
    searchInput.value      = d.name;
    searchResults.style.display = 'none';
    document.getElementById('selName').textContent    = d.name;
    document.getElementById('selBarcode').textContent = d.barcode ? `(${d.barcode})` : '';
    selBox.classList.remove('d-none');

    document.getElementById('currentQtyDisplay').textContent = currentQty.toFixed(3) + ' ' + (d.unit || '');
    document.getElementById('costDisplay').textContent       = costPerUnit.toFixed(2) + ' ' + CUR;
    document.getElementById('stockInfo').classList.remove('d-none');
    document.getElementById('quantityAfter').focus();
    calcDiff();
}

// Close results when clicking outside
document.addEventListener('click', e => {
    if (!searchResults.contains(e.target) && e.target !== searchInput) {
        searchResults.style.display = 'none';
    }
});

function calcDiff() {
    const after = parseFloat(document.getElementById('quantityAfter').value);
    const box   = document.getElementById('diffBox');
    if (isNaN(after)) { box.classList.add('d-none'); return; }

    const diff  = after - currentQty;
    const value = Math.abs(diff) * costPerUnit;
    const isNeg = diff < 0;

    document.getElementById('diffDisplay').textContent =
        (diff >= 0 ? '+' : '') + diff.toFixed(3);
    document.getElementById('diffDisplay').className = isNeg ? 'text-danger fw-bold' : 'text-success fw-bold';

    document.getElementById('diffValue').textContent = value.toFixed(2) + ' ' + CUR;
    document.getElementById('diffValue').className   = isNeg ? 'text-danger fw-bold' : 'text-success fw-bold';

    document.getElementById('glHint').innerHTML = diff === 0 ? '' : isNeg
        ? '<i class="bi bi-journal-text"></i> سيُنشأ قيد: مدين مصروف عجز المخزون / دائن المخزون'
        : '<i class="bi bi-journal-text"></i> سيُنشأ قيد: مدين المخزون / دائن إيرادات أخرى';

    box.className = 'alert mb-3 ' + (isNeg ? 'alert-danger' : (diff === 0 ? 'alert-secondary' : 'alert-success'));
    box.classList.remove('d-none');
}
</script>
@endsection
