@extends('layouts.app')
@section('page-title', 'إضافة مرتجع مبيعات')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" rel="stylesheet">
<style>
    .select2-container { width: 100% !important; }
</style>
@endpush

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-arrow-return-left text-danger me-2"></i>إضافة مرتجع مبيعات جديد</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('sale-returns.store') }}" method="POST" id="returnForm">
            @csrf

            {{-- Row 1: Date + Refund method --}}
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">تاريخ المرتجع <span class="text-danger">*</span></label>
                    <input type="date" name="return_date" class="form-control"
                           value="{{ old('return_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">طريقة الاسترداد <span class="text-danger">*</span></label>
                    <select name="refund_method" id="refundMethod" class="form-select" required>
                        <option value="cash"        {{ old('refund_method','cash') === 'cash'        ? 'selected':'' }}>نقدي</option>
                        <option value="bank"        {{ old('refund_method')        === 'bank'        ? 'selected':'' }}>بنكي</option>
                        <option value="credit_note" {{ old('refund_method')        === 'credit_note' ? 'selected':'' }}>إشعار دائن (تخفيض مديونية)</option>
                    </select>
                </div>
                <div class="col-md-4" id="customerWrap">
                    <label class="form-label">العميل
                        <span id="customerRequired" class="text-danger d-none">*</span>
                        <small class="text-muted" id="customerHint">(إجباري للإشعار الدائن)</small>
                    </label>
                    <select name="customer_id" id="customerSelect" class="form-select">
                        <option value="">— لا يوجد عميل —</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected':'' }}>
                                {{ $c->name }}{{ $c->phone ? ' — '.$c->phone : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Row 2: Original sale reference (optional) --}}
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label">رقم الفاتورة الأصلية
                        <small class="text-muted">(اختياري — للإسناد فقط)</small>
                    </label>
                    <input type="text" name="sale_ref_text"
                           class="form-control"
                           placeholder="مثال: INV-20260601-0001"
                           value="{{ old('sale_ref_text') }}">
                    {{-- Hidden: resolved sale_id via AJAX --}}
                    <input type="hidden" name="sale_id" id="saleIdHidden" value="{{ old('sale_id') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="1"
                              placeholder="سبب الإرجاع أو ملاحظات أخرى">{{ old('notes') }}</textarea>
                </div>
            </div>

            {{-- Items table --}}
            <div class="card mb-4 border-danger">
                <div class="card-header bg-danger bg-opacity-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-danger"><i class="bi bi-box-arrow-in-right me-1"></i>الأصناف المرتجعة</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="addReturnRow()">
                        <i class="bi bi-plus-circle"></i> إضافة صنف
                    </button>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="returnItemsTable" style="min-width:580px;">
                            <thead class="table-light">
                            <tr>
                                <th style="width:42%">المنتج</th>
                                <th style="width:16%;min-width:100px">الكمية</th>
                                <th style="width:19%;min-width:120px">سعر الوحدة</th>
                                <th style="width:18%;min-width:110px">المجموع</th>
                                <th style="width:5%"></th>
                            </tr>
                            </thead>
                            <tbody id="returnRows"></tbody>
                            <tfoot>
                            <tr class="table-warning">
                                <td colspan="3" class="text-end fw-bold">إجمالي المرتجع:</td>
                                <td colspan="2" class="fw-bold" id="returnTotal">0.00 {{ $currency }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-danger">
                <i class="bi bi-save me-1"></i> حفظ المرتجع وترحيل القيد
            </button>
            <a href="{{ route('sale-returns.index') }}" class="btn btn-secondary">
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

    // ── Refund method toggle ─────────────────────────────────────────────────
    function toggleCustomerRequired() {
        const isCreditNote = $('#refundMethod').val() === 'credit_note';
        $('#customerRequired').toggleClass('d-none', !isCreditNote);
        $('#customerHint').toggleClass('d-none', isCreditNote);
        $('#customerSelect').prop('required', isCreditNote);
    }

    $('#refundMethod').on('change', toggleCustomerRequired);
    toggleCustomerRequired();

    // ── Add return item row ──────────────────────────────────────────────────
    function addReturnRow() {
        rowCount++;
        const id = rowCount;

        const tr = `
        <tr id="rrow${id}">
            <td>
                <select name="items[${id}][product_id]"
                        class="form-select ret-product-select"
                        id="rps_${id}" required></select>
            </td>
            <td>
                <input type="number" name="items[${id}][quantity]"
                       class="form-control ret-qty"
                       value="1" min="0.001" step="0.001"
                       placeholder="الكمية"
                       oninput="calcRetRow(${id})" required>
            </td>
            <td>
                <input type="number" name="items[${id}][unit_price]"
                       class="form-control ret-price"
                       step="0.01" min="0"
                       placeholder="سعر البيع"
                       oninput="calcRetRow(${id})" required>
            </td>
            <td>
                <input type="text" class="form-control ret-total" readonly value="0.00">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeRetRow(${id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>`;

        $('#returnRows').append(tr);
        initRetSelect2(id);
    }

    function initRetSelect2(id) {
        $(`#rps_${id}`).select2({
            theme: 'bootstrap-5',
            dir: 'rtl',
            width: '100%',
            placeholder: 'ابحث باسم المنتج أو الباركود…',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: SEARCH_URL,
                dataType: 'json',
                delay: 200,
                data: function(p) { return { q: p.term || '' }; },
                processResults: function(data) {
                    return { results: data.map(d => ({ id: d.id, text: d.text, selling_price: d.selling_price || 0, cost_price: d.cost_price || 0 })) };
                },
                cache: true,
            },
            language: {
                noResults:     function() { return 'لا توجد نتائج'; },
                searching:     function() { return 'جاري البحث…'; },
                inputTooShort: function() { return 'اكتب للبحث…'; },
            },
        });

        $(`#rps_${id}`).on('select2:select', function(e) {
            const d = e.params.data;
            $(`#rrow${id} .ret-price`).val(d.selling_price || d.cost_price || 0);
            calcRetRow(id);
        });

        $(`#rps_${id}`).on('select2:clear', function() {
            $(`#rrow${id} .ret-price`).val('');
            calcRetRow(id);
        });
    }

    function calcRetRow(id) {
        const qty   = parseFloat($(`#rrow${id} .ret-qty`).val())   || 0;
        const price = parseFloat($(`#rrow${id} .ret-price`).val()) || 0;
        $(`#rrow${id} .ret-total`).val((qty * price).toFixed(2));
        calcReturnTotal();
    }

    function removeRetRow(id) {
        $(`#rrow${id}`).remove();
        calcReturnTotal();
    }

    function calcReturnTotal() {
        let total = 0;
        $('.ret-total').each(function() { total += parseFloat($(this).val()) || 0; });
        $('#returnTotal').text(total.toFixed(2) + ' ' + CURRENCY);
    }

    // ── Form submit validation ───────────────────────────────────────────────
    $('#returnForm').on('submit', function(e) {
        if ($('#returnRows tr').length === 0) {
            e.preventDefault();
            alert('يجب إضافة صنف مرتجع واحد على الأقل!');
        }
    });

    // ── Init first row ───────────────────────────────────────────────────────
    $(function() { addReturnRow(); });
</script>
@endsection
