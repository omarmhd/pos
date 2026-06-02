@extends('layouts.app')
@section('page-title', 'إضافة مرتجع مشتريات')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" rel="stylesheet">
<style>.select2-container { width: 100% !important; }</style>
@endpush

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-arrow-return-right text-warning me-2"></i>إضافة مرتجع مشتريات جديد</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('purchase-returns.store') }}" method="POST" id="purchaseReturnForm">
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
                <div class="col-md-4">
                    <label class="form-label">تاريخ المرتجع <span class="text-danger">*</span></label>
                    <input type="date" name="return_date" class="form-control"
                           value="{{ old('return_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">طريقة الاسترداد <span class="text-danger">*</span></label>
                    <select name="refund_method" class="form-select" required>
                        <option value="ap_deduction" {{ old('refund_method','ap_deduction') === 'ap_deduction' ? 'selected':'' }}>
                            خصم من ذمة المورد (الأكثر شيوعاً)
                        </option>
                        <option value="cash" {{ old('refund_method') === 'cash' ? 'selected':'' }}>استرداد نقدي</option>
                        <option value="bank" {{ old('refund_method') === 'bank' ? 'selected':'' }}>استرداد بنكي</option>
                    </select>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label">رقم فاتورة الشراء الأصلية
                        <small class="text-muted">(اختياري — للإسناد فقط)</small>
                    </label>
                    <input type="text" name="purchase_ref_text" class="form-control"
                           placeholder="مثال: PUR-20260601-0001"
                           value="{{ old('purchase_ref_text') }}">
                    <input type="hidden" name="purchase_id" value="{{ old('purchase_id') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="1"
                              placeholder="سبب الإرجاع">{{ old('notes') }}</textarea>
                </div>
            </div>

            {{-- Items table --}}
            <div class="card mb-4 border-warning">
                <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-warning"><i class="bi bi-box-arrow-up-right me-1"></i>الأصناف المرتجعة للمورد</h6>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="addRetRow()">
                        <i class="bi bi-plus-circle"></i> إضافة صنف
                    </button>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" style="min-width:580px;">
                            <thead class="table-light">
                            <tr>
                                <th style="width:42%">المنتج</th>
                                <th style="width:16%;min-width:100px">الكمية</th>
                                <th style="width:19%;min-width:120px">سعر الوحدة</th>
                                <th style="width:18%;min-width:110px">المجموع</th>
                                <th style="width:5%"></th>
                            </tr>
                            </thead>
                            <tbody id="retRows"></tbody>
                            <tfoot>
                            <tr class="table-warning">
                                <td colspan="3" class="text-end fw-bold">إجمالي المرتجع:</td>
                                <td colspan="2" class="fw-bold" id="retTotal">0.00 {{ $currency }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-warning">
                <i class="bi bi-save me-1"></i> حفظ المرتجع وترحيل القيد
            </button>
            <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary">
                <i class="bi bi-x-circle"></i> إلغاء
            </a>
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

    function addRetRow() {
        rowCount++;
        const id = rowCount;
        const tr = `
        <tr id="pr_row${id}">
            <td>
                <select name="items[${id}][product_id]"
                        class="form-select pr-product-select"
                        id="pr_ps_${id}" required></select>
            </td>
            <td>
                <input type="number" name="items[${id}][quantity]"
                       class="form-control pr-qty"
                       value="1" min="0.001" step="0.001"
                       placeholder="الكمية"
                       oninput="calcPrRow(${id})" required>
            </td>
            <td>
                <input type="number" name="items[${id}][unit_price]"
                       class="form-control pr-price"
                       step="0.01" min="0"
                       placeholder="سعر الشراء"
                       oninput="calcPrRow(${id})" required>
            </td>
            <td>
                <input type="text" class="form-control pr-total" readonly value="0.00">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-warning" onclick="removePrRow(${id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>`;
        $('#retRows').append(tr);
        initPrSelect2(id);
    }

    function initPrSelect2(id) {
        $(`#pr_ps_${id}`).select2({
            theme: 'bootstrap-5', dir: 'rtl', width: '100%',
            placeholder: 'ابحث باسم المنتج أو الباركود…',
            allowClear: true, minimumInputLength: 0,
            ajax: {
                url: SEARCH_URL, dataType: 'json', delay: 200,
                data: p => ({ q: p.term || '' }),
                processResults: data => ({
                    results: data.map(d => ({
                        id: d.id, text: d.text,
                        cost_price: d.cost_price || 0
                    }))
                }),
                cache: true,
            },
            language: {
                noResults:     () => 'لا توجد نتائج',
                searching:     () => 'جاري البحث…',
                inputTooShort: () => 'اكتب للبحث…',
            },
        });

        $(`#pr_ps_${id}`).on('select2:select', function(e) {
            // Pre-fill with cost_price — this is what we originally paid
            $(`#pr_row${id} .pr-price`).val(e.params.data.cost_price || 0);
            calcPrRow(id);
        });
        $(`#pr_ps_${id}`).on('select2:clear', function() {
            $(`#pr_row${id} .pr-price`).val('');
            calcPrRow(id);
        });
    }

    function calcPrRow(id) {
        const qty   = parseFloat($(`#pr_row${id} .pr-qty`).val())   || 0;
        const price = parseFloat($(`#pr_row${id} .pr-price`).val()) || 0;
        $(`#pr_row${id} .pr-total`).val((qty * price).toFixed(2));
        calcPrTotal();
    }

    function removePrRow(id) {
        $(`#pr_row${id}`).remove();
        calcPrTotal();
    }

    function calcPrTotal() {
        let t = 0;
        $('.pr-total').each(function() { t += parseFloat($(this).val()) || 0; });
        $('#retTotal').text(t.toFixed(2) + ' ' + CURRENCY);
    }

    $('#purchaseReturnForm').on('submit', function(e) {
        if ($('#retRows tr').length === 0) {
            e.preventDefault();
            alert('يجب إضافة صنف مرتجع واحد على الأقل!');
        }
    });

    $(function() { addRetRow(); });
</script>
@endsection
