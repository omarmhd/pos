@extends('layouts.app')
@section('page-title', 'عروض الأسعار')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-file-earmark-richtext text-info me-2"></i>عروض الأسعار (Quotations)
        </h5>
        @can('quotations.create')
        <a href="{{ route('sales-quotations.create') }}" class="btn btn-info btn-sm text-white">
            <i class="bi bi-plus-circle me-1"></i> عرض سعر جديد
        </a>
        @endcan
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
            <table id="qt-table" class="table table-hover w-100" data-url="{{ route('sales-quotations.index') }}">
                <thead class="table-light">
                <tr>
                    <th>رقم العرض</th><th>العميل</th><th>التاريخ</th>
                    <th>صالح حتى</th><th class="text-end">الإجمالي</th>
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
    $('#qt-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true, serverSide: true,
        ajax: { url: $('#qt-table').data('url'),
                data: d => { @if(isset($branchId) && $branchId) d.branch_id = {{ $branchId }}; @endif } },
        order: [[2, 'desc']],
        columns: [
            { data: 'quotation_number' },
            { data: 'customer_name_col' },
            { data: 'date' },
            { data: 'valid', defaultContent: '—', orderable: false },
            { data: 'total_fmt', className: 'text-end' },
            { data: 'status_badge', className: 'text-center', orderable: false },
            { data: 'user_name' },
            { data: 'action', className: 'text-center', orderable: false },
        ],
    }));
});
</script>
@endsection
