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
const SEARCH_URL  = "{{ route('purchases.products.search') }}";
const LEVEL_URL   = "{{ route('stock-transfers.level') }}";
let rowCount = 0;

function getFromWarehouseId() {
    return parseInt($('#fromWH').val()) || 0;
}

function addTrfRow() {
    rowCount++;
    const id = rowCount;
    const tr = `
    <tr id="trf_row_${id}">
        <td>
            <select name="items[${id}][product_id]" class="form-select trf-product"
                    id="trf_ps_${id}" required></select>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text trf-available text-muted" id="trf_avail_${id}">—</span>
                <span class="input-group-text small text-muted">متاح</span>
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

    $(`#trf_ps_${id}`).select2({
        theme: 'bootstrap-5', dir: 'rtl', width: '100%',
        placeholder: 'ابحث عن صنف…', allowClear: true, minimumInputLength: 0,
        ajax: {
            url: SEARCH_URL, dataType: 'json', delay: 200,
            data: p => ({ q: p.term || '' }),
            processResults: data => ({ results: data.map(d => ({ id: d.id, text: d.text })) }),
            cache: true,
        },
        language: { noResults: () => 'لا نتائج', searching: () => 'بحث…', inputTooShort: () => 'اكتب…' },
    });

    $(`#trf_ps_${id}`).on('select2:select', function(e) {
        const productId = e.params.data.id;
        const fromWH    = getFromWarehouseId();
        if (fromWH && productId) {
            $.get(LEVEL_URL, { warehouse_id: fromWH, product_id: productId }, function(data) {
                $(`#trf_avail_${id}`).text(parseFloat(data.quantity).toFixed(2));
            });
        }
    });
}

// When from-warehouse changes, refresh all available quantities
$('#fromWH').on('change', function() {
    const fromWH = parseInt($(this).val()) || 0;
    if (!fromWH) return;
    $('#trf-rows tr').each(function() {
        const rowId = $(this).attr('id')?.replace('trf_row_', '');
        if (!rowId) return;
        const productId = $(`#trf_ps_${rowId}`).val();
        if (productId) {
            $.get(LEVEL_URL, { warehouse_id: fromWH, product_id: productId }, function(data) {
                $(`#trf_avail_${rowId}`).text(parseFloat(data.quantity).toFixed(2));
            });
        }
    });
});

$('#transfer-form').on('submit', function(e) {
    if (!$('#trf-rows tr').length) { e.preventDefault(); alert('أضف صنفاً واحداً!'); }
    if ($('#fromWH').val() === $('#toWH').val()) { e.preventDefault(); alert('المخزن المصدر والوجهة لا يمكن أن يكونا نفس المخزن!'); }
});

$(function() { addTrfRow(); });
</script>
@endsection
