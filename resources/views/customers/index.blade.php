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
        <div class="text-muted small mb-2"><i class="bi bi-lightbulb"></i> تلميح: انقر مزدوجًا على أي صف لعرض ملخص سريع، ومنه زر «كل التفاصيل».</div>
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

{{-- Customer Summary Modal --}}
<div class="modal fade" id="customerSummaryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person me-1"></i> ملخص العميل</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customerSummaryBody"></div>
            <div class="modal-footer">
                <a href="#" id="customerSummaryDetailsBtn" class="btn btn-primary">
                    <i class="bi bi-arrow-left-circle me-1"></i> كل التفاصيل وكشف الحساب
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function () {
    var base = '{{ url('customers') }}';
    $('#customers-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route('customers.index') }}' },
        order: [[0, 'asc']],
        createdRow: function (row) { row.style.cursor = 'pointer'; },
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

    var cModal = new bootstrap.Modal(document.getElementById('customerSummaryModal'));
    $('#customers-table tbody').on('dblclick', 'tr', function () {
        var id = this.id;
        if (!id) return;
        var body = document.getElementById('customerSummaryBody');
        body.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> جارٍ التحميل…</div>';
        document.getElementById('customerSummaryDetailsBtn').href = base + '/' + id;
        cModal.show();
        fetch(base + '/' + id + '/summary', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (html) { body.innerHTML = html; })
            .catch(function () { body.innerHTML = '<div class="text-danger text-center py-3">تعذّر تحميل الملخص</div>'; });
    });
});
</script>
@endsection
