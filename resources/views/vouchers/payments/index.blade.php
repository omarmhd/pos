@extends('layouts.app')
@section('page-title', 'سندات الصرف')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-arrow-up-circle text-danger me-2"></i>سندات الصرف (دفعيات)</h5>
        @can('vouchers.create')
        <a href="{{ route('vouchers.payments.create') }}" class="btn btn-danger btn-sm">
            <i class="bi bi-plus-circle me-1"></i> سند صرف جديد
        </a>
        @endcan
    </div>

    {{-- AJAX filter bar --}}
    <div class="card-body border-bottom py-2">
        <div id="filterForm" class="row g-2 align-items-end">
            @include('components.branch-filter', ['dtReload' => true])

            <div class="col-auto">
                <label class="form-label small mb-1"><i class="bi bi-calendar-event me-1"></i>من تاريخ</label>
                <input type="date" name="from" class="form-control form-control-sm" style="min-width:135px">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">إلى تاريخ</label>
                <input type="date" name="to" class="form-control form-control-sm" style="min-width:135px">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1"><i class="bi bi-credit-card me-1"></i>طريقة الصرف</label>
                <select name="method" class="form-select form-select-sm" style="min-width:140px">
                    <option value="">كل الطرق</option>
                    <option value="cash">نقدي</option>
                    <option value="bank">تحويل بنكي</option>
                    <option value="mobile_wallet">محفظة إلكترونية</option>
                </select>
            </div>
            <div class="col-auto d-flex align-items-end gap-1">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnApplyPv">
                    <i class="bi bi-funnel"></i> تصفية
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnClearPv">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <table id="paymentsTable" class="table table-hover" style="width:100%">
            <thead>
            <tr>
                <th>رقم السند</th>
                <th>التاريخ</th>
                <th>النوع</th>
                <th>الجهة</th>
                <th>الحساب المدين</th>
                <th>حساب النقدية</th>
                <th class="text-end">المبلغ</th>
                <th>الحالة</th>
                <th>بواسطة</th>
                <th>الإجراءات</th>
            </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function () {
    var table = $('#paymentsTable').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: false,
        ajax: {
            url: '{{ route("vouchers.payments.data") }}',
            dataSrc: 'data',
            data: function(d) { dtCollectFilters(d, '#filterForm'); }
        },
        order: [[1, 'desc']],
        columns: [
            { data: 'voucher_number' },
            { data: 'date_fmt' },
            { data: 'type_badge',       orderable: false },
            { data: 'party' },
            { data: 'account_name' },
            { data: 'cash_account_name' },
            { data: 'amount_fmt',       className: 'text-end' },
            { data: 'status',           orderable: false },
            { data: 'user_name' },
            { data: 'action',           orderable: false, searchable: false, className: 'text-center' },
        ],
    }));

    dtWireFilters(table, '#filterForm');

    $('#btnApplyPv').on('click', function() { table.ajax.reload(); });

    $('#btnClearPv').on('click', function() {
        $('#filterForm select, #filterForm input[type="date"]').val('');
        table.ajax.reload();
    });
});
</script>
@endsection
