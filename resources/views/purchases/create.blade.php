@extends('layouts.app')

@section('page-title', 'إضافة فاتورة شراء')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" rel="stylesheet">
<style>
    .select2-container { width: 100% !important; }
    .select2-results__option--create {
        color: #0d6efd;
        font-weight: 600;
    }
    .select2-results__option--create::before {
        content: '＋ ';
    }
</style>
@endpush

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-bag-plus"></i> إضافة فاتورة شراء جديدة</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('purchases.store') }}" method="POST" id="purchaseForm">
            @csrf
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">المورد <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                        <option value="">اختر المورد</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }} - {{ $supplier->company }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">رقم فاتورة المورد</label>
                    <input type="text" name="supplier_invoice_number"
                           class="form-control @error('supplier_invoice_number') is-invalid @enderror"
                           value="{{ old('supplier_invoice_number') }}"
                           placeholder="رقم الفاتورة الصادرة من المورد (اختياري)">
                    @error('supplier_invoice_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Warehouse selector: only visible when system has >1 warehouse --}}
            @if($warehouses->count() > 1)
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-archive text-success me-1"></i>
                        المخزن المستلِم
                        <span class="badge bg-success ms-1">{{ $warehouses->count() }} مخازن</span>
                    </label>
                    <select name="warehouse_id" class="form-select">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}"
                                {{ old('warehouse_id', $defaultWarehouseId) == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }}
                                @if($wh->branch) ({{ $wh->branch->name }}) @endif
                                @if($wh->is_default) ⭐ @endif
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">البضاعة ستُضاف لمخزون هذا المخزن</div>
                </div>
            </div>
            @else
            {{-- Single warehouse: submit silently --}}
            <input type="hidden" name="warehouse_id" value="{{ $defaultWarehouseId }}">
            @endif

            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label">حالة الدفع <span class="text-danger">*</span></label>
                    <select name="payment_status" class="form-select" id="paymentStatus" required>
                        <option value="unpaid">غير مدفوع</option>
                        <option value="partial">مدفوع جزئياً</option>
                        <option value="paid">مدفوع بالكامل</option>
                    </select>
                </div>
            </div>

            <!-- Products Section -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">المنتجات</h6>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addProductRow()">
                        <i class="bi bi-plus-circle"></i> إضافة منتج
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="productsTable" style="min-width:640px;">
                            <thead class="table-light">
                            <tr>
                                <th style="width:28%">المنتج</th>
                                <th style="width:10%;min-width:95px">الوحدة</th>
                                <th style="width:10%;min-width:90px">الكمية</th>
                                <th style="width:13%;min-width:110px">سعر الوحدة</th>
                                <th style="width:11%;min-width:100px">المجموع</th>
                                <th style="width:10%">رقم الدُّفعة</th>
                                <th style="width:10%">تاريخ الانتهاء</th>
                                <th style="width:6%"></th>
                            </tr>
                            </thead>
                            <tbody id="productRows"></tbody>
                            <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>الإجمالي:</strong></td>
                                <td colspan="2"><strong id="totalAmount">0.00 {{ $currency }}</strong></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-3">
                    <label class="form-label">ضريبة المدخلات ({{ $currency }})</label>
                    <input type="number" name="tax_amount" class="form-control"
                           id="taxAmount" value="0" step="0.01" min="0" oninput="calculateTotal()">
                    <div class="form-text">تُرحَّل لحساب "ضريبة مدخلات" (1260) ولا تدخل في تكلفة المخزون</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الإجمالي شامل الضريبة</label>
                    <input type="text" class="form-control" id="grandTotal" value="0.00 {{ $currency }}" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">المبلغ المدفوع <span class="text-danger">*</span></label>
                    <input type="number" name="paid_amount" class="form-control"
                           id="paidAmount" value="0" step="0.01" min="0" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">المبلغ المتبقي</label>
                    <input type="text" class="form-control" id="remainingAmount" value="0.00 {{ $currency }}" readonly>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-success">
                <i class="bi bi-save"></i> حفظ الفاتورة
            </button>
            <a href="{{ route('purchases.index') }}" class="btn btn-secondary">
                <i class="bi bi-x-circle"></i> إلغاء
            </a>
        </form>
    </div>
</div>

{{-- ════════════════════════════════════════════
     Modal: إضافة منتج جديد سريع
     ════════════════════════════════════════════ --}}
