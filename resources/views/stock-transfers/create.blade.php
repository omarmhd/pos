@extends('layouts.app')
@section('page-title', 'تحويل مخزون جديد')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" rel="stylesheet">
<style>.select2-container{width:100%!important}</style>
@endpush

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-arrow-left-right text-warning me-2"></i>
            إنشاء تحويل مخزون داخلي
        </h5>
    </div>
    <div class="card-body">

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-warning bg-opacity-10 border-warning h-100 p-3 text-center">
                    <div class="fw-bold">الحالة الشائعة</div>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-box-seam me-1"></i>مخزن خلفي (main)
                        <i class="bi bi-arrow-left mx-2"></i>
                        <i class="bi bi-shop me-1"></i>معرض/رفوف (floor)
                    </div>
                    <div class="text-warning fw-bold mt-1">إعادة تعبئة الرفوف</div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="alert alert-light border small mb-0">
                    <strong>ملاحظة محاسبية:</strong> التحويل الداخلي <strong>لا يُنشئ قيداً محاسبياً</strong>.<br>
                    حساب المخزون (1300) لا يتغير — فقط توزيع الكميات بين المخازن يتغير.<br>
                    يُسجَّل سجل حركة (StockMovement) لأغراض التدقيق.
                </div>
            </div>
        </div>

        <form action="{{ route('stock-transfers.store') }}" method="POST" id="transfer-form">
            @csrf

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">
                        <i class="bi bi-box-arrow-right text-danger me-1"></i>
                        من مخزن (المصدر) <span class="text-danger">*</span>
                    </label>
                    <select name="from_warehouse_id" id="fromWH" class="form-select" required>
                        <option value="">اختر مخزن المصدر</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('from_warehouse_id') == $wh->id ? 'selected':'' }}>
                                {{ $wh->name }} @if($wh->branch) ({{ $wh->branch->name }}) @endif
                                — [{{ ['floor'=>'معرض','main'=>'رئيسي','returns'=>'مرتجعات'][$wh->type] ?? $wh->type }}]
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">
                        <i class="bi bi-box-arrow-in-right text-success me-1"></i>
                        إلى مخزن (الوجهة) <span class="text-danger">*</span>
                    </label>
                    <select name="to_warehouse_id" id="toWH" class="form-select" required>
                        <option value="">اختر مخزن الوجهة</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('to_warehouse_id') == $wh->id ? 'selected':'' }}>
                                {{ $wh->name }} @if($wh->branch) ({{ $wh->branch->name }}) @endif
                                — [{{ ['floor'=>'معرض','main'=>'رئيسي','returns'=>'مرتجعات'][$wh->type] ?? $wh->type }}]
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">تاريخ التحويل <span class="text-danger">*</span></label>
                    <input type="date" name="transfer_date" class="form-control"
                           value="{{ old('transfer_date', date('Y-m-d')) }}" required>
                </div>
            </div>

            {{-- Items --}}
            <div class="card mb-4 border-warning">
                <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-warning">
                        <i class="bi bi-list-check me-1"></i>الأصناف المُحوَّلة
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="addTrfRow()">
                        <i class="bi bi-plus-circle"></i> إضافة صنف
                    </button>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" style="min-width:600px;">
                            <thead class="table-light">
                            <tr>
                                <th style="width:40%">الصنف</th>
                                <th style="width:20%;min-width:120px">رصيد المصدر</th>
                                <th style="width:20%;min-width:120px">الكمية المُحوَّلة</th>
                                <th style="width:5%"></th>
                            </tr>
                            </thead>
                            <tbody id="trf-rows"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"
                          placeholder="سبب التحويل، رقم أمر التعبئة...">{{ old('notes') }}</textarea>
            </div>

            <button type="submit" class="btn btn-warning">
                <i class="bi bi-save me-1"></i> حفظ طلب التحويل
            </button>
            <a href="{{ route('stock-transfers.index') }}" class="btn btn-secondary">إلغاء</a>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const SEARCH_URL = "{{ route('purchases.products.search') }}";

// ── Pre-loaded stock levels: { warehouseId: { productId: qty } } ─────────
// No AJAX needed — data is embedded directly in the page
const STOCK_DATA = @json($stockLevels);

function getStockLevel(warehouseId, productId) {
    if (!warehouseId || !productId) return null;
    const wh = STOCK_DATA[warehouseId];
    if (!wh) return 0;
    const qty = wh[productId];
    return qty !== undefined ? parseFloat(qty) : 0;
}

