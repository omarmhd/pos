@extends('layouts.app')

@section('page-title', 'الموردين')

@section('content')
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-truck"></i> قائمة الموردين</h5>
            <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> إضافة مورد جديد
            </a>
        </div>
        <div class="card-body">
            <div class="text-muted small mb-2"><i class="bi bi-lightbulb"></i> تلميح: انقر مزدوجًا على أي صف لفتح بطاقة المورّد وكل حركاته.</div>
            <div class="table-responsive">
                <table class="table table-hover dt-table" style="width:100%">
                    <thead class="table-light">
                    <tr>
                        <th>الاسم</th>
                        <th>الشركة</th>
                        <th>الهاتف</th>
                        <th>البريد الإلكتروني</th>
                        <th>عدد المشتريات</th>
                        <th>إجراءات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($suppliers as $supplier)
                        <tr style="cursor:pointer" ondblclick="openSupplierSummary('{{ $supplier->id }}')">
                            <td><strong>{{ $supplier->name }}</strong></td>
                            <td>{{ $supplier->company ?? '-' }}</td>
                            <td>{{ $supplier->phone }}</td>
                            <td>{{ $supplier->email ?? '-' }}</td>
                            <td><span class="badge bg-info">{{ $supplier->purchases_count }}</span></td>
                            <td>
                                <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-sm btn-info btn-action" title="عرض التفاصيل">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('supplier-payments.create', $supplier) }}" class="btn btn-sm btn-success btn-action" title="تسجيل دفعة">
                                    <i class="bi bi-cash-coin"></i>
                                </a>
                                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-sm btn-primary btn-action" title="تعديل">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger btn-action"
                                            onclick="return confirm('هل أنت متأكد؟')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

{{-- Supplier Summary Modal --}}
<div class="modal fade" id="supplierSummaryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-truck me-1"></i> ملخص المورّد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="supplierSummaryBody"></div>
            <div class="modal-footer">
                <a href="#" id="supplierSummaryDetailsBtn" class="btn btn-primary">
                    <i class="bi bi-arrow-left-circle me-1"></i> كل التفاصيل
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function openSupplierSummary(id) {
    var base = '{{ url('suppliers') }}';
    var body = document.getElementById('supplierSummaryBody');
    body.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> جارٍ التحميل…</div>';
    document.getElementById('supplierSummaryDetailsBtn').href = base + '/' + id;
    new bootstrap.Modal(document.getElementById('supplierSummaryModal')).show();
    fetch(base + '/' + id + '/summary', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.text(); })
        .then(function (html) { body.innerHTML = html; })
        .catch(function () { body.innerHTML = '<div class="text-danger text-center py-3">تعذّر تحميل الملخص</div>'; });
}
</script>
@endsection
