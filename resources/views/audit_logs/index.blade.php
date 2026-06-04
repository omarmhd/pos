@extends('layouts.app')
@section('page-title', 'سجل التدقيق')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-shield-check text-success me-2"></i>سجل التدقيق الشامل
        </h5>
        <span class="badge bg-info">كل العمليات المالية مسجَّلة تلقائياً</span>
    </div>

    {{-- AJAX filters --}}
    <div class="card-body border-bottom py-2">
        <div id="filterForm" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1"><i class="bi bi-layers me-1"></i>نوع السجل</label>
                <select name="model" class="form-select form-select-sm" style="min-width:160px">
                    <option value="">كل الأنواع</option>
                    <option value="Sale">فواتير البيع</option>
                    <option value="Purchase">فواتير الشراء</option>
                    <option value="JournalEntry">القيود اليومية</option>
                    <option value="CustomerPayment">دفعات العملاء</option>
                    <option value="SupplierPayment">دفعات الموردين</option>
                    <option value="ReceiptVoucher">سندات القبض</option>
                    <option value="PaymentVoucher">سندات الصرف</option>
                    <option value="Customer">بيانات العملاء</option>
                    <option value="Supplier">بيانات الموردين</option>
                    <option value="InventoryAdjustment">تعديلات المخزون</option>
                    <option value="FixedAsset">الأصول الثابتة</option>
                    <option value="CashShift">الورديات النقدية</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1"><i class="bi bi-funnel me-1"></i>نوع الحدث</label>
                <select name="action" class="form-select form-select-sm" style="min-width:120px">
                    <option value="">الكل</option>
                    <option value="created">إنشاء</option>
                    <option value="updated">تعديل</option>
                    <option value="deleted">حذف</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1"><i class="bi bi-calendar-event me-1"></i>من تاريخ</label>
                <input type="date" name="from" class="form-control form-control-sm" style="min-width:135px">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">إلى تاريخ</label>
                <input type="date" name="to" class="form-control form-control-sm" style="min-width:135px">
            </div>
            <div class="col-auto d-flex align-items-end gap-1">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnApplyAudit">
                    <i class="bi bi-search"></i>
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnClearAudit">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="audit-table" class="table table-hover table-sm align-middle" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>الوقت</th>
                        <th>المستخدم</th>
                        <th>الحدث</th>
                        <th>النوع</th>
                        <th>رقم السجل</th>
                        <th>التفاصيل (قبل → بعد)</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

@push('styles')
<style>
#audit-table td { vertical-align: middle; }
#audit-table pre { max-height: 80px; overflow: auto; }
</style>
@endpush
@endsection

@section('scripts')
<script>
$(function () {
    var table = $('#audit-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("audit.logs.index") }}',
            data: function(d) { dtCollectFilters(d, '#filterForm'); }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'id',           name: 'id',           width: '55px' },
            { data: 'time',         name: 'created_at',   width: '130px' },
            { data: 'user_name',    name: 'user_name',    width: '110px' },
            { data: 'action_badge', name: 'action',       orderable: true,  searchable: false, width: '80px' },
            { data: 'model_label',  name: 'model_label',  orderable: false, width: '110px' },
            { data: 'record_link',  name: 'auditable_id', orderable: true,  searchable: false, width: '70px' },
            { data: 'diff_html',    name: 'diff',         orderable: false, searchable: false },
        ]
    }));

    dtWireFilters(table, '#filterForm');
    $('#btnApplyAudit').on('click', function() { table.ajax.reload(); });
    $('#btnClearAudit').on('click', function() {
        $('#filterForm select, #filterForm input').val('');
        table.ajax.reload();
    });
});
</script>
@endsection
