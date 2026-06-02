@extends('layouts.app')
@section('page-title', 'فواتير المصروفات')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-receipt-cutoff text-danger me-2"></i>فواتير المصروفات
        </h5>
        @can('expenses.create')
        <a href="{{ route('expense-invoices.create') }}" class="btn btn-danger btn-sm">
            <i class="bi bi-plus-circle me-1"></i> فاتورة مصروف جديدة
        </a>
        @endcan
    </div>

    {{-- Branch filter --}}
    @if(isset($branches) && $branches->count() >= 1)
    <div class="card-body border-bottom py-2">
        <form method="GET" class="row g-2 align-items-end">
            @include('components.branch-filter')
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-funnel"></i> تصفية
                </button>
            </div>
        </form>
    </div>
    @endif

    <div class="card-body">
        <div class="table-responsive">
            <table id="expenses-table" class="table table-hover dt-server w-100"
                   data-url="{{ route('expense-invoices.index') }}">
                <thead class="table-light">
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>المورد</th>
                    <th>حساب المصروف</th>
                    <th>التاريخ</th>
                    <th>تاريخ الاستحقاق</th>
                    <th class="text-end">الإجمالي</th>
                    <th class="text-end">المدفوع</th>
                    <th class="text-end">المتبقي</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center">الإجراءات</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    $('#expenses-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: {
            url: $('#expenses-table').data('url'),
            data: function(d) {
                @if(isset($branchId) && $branchId) d.branch_id = {{ $branchId }}; @endif
            }
        },
        columns: [
            { data: 'invoice_number' },
            { data: 'vendor_name' },
            { data: 'account_name' },
            { data: 'date' },
            { data: 'due', orderable: false },
            { data: 'total_fmt',     className: 'text-end' },
            { data: 'paid_fmt',      className: 'text-end' },
            { data: 'remaining_fmt', className: 'text-end' },
            { data: 'status_badge',  className: 'text-center', orderable: false },
            { data: 'action',        className: 'text-center', orderable: false },
        ],
    }));
});
</script>
@endsection
