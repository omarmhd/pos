@extends('layouts.app')
@section('page-title', 'الشيكات')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-bank2 text-primary me-2"></i>إدارة الشيكات</h5>
        @can('checks.create')
        <a href="{{ route('checks.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> تسجيل شيك جديد
        </a>
        @endcan
    </div>

    {{-- فلاتر --}}
    <div class="card-body border-bottom py-2">
        <div id="filterForm" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">النوع</label>
                <select name="type" id="fType" class="form-select form-select-sm" style="min-width:140px">
                    <option value="">الكل</option>
                    <option value="receivable">وارد (من عملاء)</option>
                    <option value="payable">صادر (لموردين)</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">الحالة</label>
                <select name="status" id="fStatus" class="form-select form-select-sm" style="min-width:155px">
                    <option value="">كل الحالات</option>
                    <option value="received">تحت التحصيل</option>
                    <option value="deposited">مودَع</option>
                    <option value="cleared">مُقاصّ</option>
                    <option value="bounced">مرتجع</option>
                    <option value="pending">بانتظار الصرف</option>
                    <option value="returned">أُعيد</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">استحقاق من</label>
                <input type="date" name="from" id="fFrom" class="form-control form-control-sm" style="min-width:135px">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">إلى</label>
                <input type="date" name="to" id="fTo" class="form-control form-control-sm" style="min-width:135px">
            </div>
            <div class="col-auto d-flex align-items-end gap-1">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnApply">
                    <i class="bi bi-funnel"></i> تصفية
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnClear">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <table id="checksTable" class="table table-hover" style="width:100%" data-title="الشيكات">
            <thead>
            <tr>
                <th>رقم الشيك</th>
                <th>النوع</th>
                <th>الجهة</th>
                <th>رقم الشيك الورقي</th>
                <th>البنك</th>
                <th>تاريخ الشيك</th>
                <th>تاريخ الاستحقاق</th>
                <th class="text-end">المبلغ ({{ $currency }})</th>
                <th>الحالة</th>
                <th>الإجراءات</th>
            </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function () {
    var table = $('#checksTable').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("checks.data") }}',
            data: function(d) {
                d.type   = $('#fType').val();
                d.status = $('#fStatus').val();
                d.from   = $('#fFrom').val();
                d.to     = $('#fTo').val();
            }
        },
        order: [[6, 'asc']],  // ترتيب تصاعدي حسب تاريخ الاستحقاق
        columns: [
            { data: 'check_number', name: 'check_number' },
            { data: 'type_badge',   name: 'type', orderable: false },
            { data: 'party',        name: 'party', orderable: false },
            { data: 'check_ref',    name: 'check_ref', defaultContent: '—' },
            { data: 'bank_name',    name: 'bank_name', defaultContent: '—' },
            { data: 'check_date_fmt', name: 'check_date' },
            { data: 'due_date_fmt',   name: 'due_date' },
            { data: 'amount_fmt',     name: 'amount', className: 'text-end' },
            { data: 'status_badge',   name: 'status', orderable: false },
            { data: 'action',         name: 'action', orderable: false, searchable: false },
        ]
    }));

    $('#btnApply').on('click', function () { table.ajax.reload(); });
    $('#btnClear').on('click', function () {
        $('#fType, #fStatus').val('');
        $('#fFrom, #fTo').val('');
        table.ajax.reload();
    });
});
</script>
@endsection
