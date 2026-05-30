@extends('layouts.app')

@section('page-title', 'إدارة العملاء')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-people"></i> قائمة العملاء</h5>
        <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-person-plus"></i> إضافة عميل
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="customers-table" class="table table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>العميل</th>
                        <th>الهاتف</th>
                        <th class="text-center">فواتير آجل</th>
                        <th class="text-end">إجمالي الذمم</th>
                        <th class="text-end">حد الائتمان</th>
                        <th class="text-center">الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function () {
    $('#customers-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route('customers.index') }}' },
        order: [[0, 'asc']],
        columns: [
            { data: 'name',              name: 'name' },
            { data: 'phone',             name: 'phone' },
            { data: 'credit_sales_count',name: 'credit_sales_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'outstanding',       name: 'outstanding',        orderable: false, searchable: false, className: 'text-end' },
            { data: 'credit_limit_fmt',  name: 'credit_limit',       orderable: true,  searchable: false, className: 'text-end' },
            { data: 'status_badge',      name: 'is_active',          orderable: true,  searchable: false, className: 'text-center' },
            { data: 'action',            name: 'action',             orderable: false, searchable: false },
        ]
    }));
});
</script>
@endsection
