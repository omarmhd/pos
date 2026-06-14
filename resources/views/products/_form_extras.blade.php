{{-- resources/views/products/_form_extras.blade.php
     الأقسام المشتركة بين إنشاء/تعديل صنف:
     النوع، الحدود، الضريبة، البونص، العملة، الوحدات الإضافية، مكونات التجميعي.
     يتوقع: $currencies, $allProducts, واختيارياً $product --}}
@php
    $p          = $product ?? null;
    $units      = old('units', $p?->units?->map(fn($u) => [
                        'id' => $u->id, 'name' => $u->name, 'factor' => $u->factor,
                        'barcode' => $u->barcode, 'selling_price' => $u->selling_price,
                        'cost_price' => $u->cost_price,
                  ])->toArray() ?? []);
    $components = old('components', $p?->components?->map(fn($c) => [
                        'component_id' => $c->component_id, 'quantity' => $c->quantity,
                  ])->toArray() ?? []);
@endphp

{{-- ── نوع الصنف والحدود ─────────────────────────────────────────────── --}}
<div class="col-12"><hr class="my-2"><h6 class="text-primary"><i class="bi bi-sliders"></i> نوع الصنف وحدود المخزون</h6></div>

<div class="col-md-3">
    <label class="form-label">نوع الصنف <span class="text-danger">*</span></label>
    <select name="product_type" id="product_type" class="form-select @error('product_type') is-invalid @enderror" required>
        @foreach(\App\Models\Product::$types as $value => $label)
            <option value="{{ $value }}" {{ old('product_type', $p->product_type ?? (($mode ?? null) === 'product' ? 'bundle' : 'goods')) === $value ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    <div class="form-text">خدمة: بلا مخزون • تجميعي: يُخصم من مخزون مكوناته</div>
    @error('product_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="col-md-3">
    <label class="form-label">الفئة المحاسبية (IAS 2)</label>
    <select name="product_class" id="product_class" class="form-select @error('product_class') is-invalid @enderror">
        @foreach(\App\Models\Product::$classes as $value => $label)
            <option value="{{ $value }}" {{ old('product_class', $p->product_class ?? (($mode ?? null) === 'product' ? 'manufactured' : 'merchandise')) === $value ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    <div class="form-text">تحدّد حساب المخزون: بضاعة (1300) • مواد خام (1310) • منتج تام (1320)</div>
    @error('product_class')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="col-md-3">
    <label class="form-label">الحد الأقصى للمخزون</label>
    <input type="number" name="max_quantity" class="form-control @error('max_quantity') is-invalid @enderror"
           value="{{ old('max_quantity', $p->max_quantity ?? '') }}" step="0.001" min="0">
    @error('max_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="col-md-3">
    <label class="form-label">حد إعادة الطلب</label>
    <input type="number" name="reorder_level" class="form-control @error('reorder_level') is-invalid @enderror"
           value="{{ old('reorder_level', $p->reorder_level ?? '') }}" step="0.001" min="0">
    <div class="form-text">عند بلوغه يظهر الصنف في تقرير إعادة الطلب</div>
    @error('reorder_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

{{-- ── الضريبة ───────────────────────────────────────────────────────── --}}
<div class="col-12"><hr class="my-2"><h6 class="text-primary"><i class="bi bi-percent"></i> ضريبة القيمة المضافة</h6></div>

<div class="col-md-3 d-flex align-items-end pb-1">
    <div class="form-check form-switch">
        <input type="hidden" name="is_taxable" value="0">
        <input class="form-check-input" type="checkbox" name="is_taxable" id="is_taxable" value="1"
               {{ old('is_taxable', $p?->is_taxable ?? true) ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold" for="is_taxable">خاضع للضريبة</label>
    </div>
</div>

<div class="col-md-3">
    <label class="form-label">نسبة الضريبة الخاصة %</label>
    <input type="number" name="vat_rate" class="form-control @error('vat_rate') is-invalid @enderror"
           value="{{ old('vat_rate', $p->vat_rate ?? '') }}" step="0.01" min="0" max="100"
           placeholder="فارغ = النسبة العامة">
    @error('vat_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

{{-- ── البونص ────────────────────────────────────────────────────────── --}}
<div class="col-12"><hr class="my-2"><h6 class="text-primary"><i class="bi bi-gift"></i> الكمية الإضافية (بونص)</h6></div>

<div class="col-md-3">
    <label class="form-label">إضافي بعد الكمية</label>
    <input type="number" name="bonus_after_qty" class="form-control"
           value="{{ old('bonus_after_qty', $p->bonus_after_qty ?? '') }}" step="0.001" min="0"
           placeholder="مثال: 10">
    <div class="form-text">لا بونص قبل بلوغ هذه الكمية</div>
</div>
<div class="col-md-3">
    <label class="form-label">إضافي كل كمية</label>
    <input type="number" name="bonus_every_qty" class="form-control"
           value="{{ old('bonus_every_qty', $p->bonus_every_qty ?? '') }}" step="0.001" min="0"
           placeholder="مثال: 12">
</div>
<div class="col-md-3">
    <label class="form-label">الكمية المجانية</label>
    <input type="number" name="bonus_free_qty" class="form-control"
           value="{{ old('bonus_free_qty', $p->bonus_free_qty ?? 1) }}" step="0.001" min="0.001">
    <div class="form-text">تُمنح عن كل "إضافي كل كمية"</div>
</div>

{{-- ── العملة ────────────────────────────────────────────────────────── --}}
<div class="col-12"><hr class="my-2"><h6 class="text-primary"><i class="bi bi-currency-exchange"></i> عملة الأسعار (اختياري)</h6></div>

<div class="col-md-3">
    <label class="form-label">العملة</label>
    <select name="currency_id" class="form-select">
        <option value="">العملة الأساسية</option>
        @foreach($currencies as $cur)
            <option value="{{ $cur->id }}" {{ old('currency_id', $p->currency_id ?? '') == $cur->id ? 'selected' : '' }}>
                {{ $cur->name }} ({{ $cur->code }}) — سعر الصرف: {{ rtrim(rtrim(number_format($cur->exchange_rate, 6), '0'), '.') }}
            </option>
        @endforeach
    </select>
</div>
<div class="col-md-3">
    <label class="form-label">سعر الشراء بالعملة</label>
    <input type="number" name="cost_price_fc" class="form-control"
           value="{{ old('cost_price_fc', $p->cost_price_fc ?? '') }}" step="0.0001" min="0">
</div>
<div class="col-md-3">
    <label class="form-label">سعر البيع بالعملة</label>
    <input type="number" name="selling_price_fc" class="form-control"
           value="{{ old('selling_price_fc', $p->selling_price_fc ?? '') }}" step="0.0001" min="0">
    <div class="form-text">يُحوَّل تلقائياً للعملة الأساسية عند الحفظ</div>
</div>

{{-- ── الوحدات الإضافية ──────────────────────────────────────────────── --}}
<div class="col-12"><hr class="my-2">
    <h6 class="text-primary d-inline"><i class="bi bi-boxes"></i> الوحدات الإضافية (كرتون / دستة ...)</h6>
    <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="addUnitRow()">
        <i class="bi bi-plus"></i> إضافة وحدة
    </button>
</div>

<div class="col-12">
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0" id="units-table">
            <thead class="table-light">
                <tr>
                    <th style="width:20%">اسم الوحدة</th>
                    <th style="width:18%">المعامل (كم وحدة رئيسية)</th>
                    <th style="width:22%">باركود الوحدة</th>
                    <th style="width:15%">سعر بيع الوحدة</th>
                    <th style="width:15%">تكلفة الوحدة</th>
                    <th style="width:10%"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($units as $i => $u)
                <tr>
                    <td>
                        <input type="hidden" name="units[{{ $i }}][id]" value="{{ $u['id'] ?? '' }}">
                        <input type="text" name="units[{{ $i }}][name]" class="form-control form-control-sm" value="{{ $u['name'] ?? '' }}">
                    </td>
                    <td><input type="number" name="units[{{ $i }}][factor]" class="form-control form-control-sm" step="0.0001" min="0.0001" value="{{ $u['factor'] ?? '' }}"></td>
                    <td><input type="text" name="units[{{ $i }}][barcode]" class="form-control form-control-sm" value="{{ $u['barcode'] ?? '' }}"></td>
                    <td><input type="number" name="units[{{ $i }}][selling_price]" class="form-control form-control-sm" step="0.01" min="0" value="{{ $u['selling_price'] ?? '' }}" placeholder="تلقائي"></td>
                    <td><input type="number" name="units[{{ $i }}][cost_price]" class="form-control form-control-sm" step="0.01" min="0" value="{{ $u['cost_price'] ?? '' }}" placeholder="تلقائي"></td>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="form-text">المخزون يُحفظ دائماً بالوحدة الرئيسية. سعر/تكلفة فارغان = سعر الأساس × المعامل.</div>
</div>

{{-- ── مكونات الصنف التجميعي / معادلة التصنيع (تُخفى في وضع "صنف") ────── --}}
@if(($mode ?? null) !== 'item')
<div class="col-12" id="components-section">
    <hr class="my-2">
    <h6 class="text-primary d-inline"><i class="bi bi-diagram-3"></i> معادلة التصنيع / مكونات الصنف التجميعي</h6>
    <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="addComponentRow()">
        <i class="bi bi-plus"></i> إضافة مكوّن
    </button>
    <div class="table-responsive mt-2">
        <table class="table table-sm table-bordered align-middle mb-0" id="components-table">
            <thead class="table-light">
                <tr>
                    <th style="width:60%">المكوّن</th>
                    <th style="width:30%">الكمية المستهلكة لكل وحدة</th>
                    <th style="width:10%"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($components as $i => $c)
                <tr>
                    <td>
                        <select name="components[{{ $i }}][component_id]" class="form-select form-select-sm">
                            <option value="">اختر المكوّن</option>
                            @foreach($allProducts as $ap)
                                <option value="{{ $ap->id }}" {{ ($c['component_id'] ?? '') == $ap->id ? 'selected' : '' }}>
                                    {{ $ap->name }} (تكلفة: {{ number_format($ap->cost_price, 2) }})
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="number" name="components[{{ $i }}][quantity]" class="form-control form-control-sm" step="0.0001" min="0.0001" value="{{ $c['quantity'] ?? '' }}"></td>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="form-text">
        تُستخدم في شاشة التصنيع لإنتاج الصنف، وفي البيع المباشر للصنف "التجميعي" (يُخصم المخزون من المكونات).
    </div>
</div>
@endif

<script>
    let unitRowIdx = {{ count($units) }};
    function addUnitRow() {
        const tbody = document.querySelector('#units-table tbody');
        const i = unitRowIdx++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="hidden" name="units[${i}][id]" value="">
                <input type="text" name="units[${i}][name]" class="form-control form-control-sm" placeholder="كرتون"></td>
            <td><input type="number" name="units[${i}][factor]" class="form-control form-control-sm" step="0.0001" min="0.0001" placeholder="12"></td>
            <td><input type="text" name="units[${i}][barcode]" class="form-control form-control-sm"></td>
            <td><input type="number" name="units[${i}][selling_price]" class="form-control form-control-sm" step="0.01" min="0" placeholder="تلقائي"></td>
            <td><input type="number" name="units[${i}][cost_price]" class="form-control form-control-sm" step="0.01" min="0" placeholder="تلقائي"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>`;
        tbody.appendChild(tr);
    }

    let compRowIdx = {{ count($components) }};
    const componentOptions = `@foreach($allProducts as $ap)<option value="{{ $ap->id }}">{{ $ap->name }} (تكلفة: {{ number_format($ap->cost_price, 2) }})</option>@endforeach`;
    function addComponentRow() {
        const tbody = document.querySelector('#components-table tbody');
        const i = compRowIdx++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select name="components[${i}][component_id]" class="form-select form-select-sm">
                <option value="">اختر المكوّن</option>${componentOptions}</select></td>
            <td><input type="number" name="components[${i}][quantity]" class="form-control form-control-sm" step="0.0001" min="0.0001"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>`;
        tbody.appendChild(tr);
    }
</script>
