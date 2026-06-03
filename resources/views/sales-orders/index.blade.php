@extends('layouts.app')
@section('page-title', 'أوامر البيع')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-bag-check text-success me-2"></i>أوامر البيع (Sales Orders)</h5>
    </div>
    @if(isset($branches) && $branches->count() >= 1)
    <div class="card-body border-bottom py-2">
        <form method="GET" class="row g-2 align-items-end">
            @include('components.branch-filter')
            <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @endif
    <div class="card-body">
        <div class="table-responsive">
            <table id="so-table" class="table table-hover w-100" data-url="{{ route('sales-orders.index') }}">
                <thead class="table-light">
                <tr>
                    <th>رقم الأمر</th><th>العميل</th><th>التاريخ</th>
                    <th class="text-end">الإجمالي</th>
                    <th class="text-center">الحالة</th><th>المُنشئ</th>
                    <th class="text-center">إجراءات</th>
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
    $('#so-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true, serverSide: true,
        ajax: { url: $('#so-table').data('url'),
                data: d => { @if(isset($branchId) && $branchId) d.branch_id = {{ $branchId }}; @endif } },
        order: [[2, 'desc']],
        columns: [
            { data: 'order_number' },
            { data: 'customer_name' },
            { data: 'date' },
            { data: 'total_fmt', className: 'text-end' },
            { data: 'status_badge', className: 'text-center', orderable: false },
            { data: 'user_name' },
            { data: 'action', className: 'text-center', orderable: false },
        ],
    }));
});
</script>
@endsection
