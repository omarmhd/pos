@extends('layouts.app')

@section('page-title', 'المبيعات')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-receipt"></i> قائمة المبيعات</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="sales-table" class="table table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>الكاشير</th>
                        <th>المبلغ الفرعي</th>
                        <th>الخصم</th>
                        <th>الإجمالي</th>
                        <th>طريقة الدفع</th>
                        <th>النوع</th>
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
    $('#sales-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route('sales.index') }}' },
        order: [[7, 'desc']],
        columns: [
            { data: 'invoice_number', name: 'invoice_number' },
            { data: 'user_name',      name: 'user_name' },
            { data: 'subtotal_fmt',   name: 'subtotal',      orderable: true,  searchable: false },
            { data: 'discount_fmt',   name: 'discount',      orderable: false, searchable: false },
            { data: 'total_fmt',      name: 'total_amount',  orderable: true,  searchable: false },
            { data: 'payment_badge',  name: 'payment_method',orderable: false, searchable: false },
            { data: 'credit_badge',   name: 'is_credit',     orderable: false, searchable: false },
            { data: 'date',           name: 'created_at' },
            { data: 'action',         name: 'action',        orderable: false, searchable: false },
        ]
    }));
});
</script>
@endsection