<div class="modal fade" id="quickProductModal" tabindex="-1" aria-labelledby="quickProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickProductModalLabel">
                    <i class="bi bi-box-seam"></i> إضافة منتج جديد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="quickProductError" class="alert alert-danger d-none"></div>
                <div class="mb-3">
                    <label class="form-label">اسم المنتج <span class="text-danger">*</span></label>
                    <input type="text" id="qp_name" class="form-control" placeholder="اسم المنتج">
                </div>
                <div class="mb-3">
                    <label class="form-label">الباركود</label>
                    <input type="text" id="qp_barcode" class="form-control" placeholder="اختياري">
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">سعر التكلفة <span class="text-danger">*</span></label>
                        <input type="number" id="qp_cost" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">سعر البيع <span class="text-danger">*</span></label>
                        <input type="number" id="qp_selling" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">الوحدة</label>
                    <select id="qp_unit" class="form-select">
                        @foreach(\App\Enums\ProductUnit::cases() as $unit)
                            <option value="{{ $unit->value }}"
                                {{ $unit->value === 'piece' ? 'selected' : '' }}>
                                {{ $unit->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="saveQuickProduct">
                    <i class="bi bi-save"></i> إضافة المنتج
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const SEARCH_URL       = "{{ route('purchases.products.search') }}";
    const QUICK_CREATE_URL = "{{ route('purchases.products.quick-create') }}";
    const CSRF             = "{{ csrf_token() }}";
    const CURRENCY         = "{{ $currency }}";

    let rowCount        = 0;
    let pendingRowId    = null; // row that triggered the quick-create modal
    let pendingTerm     = '';   // search term that triggered it

    // ── Add a new product row ────────────────────────────────────────────────
    function addProductRow() {
        rowCount++;
        const id = rowCount;

        const tr = `
        <tr id="row${id}">
            <td>
                <select name="items[${id}][product_id]"
                        class="form-select product-select2"
                        id="ps_${id}"
                        required></select>
            </td>
            <td>
                <select name="items[${id}][product_unit_id]"
                        class="form-select form-select-sm unit-select"
                        id="us_${id}" onchange="onUnitChange(${id})">
                    <option value="">رئيسية</option>
                </select>
            </td>
            <td>
                <input type="number" name="items[${id}][quantity]"
                       class="form-control quantity-input"
                       value="1" min="0.001" step="0.001"
                       placeholder="الكمية"
                       oninput="calculateRow(${id})" required>
            </td>
            <td>
                <input type="number" name="items[${id}][unit_price]"
                       class="form-control unit-price"
                       step="0.01" min="0"
                       placeholder="السعر"
                       oninput="calculateRow(${id})" required>
            </td>
            <td>
                <input type="text" class="form-control row-total" readonly value="0.00">
            </td>
            <td>
                <input type="text" name="items[${id}][lot_number]"
                       class="form-control form-control-sm"
                       placeholder="رقم الدُّفعة" title="Lot / Batch Number">
            </td>
            <td>
                <input type="date" name="items[${id}][expiry_date]"
                       class="form-control form-control-sm"
                       title="تاريخ الانتهاء">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(${id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>`;

        $('#productRows').append(tr);
        initSelect2(id);
    }

    // ── Initialise Select2 on a row ──────────────────────────────────────────
    function initSelect2(id) {
        const $sel = $(`#ps_${id}`);

        $sel.select2({
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
                data: function(params) {
                    return {
                        q: params.term || '',
                        supplier_id: $('select[name=supplier_id]').val() || ''   // تكلفة قائمة المورد
                    };
                },
                processResults: function(data, params) {
                    var term = (params.term || '').trim();
                    var items = [];
                    for (var i = 0; i < data.length; i++) {
                        items.push({ id: data[i].id, text: data[i].text, cost_price: data[i].cost_price,
                                     from_list: data[i].from_list, units: data[i].units || [] });
                    }
                    if (term.length > 0) {
                        items.push({ id: '__new__', text: '＋ إضافة منتج جديد: ' + term, isNew: true, term: term });
                    }
                    return { results: items };
                },
                cache: true,
            },
            templateResult: function(item) {
                if (item.isNew) {
                    return $('<span class="text-primary fw-semibold"></span>').text(item.text);
                }
                return item.text;
            },
            language: {
                noResults:     function() { return 'لا توجد نتائج'; },
                searching:     function() { return 'جاري البحث…'; },
                inputTooShort: function() { return 'اكتب للبحث…'; },
                loadingMore:   function() { return 'جاري التحميل…'; },
            },
        });

        // Handle selection
        $sel.on('select2:select', function (e) {
            const data = e.params.data;

            if (data.isNew) {
                // Reset selection, open modal
                $(this).val(null).trigger('change');
                pendingRowId = id;
                pendingTerm  = data.term;
                openQuickModal(data.term);
                return;
            }

            // Set cost price in the unit price field
            $(`#row${id} .unit-price`).val(data.cost_price || 0);
            if (data.from_list) {
                $(`#row${id} .unit-price`).addClass('border-success').attr('title', 'سعر من قائمة أسعار المورد');
            } else {
                $(`#row${id} .unit-price`).removeClass('border-success').removeAttr('title');
            }

            // تعبئة الوحدات الإضافية (كرتون/دستة...) لهذا الصنف
            rowData[id] = { baseCost: data.cost_price || 0, units: data.units || [] };
            const $unitSel = $(`#us_${id}`);
            $unitSel.empty().append('<option value="">رئيسية</option>');
            (data.units || []).forEach(u => {
                $unitSel.append(`<option value="${u.id}" data-factor="${u.factor}" data-cost="${u.cost}">${u.name} (×${u.factor})</option>`);
            });

            calculateRow(id);
        });

        $sel.on('select2:clear', function () {
            $(`#row${id} .unit-price`).val('');
            $(`#us_${id}`).empty().append('<option value="">رئيسية</option>');
            rowData[id] = null;
            calculateRow(id);
        });
    }

    // ── تغيير وحدة السطر: السعر = تكلفة الوحدة المختارة ─────────────────────
    const rowData = {};
    function onUnitChange(id) {
        const $opt = $(`#us_${id} option:selected`);
        const data = rowData[id];
        if (!$opt.val()) {
            // العودة للوحدة الرئيسية
            $(`#row${id} .unit-price`).val(data ? data.baseCost : '');
        } else {
            $(`#row${id} .unit-price`).val(parseFloat($opt.data('cost')) || 0);
        }
        calculateRow(id);
    }

    // ── Row math ─────────────────────────────────────────────────────────────
    function calculateRow(id) {
        const qty   = parseFloat($(`#row${id} .quantity-input`).val()) || 0;
        const price = parseFloat($(`#row${id} .unit-price`).val())     || 0;
        $(`#row${id} .row-total`).val((qty * price).toFixed(2));
        calculateTotal();
    }

    function removeRow(id) {
        $(`#row${id}`).remove();
        calculateTotal();
    }

    function calculateTotal() {
        let total = 0;
        $('.row-total').each(function () { total += parseFloat($(this).val()) || 0; });
        $('#totalAmount').text(total.toFixed(2) + ' ' + CURRENCY);

        // الإجمالي شامل ضريبة المدخلات
        const tax   = parseFloat($('#taxAmount').val()) || 0;
        const grand = total + tax;
        $('#grandTotal').val(grand.toFixed(2) + ' ' + CURRENCY);

        if ($('#paymentStatus').val() === 'paid') {
            $('#paidAmount').val(grand.toFixed(2));
        }
        updateRemaining();
    }

    function updateRemaining() {
        const total = parseFloat($('#totalAmount').text()) || 0;
        const tax   = parseFloat($('#taxAmount').val())    || 0;
        const paid  = parseFloat($('#paidAmount').val())   || 0;
        $('#remainingAmount').val((total + tax - paid).toFixed(2) + ' ' + CURRENCY);
    }

    $('#paidAmount').on('input', updateRemaining);

    $('#paymentStatus').on('change', function () {
        const total = (parseFloat($('#totalAmount').text()) || 0) + (parseFloat($('#taxAmount').val()) || 0);
        if ($(this).val() === 'paid')   $('#paidAmount').val(total.toFixed(2));
        if ($(this).val() === 'unpaid') $('#paidAmount').val('0');
        updateRemaining();
    });

    // ── Quick-create product modal ────────────────────────────────────────────
    function openQuickModal(term) {
        $('#qp_name').val(term);
        $('#qp_barcode').val('');
        $('#qp_cost').val('');
        $('#qp_selling').val('');
        $('#qp_unit').val('');
        $('#quickProductError').addClass('d-none').text('');
        new bootstrap.Modal(document.getElementById('quickProductModal')).show();
    }

    $('#saveQuickProduct').on('click', function () {
        const name    = $('#qp_name').val().trim();
        const barcode = $('#qp_barcode').val().trim();
        const cost    = $('#qp_cost').val().trim();
        const selling = $('#qp_selling').val().trim();
        const unit    = $('#qp_unit').val().trim();

        if (!name || !cost || !selling) {
            $('#quickProductError').removeClass('d-none').text('يرجى تعبئة الاسم وسعر التكلفة وسعر البيع.');
            return;
        }

        const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> جاري الحفظ…');

        $.ajax({
            url: QUICK_CREATE_URL,
            method: 'POST',
            data: {
                _token: CSRF,
                name, barcode, cost_price: cost, selling_price: selling, unit,
            },
            success(product) {
                // Inject the new product as a selected option in the pending row
                const option = new Option(product.text, product.id, true, true);
                $(option).data('cost_price', product.cost_price);
                $(`#ps_${pendingRowId}`).append(option).trigger('change');

                // Fill price
                $(`#row${pendingRowId} .unit-price`).val(product.cost_price || 0);
                calculateRow(pendingRowId);

                bootstrap.Modal.getInstance(document.getElementById('quickProductModal')).hide();
                pendingRowId = null;
                pendingTerm  = '';
            },
            error(xhr) {
                let msg = 'حدث خطأ أثناء إضافة المنتج.';
                if (xhr.responseJSON?.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join(' | ');
                }
                $('#quickProductError').removeClass('d-none').text(msg);
            },
            complete() {
                $btn.prop('disabled', false).html('<i class="bi bi-save"></i> إضافة المنتج');
            },
        });
    });

    // ── Form validation ───────────────────────────────────────────────────────
    $('#purchaseForm').on('submit', function (e) {
        if ($('#productRows tr').length === 0) {
            e.preventDefault();
            alert('يجب إضافة منتج واحد على الأقل!');
        }
    });

    // ── Init: add first row ───────────────────────────────────────────────────
    $(document).ready(function () {
        addProductRow();
    });
</script>
@endsection
