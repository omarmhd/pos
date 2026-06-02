@extends('layouts.app')
@section('page-title', 'سندات القبض')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-arrow-down-circle text-success me-2"></i>سندات القبض</h5>
        @can('vouchers.create')
        <a href="{{ route('vouchers.receipts.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i> سند قبض جديد
        </a>
        @endcan
    </div>
    <div class="card-body">
        <table id="receiptsTable" class="table table-hover w-100">
            <thead>
            <tr>
                <th>رقم السند</th>
                <th>التاريخ</th>
                <th>النوع</th>
                <th>الجهة</th>
                <th>الحساب الدائن</th>
                <th>حساب النقدية</th>
                <th>المبلغ</th>
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
    var url = "{{ route('vouchers.receipts.data') }}";
    var cfg = $.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: false,
        ajax: { url: url, dataSrc: 'data' },
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
    });
    $('#receiptsTable').DataTable(cfg);
});
</script>
@endsection
