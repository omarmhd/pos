@extends('layouts.app')
@section('page-title', 'أوامر الشراء')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-file-earmark-text text-warning me-2"></i>أوامر الشراء (Purchase Orders)
        </h5>
        @can('purchase_orders.create')
        <a href="{{ route('purchase-orders.create') }}" class="btn btn-warning btn-sm">
            <i class="bi bi-plus-circle me-1"></i> أمر شراء جديد
        </a>
        @endcan
    </div>

    @if(isset($branches) && $branches->count() >= 1)
    <div class="card-body border-bottom py-2">
        <form method="GET" class="row g-2 align-items-end">
            @include('components.branch-filter')
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-funnel"></i>
                </button>
            </div>
        </form>
    </div>
    @endif

    <div class="card-body">
        <div class="table-responsive">
            <table id="po-table" class="table table-hover w-100"
                   data-url="{{ route('purchase-orders.index') }}">
                <thead class="table-light">
                <tr>
                    <th>رقم الأمر</th>
                    <th>المورد</th>
                    <th>التاريخ</th>
                    <th>التسليم المتوقع</th>
                    <th class="text-end">الإجمالي</th>
                    <th class="text-center">الحالة</th>
                    <th>المُنشئ</th>
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
    $('#po-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: {
            url: $('#po-table').data('url'),
            data: function(d) {
                @if(isset($branchId) && $branchId) d.branch_id = {{ $branchId }}; @endif
            }
        },
        order: [[2, 'desc']],
        columns: [
            { data: 'po_number' },
            { data: 'supplier_name' },
            { data: 'date' },
            { data: 'expected_delivery_date', defaultContent: '—', orderable: false },
            { data: 'total_fmt', className: 'text-end' },
            { data: 'status_badge', className: 'text-center', orderable: false },
            { data: 'user_name' },
            { data: 'action', className: 'text-center', orderable: false },
        ],
    }));
});
</script>
@endsection
