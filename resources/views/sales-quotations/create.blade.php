@extends('layouts.app')
@section('page-title', 'عرض سعر جديد')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" rel="stylesheet">
<style>.select2-container{width:100%!important}</style>
@endpush

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-file-earmark-richtext text-info me-2"></i>إنشاء عرض سعر جديد</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-light border-start border-info border-3 mb-4 small">
            <strong>ملاحظة:</strong> عرض السعر لا يُنشئ قيداً محاسبياً ولا يؤثر على المخزون.
        </div>
        <form action="{{ route('sales-quotations.store') }}" method="POST" id="qt-form">
            @csrf
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">العميل</label>
                    <select name="customer_id" id="customerSelect" class="form-select">
                        <option value="">عميل غير مسجل (أدخل الاسم)</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected':'' }}>
                                {{ $c->name }}{{ $c->phone ? ' — '.$c->phone : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4" id="customerNameWrap">
                    <label class="form-label">اسم العميل (إذا لم يكن مسجلاً)</label>
                    <input type="text" name="customer_name" class="form-control"
                           value="{{ old('customer_name') }}" placeholder="أدخل اسم العميل">
                </div>
                <div class="col-md-4">
                    <label class="form-label">قائمة الأسعار</label>
                    <select name="price_list_id" id="priceListSelect" class="form-select">
                        <option value="">— الافتراضي —</option>
                        @foreach($priceLists as $pl)
                            <option value="{{ $pl->id }}" {{ old('price_list_id') == $pl->id ? 'selected':'' }}>
                                {{ $pl->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">تاريخ العرض <span class="text-danger">*</span></label>
                    <input type="date" name="quotation_date" class="form-control"
                           value="{{ old('quotation_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">صالح حتى</label>
                    <input type="date" name="valid_until" class="form-control"
                           value="{{ old('valid_until') }}">
                    <div class="form-text">تاريخ انتهاء صلاحية العرض</div>
                </div>
            </div>

            {{-- Items --}}
            <div class="card mb-4 border-info">
                <div class="card-header bg-info bg-opacity-10 d-flex justify-content-between">
                    <h6 class="mb-0 text-info"><i class="bi bi-list-check me-1"></i>الأصناف والأسعار</h6>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="addQTRow()">
                        <i class="bi bi-plus-circle"></i> إضافة صنف
                    </button>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" style="min-width:700px;">
                            <thead class="table-light">
                            <tr>
                                <th style="width:35%">الصنف</th>
                                <th style="width:12%">الكمية</th>
                                <th style="width:17%">سعر الوحدة</th>
                                <th style="width:10%">خصم%</th>
                                <th style="width:17%">المجموع</th>
                                <th style="width:4%"></th>
                            </tr>
                            </thead>
                            <tbody id="qt-rows"></tbody>
                            <tfoot>
                            <tr class="table-info">
                                <td colspan="4" class="text-end fw-bold">الإجمالي:</td>
                                <td colspan="2" class="fw-bold" id="qt-total">0.00 {{ $currency }}</td>
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
                              placeholder="شروط الدفع، الضمان، مدة التسليم…">{{ old('terms') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-info text-white"><i class="bi bi-save me-1"></i> حفظ عرض السعر</button>
            <a href="{{ route('sales-quotations.index') }}" class="btn btn-secondary">إلغاء</a>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const SEARCH_URL   = "{{ route('purchases.products.search') }}";
const PRICE_URL    = "{{ route('sales-quotations.product-price') }}";
const CURRENCY     = "{{ $currency }}";
let rowCount = 0;

// ── Get selected price list ID ────────────────────────────────────────────
function getPriceListId() {
    return parseInt($('#priceListSelect').val()) || 0;
}

// ── Fetch the correct price for a product under the chosen price list ─────
function fetchProductPrice(productId, $priceInput) {
    if (!productId) return;
    const priceListId = getPriceListId();

    $.ajax({
        url: PRICE_URL,
        method: 'GET',
        dataType: 'json',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { product_id: productId, price_list_id: priceListId || '' },
        success: function(data) {
            $priceInput.val(data.price.toFixed(2));
            // Visual indicator: different color if price overridden by list
            if (data.is_overridden) {
                $priceInput.addClass('text-primary fw-bold').attr('title', 'سعر من قائمة: ' + data.price_list_name);
            } else {
                $priceInput.removeClass('text-primary fw-bold').attr('title', 'السعر الأساسي');
            }
            // Recalculate row total
            const rowId = $priceInput.closest('tr').attr('id')?.replace('qt_row_', '');
            if (rowId) calcQT(rowId);
        },
        error: function() {
            // Fallback: keep existing price
        }
    });
}

// ── Re-fetch prices for ALL rows when price list changes ──────────────────
function refreshAllPrices() {
    $('#qt-rows tr').each(function() {
        const rowId    = $(this).attr('id')?.replace('qt_row_', '');
        if (!rowId) return;
        const productId = $(`#qt_ps_${rowId}`).val();
        if (productId) {
            fetchProductPrice(productId, $(`#qt_row_${rowId} .qt-price`));
        }
    });
}

// Toggle customer name field
$('#customerSelect').on('change', function() {
    $('#customerNameWrap').toggle(!$(this).val());
});
$('#customerSelect').trigger('change');

// When price list changes → re-price all existing rows
$(document).on('change', '#priceListSelect', function() {
    refreshAllPrices();
});

function addQTRow() {
    rowCount++;
    const id = rowCount;
    const tr = `<tr id="qt_row_${id}">
        <td><select name="items[${id}][product_id]" class="form-select qt-product" id="qt_ps_${id}" required></select></td>
        <td><input type="number" name="items[${id}][quantity]" class="form-control qt-qty" value="1" min="0.001" step="0.001" oninput="calcQT(${id})" required></td>
        <td><input type="number" name="items[${id}][unit_price]" class="form-control qt-price" step="0.01" min="0" oninput="calcQT(${id})" required></td>
        <td><input type="number" name="items[${id}][discount_percent]" class="form-control qt-disc" value="0" min="0" max="100" step="0.1" oninput="calcQT(${id})"></td>
        <td><input type="text" class="form-control qt-sub" readonly value="0.00"></td>
        <td><button type="button" class="btn btn-sm btn-info text-white" onclick="$('#qt_row_${id}').remove();calcQTTotal()"><i class="bi bi-trash"></i></button></td>
    </tr>`;
    $('#qt-rows').append(tr);

    $(`#qt_ps_${id}`).select2({
        theme:'bootstrap-5', dir:'rtl', width:'100%', placeholder:'ابحث عن صنف…',
        allowClear:true, minimumInputLength:0,
        ajax:{
            url: SEARCH_URL,
            dataType: 'json',
            delay: 200,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: p => ({ q: p.term || '' }),
            processResults: data => ({
                results: data.map(d => ({ id: d.id, text: d.text }))
            }),
            cache: true,
        },
        language:{noResults:()=>'لا نتائج', searching:()=>'بحث…', inputTooShort:()=>'اكتب للبحث…'},
    });

    $(`#qt_ps_${id}`).on('select2:select', function(e) {
        // Fetch price respecting the selected price list
        fetchProductPrice(e.params.data.id, $(`#qt_row_${id} .qt-price`));
    });

    $(`#qt_ps_${id}`).on('select2:unselect', function() {
        $(`#qt_row_${id} .qt-price`).val('0').removeClass('text-primary fw-bold');
        calcQT(id);
    });
}

function calcQT(id) {
    const qty   = parseFloat($(`#qt_row_${id} .qt-qty`).val())   || 0;
    const price = parseFloat($(`#qt_row_${id} .qt-price`).val()) || 0;
    const disc  = parseFloat($(`#qt_row_${id} .qt-disc`).val())  || 0;
    $(`#qt_row_${id} .qt-sub`).val((qty * price * (1 - disc/100)).toFixed(2));
    calcQTTotal();
}
function calcQTTotal() {
    let t = 0;
    $('.qt-sub').each(function(){ t += parseFloat($(this).val()) || 0; });
    $('#qt-total').text(t.toFixed(2) + ' ' + CURRENCY);
}
$('#qt-form').on('submit', e => { if(!$('#qt-rows tr').length){ e.preventDefault(); alert('أضف صنفاً!'); }});
$(function(){ addQTRow(); });
</script>
@endsection
