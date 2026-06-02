@extends('layouts.app')
@section('page-title', 'مرتجعات المبيعات')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-arrow-return-left text-danger me-2"></i>مرتجعات المبيعات</h5>
        @can('sales.returns.create')
        <a href="{{ route('sale-returns.create') }}" class="btn btn-danger btn-sm">
            <i class="bi bi-plus-circle me-1"></i> مرتجع جديد
        </a>
        @endcan
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-server" style="width:100%" id="returnsTable"
                   data-url="{{ route('sale-returns.index') }}">
                <thead class="table-light">
                <tr>
                    <th>رقم المرتجع</th>
                    <th>العميل</th>
                    <th>التاريخ</th>
                    <th class="text-end">المبلغ</th>
                    <th class="text-center">طريقة الاسترداد</th>
                    <th>المستخدم</th>
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
$(function () {
    const cfg = $.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: $('#returnsTable').data('url'),
        columns: [
            { data: 'return_number' },
            { data: 'customer_name' },
            { data: 'date' },
            { data: 'total_fmt',    className: 'text-end' },
            { data: 'method_badge', className: 'text-center', orderable: false },
            { data: 'user_name' },
            { data: 'action',       className: 'text-center', orderable: false },
        ],
    });
    $('#returnsTable').DataTable(cfg);
});
</script>
@endsection
