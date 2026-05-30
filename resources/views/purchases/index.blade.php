@extends('layouts.app')

@section('page-title', 'المشتريات')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-bag-plus"></i> قائمة المشتريات</h5>
        <a href="{{ route('purchases.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> إضافة فاتورة شراء
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="purchases-table" class="table table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>المورد</th>
                        <th>المستخدم</th>
                        <th>الإجمالي</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th>حالة الدفع</th>
                        <th>التاريخ</th>
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
    $('#purchases-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route('purchases.index') }}' },
        order: [[7, 'desc']],
        columns: [
            { data: 'invoice_number', name: 'invoice_number' },
            { data: 'supplier_name',  name: 'supplier_name' },
            { data: 'user_name',      name: 'user_name' },
            { data: 'total_fmt',      name: 'total_amount',  orderable: true,  searchable: false },
            { data: 'paid_fmt',       name: 'paid_amount',   orderable: true,  searchable: false },
            { data: 'remaining_fmt',  name: 'remaining',     orderable: false, searchable: false },
            { data: 'status_badge',   name: 'payment_status',orderable: true,  searchable: false },
            { data: 'date',           name: 'created_at' },
            { data: 'action',         name: 'action',        orderable: false, searchable: false },
        ]
    }));
});
</script>
@endsection