// ── Show balance in the span ──────────────────────────────────────────────
function showLevel($span, warehouseId, productId) {
    if (!warehouseId) {
        $span.text('—').attr('class', 'badge bg-light text-dark border trf-available text-muted fs-6');
        return;
    }
    if (!productId) {
        $span.text('—').attr('class', 'badge bg-light text-dark border trf-available text-muted fs-6');
        return;
    }
    const qty = getStockLevel(warehouseId, productId);
    if (qty === null) {
        $span.text('—').attr('class', 'badge bg-light text-dark border trf-available text-muted fs-6');
        return;
    }
    if (qty > 0) {
        $span.text(qty.toFixed(2)).attr('class', 'badge bg-success trf-available fs-6 fw-bold');
    } else {
        $span.text('0').attr('class', 'badge bg-danger trf-available fs-6 fw-bold');
    }
}

// ── Refresh all rows when warehouse changes ───────────────────────────────
function refreshAllLevels() {
    const fromWH = parseInt($('#fromWH').val()) || 0;
    $('#trf-rows tr').each(function() {
        const rowId    = $(this).attr('id')?.replace('trf_row_', '');
        if (!rowId) return;
        const productId = parseInt($(`#trf_ps_${rowId}`).val()) || 0;
        showLevel($(`#trf_avail_${rowId}`), fromWH, productId);
    });
}

// ── Add a new row ─────────────────────────────────────────────────────────
function addTrfRow() {
    rowCount++;
    const id = rowCount;
    const tr = `
    <tr id="trf_row_${id}">
        <td>
            <select name="items[${id}][product_id]"
                    class="form-select trf-product"
                    id="trf_ps_${id}" required></select>
        </td>
        <td>
            <div class="d-flex align-items-center gap-1">
                <span class="badge bg-light text-dark border trf-available text-muted fs-6"
                      id="trf_avail_${id}">—</span>
                <small class="text-muted">متاح</small>
            </div>
        </td>
        <td>
            <input type="number" name="items[${id}][quantity]"
                   class="form-control form-control-sm trf-qty"
                   value="1" min="0.001" step="0.001" required>
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-warning"
                    onclick="$('#trf_row_${id}').remove()">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>`;

    $('#trf-rows').append(tr);

    // Initialize Select2 on the new product select
    $(`#trf_ps_${id}`).select2({
        theme: 'bootstrap-5',
        dir: 'rtl',
        width: '100%',
        placeholder: 'ابحث عن صنف…',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: SEARCH_URL,
            dataType: 'json',
            delay: 200,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: function(params) { return { q: params.term || '' }; },
            processResults: function(data) {
                return {
                    results: data.map(function(d) {
                        return { id: d.id, text: d.text };
                    })
                };
            },
            cache: true,
        },
        language: {
            noResults:     function() { return 'لا توجد نتائج'; },
            searching:     function() { return 'جاري البحث…'; },
            inputTooShort: function() { return 'اكتب للبحث…'; },
        },
    });

    // When a product is selected → show balance instantly from pre-loaded data
    $(`#trf_ps_${id}`).on('select2:select', function(e) {
        const productId = parseInt(e.params.data.id) || 0;
        const fromWH    = parseInt($('#fromWH').val()) || 0;

        if (!fromWH) {
            $('#fromWH').addClass('is-invalid');
            setTimeout(function() { $('#fromWH').removeClass('is-invalid'); }, 2000);
        }

        showLevel($(`#trf_avail_${id}`), fromWH, productId);
    });

    // When product cleared → reset display
    $(`#trf_ps_${id}`).on('select2:unselect', function() {
        $(`#trf_avail_${id}`).text('—').attr('class', 'badge bg-light text-dark border trf-available text-muted fs-6');
    });
}

// ── When from-warehouse changes → refresh all balances immediately ─────────
$(document).on('change', '#fromWH', function() {
    refreshAllLevels();
});

// ── Form submit validation ─────────────────────────────────────────────────
$('#transfer-form').on('submit', function(e) {
    if (!$('#trf-rows tr').length) {
        e.preventDefault();
        alert('أضف صنفاً واحداً على الأقل!');
        return;
    }
    if ($('#fromWH').val() === $('#toWH').val() && $('#fromWH').val() !== '') {
        e.preventDefault();
        alert('المخزن المصدر والوجهة لا يمكن أن يكونا نفس المخزن!');
    }
});

// ── Init first row on page load ────────────────────────────────────────────
$(function() { addTrfRow(); });
</script>
@endsection
