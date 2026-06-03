@extends('layouts.app')
@section('page-title', 'سندات القبض')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-arrow-down-circle text-success me-2"></i>سندات القبض (تحصيل)</h5>
        @can('vouchers.create')
        <a href="{{ route('vouchers.receipts.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i> سند قبض جديد
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="card-body border-bottom py-2">
        <form method="GET" class="row g-2 align-items-end" id="filterForm">
            @include('components.branch-filter')

            <div class="col-auto">
                <label class="form-label small mb-1"><i class="bi bi-calendar-event me-1"></i>من تاريخ</label>
                <input type="date" name="from" class="form-control form-control-sm"
                       value="{{ request('from') }}" style="min-width:135px">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">إلى تاريخ</label>
                <input type="date" name="to" class="form-control form-control-sm"
                       value="{{ request('to') }}" style="min-width:135px">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1"><i class="bi bi-credit-card me-1"></i>طريقة الاستلام</label>
                <select name="method" class="form-select form-select-sm" style="min-width:140px">
                    <option value="">كل الطرق</option>
                    <option value="cash"          {{ request('method') === 'cash'          ? 'selected':'' }}>نقدي</option>
                    <option value="bank"          {{ request('method') === 'bank'          ? 'selected':'' }}>تحويل بنكي</option>
                    <option value="mobile_wallet" {{ request('method') === 'mobile_wallet' ? 'selected':'' }}>محفظة إلكترونية</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-funnel"></i> تصفية
                </button>
                @if(request()->hasAny(['branch_id','from','to','method']))
                    <a href="{{ route('vouchers.receipts.index') }}" class="btn btn-outline-danger btn-sm ms-1">
                        <i class="bi bi-x"></i>
                    </a>
                @endif
            </div>
        </form>
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
    // Pass current filter values as query params to the data endpoint
    var params = new URLSearchParams({
        @if(request('branch_id')) branch_id: '{{ request('branch_id') }}', @endif
        @if(request('from'))      from:      '{{ request('from') }}',      @endif
        @if(request('to'))        to:        '{{ request('to') }}',        @endif
        @if(request('method'))    method:    '{{ request('method') }}',    @endif
    });
    var url = "{{ route('vouchers.receipts.data') }}" + (params.toString() ? '?' + params.toString() : '');

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
