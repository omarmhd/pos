@extends('layouts.app')
@section('page-title', 'دفتر الأستاذ العام')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-journal-text"></i> دفتر الأستاذ العام
    </h4>
    <a href="{{ route('accounting.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-right"></i> لوحة المحاسبة
    </a>
</div>

{{-- AJAX filter bar --}}
@if(isset($branches) && $branches->count() >= 1)
<div class="card mb-3 border-0 shadow-sm no-print">
    <div class="card-body py-2">
        <div id="filterForm" class="row g-2 align-items-end">
            @include('components.branch-filter', ['dtReload' => true])
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearLedger">
                    <i class="bi bi-x"></i> مسح
                </button>
            </div>
            <div class="col-auto" id="ledgerBranchBadge"></div>
        </div>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table id="ledger-table" class="table table-hover align-middle mb-0" style="width:100%">
            <thead class="table-light">
                <tr>
                    <th style="width:100px">الكود</th>
                    <th>اسم الحساب</th>
                    <th style="width:110px">النوع</th>
                    <th style="width:130px" class="text-end">إجمالي مدين</th>
                    <th style="width:130px" class="text-end">إجمالي دائن</th>
                    <th style="width:130px" class="text-end">الرصيد الصافي</th>
                    <th style="width:90px" class="text-center">تفاصيل</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    var table = $('#ledger-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("accounting.ledger.index") }}',
            data: function(d) { dtCollectFilters(d, '#filterForm'); }
        },
        order: [[0, 'asc']],
        columns: [
            { data: 'code',        name: 'a.code' },
            { data: 'name',        name: 'a.name' },
            { data: 'type_badge',  name: 'a.type', orderable: false },
            { data: 'total_debit',  name: 'total_debit',  searchable: false,
              render: function(v) { return '<span class="font-monospace">' + parseFloat(v).toLocaleString('ar-EG', {minimumFractionDigits:2}) + '</span>'; },
              className: 'text-end' },
            { data: 'total_credit', name: 'total_credit', searchable: false,
              render: function(v) { return '<span class="font-monospace">' + parseFloat(v).toLocaleString('ar-EG', {minimumFractionDigits:2}) + '</span>'; },
              className: 'text-end' },
            { data: 'net_balance', name: 'net_balance', orderable: false, searchable: false, className: 'text-end' },
            { data: 'action',      name: 'action',      orderable: false, searchable: false, className: 'text-center' }
        ]
    }));

    dtWireFilters(table, '#filterForm');

    $('#btnClearLedger').on('click', function() {
        $('#filterForm select, #filterForm input').val('');
        table.ajax.reload();
    });
});
</script>
@endsection
