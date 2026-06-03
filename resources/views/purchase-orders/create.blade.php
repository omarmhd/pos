@extends('layouts.app')
@section('page-title', 'أمر شراء جديد')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" rel="stylesheet">
<style>.select2-container { width: 100% !important; }</style>
@endpush

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-file-earmark-plus text-warning me-2"></i>إنشاء أمر شراء جديد
        </h5>
    </div>
    <div class="card-body">

        <div class="alert alert-light border-start border-warning border-3 mb-4 small">
            <strong>ملاحظة:</strong> أمر الشراء لا يُنشئ قيداً محاسبياً.
            القيد يُرحَّل فقط عند تحويله لفاتورة شراء.
        </div>

        <form action="{{ route('purchase-orders.store') }}" method="POST" id="po-form">
            @csrf

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">المورد <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                        <option value="">اختر المورد</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected':'' }}>
                                {{ $s->name }}{{ $s->company ? ' — '.$s->company : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">تاريخ الأمر <span class="text-danger">*</span></label>
                    <input type="date" name="order_date" class="form-control"
                           value="{{ old('order_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">تاريخ التسليم المتوقع</label>
                    <input type="date" name="expected_delivery_date" class="form-control"
                           value="{{ old('expected_delivery_date') }}">
                </div>
                @if($warehouses->count() > 1)
                <div class="col-md-2">
                    <label class="form-label">المخزن المستلِم</label>
                    <select name="warehouse_id" class="form-select">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}"
                                {{ old('warehouse_id', $defaultWarehouseId) == $wh->id ? 'selected':'' }}>
                                {{ $wh->name }} @if($wh->is_default) ⭐ @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                @else
                    <input type="hidden" name="warehouse_id" value="{{ $defaultWarehouseId }}">
                @endif
            </div>

            {{-- Items table --}}
            <div class="card mb-4 border-warning">
                <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-warning"><i class="bi bi-list-check me-1"></i>الأصناف المطلوبة</h6>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="addPORow()">
                        <i class="bi bi-plus-circle"></i> إضافة صنف
                    </button>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="po-items-table" style="min-width:620px;">
                            <thead class="table-light">
                            <tr>
                                <th style="width:42%">الصنف</th>
                                <th style="width:16%;min-width:100px">الكمية المطلوبة</th>
                                <th style="width:19%;min-width:120px">سعر الوحدة</th>
                                <th style="width:18%;min-width:110px">المجموع</th>
                                <th style="width:5%"></th>
                            </tr>
                            </thead>
                            <tbody id="po-rows"></tbody>
                            <tfoot>
                            <tr class="table-warning">
                                <td colspan="3" class="text-end fw-bold">إجمالي أمر الشراء:</td>
                                <td colspan="2" class="fw-bold" id="po-total">0.00 {{ $currency }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label">الشروط والأحكام</label>
                    <textarea name="terms" class="form-control" rows="2"
                              placeholder="شروط الدفع، الضمان، التسليم…">{{ old('terms') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-warning">
                <i class="bi bi-save me-1"></i> حفظ أمر الشراء
            </button>
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-secondary">إلغاء</a>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const SEARCH_URL = "{{ route('purchases.products.search') }}";
    const CURRENCY   = "{{ $currency }}";
    let rowCount = 0;

    function addPORow() {
        rowCount++;
        const id = rowCount;
        const tr = `
        <tr id="po_row_${id}">
            <td>
                <select name="items[${id}][product_id]" class="form-select po-product"
                        id="po_ps_${id}" required></select>
            </td>
            <td>
                <input type="number" name="items[${id}][quantity_ordered]"
                       class="form-control po-qty" value="1" min="0.001" step="0.001"
                       placeholder="الكمية" oninput="calcPO(${id})" required>
            </td>
            <td>
                <input type="number" name="items[${id}][unit_price]"
                       class="form-control po-price" step="0.01" min="0"
                       placeholder="السعر" oninput="calcPO(${id})" required>
            </td>
            <td><input type="text" class="form-control po-subtotal" readonly value="0.00"></td>
            <td>
                <button type="button" class="btn btn-sm btn-warning" onclick="removePORow(${id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>`;
        $('#po-rows').append(tr);

        $(`#po_ps_${id}`).select2({
            theme: 'bootstrap-5', dir: 'rtl', width: '100%',
            placeholder: 'ابحث عن صنف…', allowClear: true, minimumInputLength: 0,
            ajax: {
                url: SEARCH_URL, dataType: 'json', delay: 200,
                data: p => ({ q: p.term || '' }),
                processResults: data => ({
                    results: data.map(d => ({ id: d.id, text: d.text, cost_price: d.cost_price || 0 }))
                }),
                cache: true,
            },
            language: { noResults: () => 'لا نتائج', searching: () => 'بحث…', inputTooShort: () => 'اكتب للبحث…' },
        });

        $(`#po_ps_${id}`).on('select2:select', function(e) {
            $(`#po_row_${id} .po-price`).val(e.params.data.cost_price || 0);
            calcPO(id);
        });
    }

    function calcPO(id) {
        const qty   = parseFloat($(`#po_row_${id} .po-qty`).val())   || 0;
        const price = parseFloat($(`#po_row_${id} .po-price`).val()) || 0;
        $(`#po_row_${id} .po-subtotal`).val((qty * price).toFixed(2));
        calcPOTotal();
    }

    function removePORow(id) { $(`#po_row_${id}`).remove(); calcPOTotal(); }

    function calcPOTotal() {
        let t = 0;
        $('.po-subtotal').each(function() { t += parseFloat($(this).val()) || 0; });
        $('#po-total').text(t.toFixed(2) + ' ' + CURRENCY);
    }

    $('#po-form').on('submit', function(e) {
        if ($('#po-rows tr').length === 0) {
            e.preventDefault(); alert('أضف صنفاً واحداً على الأقل!');
        }
    });

    $(function() { addPORow(); });
</script>
@endsection
