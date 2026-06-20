@extends('layouts.app')
@section('page-title', 'دفتر الأستاذ العام')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" rel="stylesheet">
<style>.select2-container{width:100%!important}</style>
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

{{-- منتقي كشف حساب طرف (الأستاذ المساعد) --}}
@php
    $optC = $customers->map(fn($c) => "<option value='customer:{$c->id}'>" . e($c->name) . "</option>")->implode('');
    $optS = $suppliers->map(fn($s) => "<option value='supplier:{$s->id}'>" . e($s->name) . "</option>")->implode('');
    $optE = $employees->map(fn($e) => "<option value='employee:{$e->id}'>" . e($e->name) . "</option>")->implode('');
@endphp
<div class="card mb-3 border-info shadow-sm no-print">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-7">
                <label class="form-label small mb-1 fw-semibold">
                    <i class="bi bi-person-vcard text-info"></i> كشف حساب طرف (الأستاذ المساعد) — عميل / مورّد / موظف
                </label>
                <select id="partyPicker" class="form-select form-select-sm">
                    <option value="">— ابحث واختر عميلًا أو مورّدًا أو موظفًا —</option>
                    <optgroup label="العملاء">{!! $optC !!}</optgroup>
                    <optgroup label="الموردون">{!! $optS !!}</optgroup>
                    <optgroup label="الموظفون">{!! $optE !!}</optgroup>
                </select>
            </div>
            <div class="col-auto">
                <button type="button" id="btnPartyLedger" class="btn btn-info btn-sm text-white">
                    <i class="bi bi-journal-bookmark"></i> عرض الكشف
                </button>
            </div>
        </div>
        <div class="form-text">يعرض حركة الطرف برصيد جارٍ من القيود — بينما الجدول أدناه هو الأستاذ العام (حسابات المراقبة الإجمالية).</div>
    </div>
</div>

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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function() {
    // منتقي كشف حساب الطرف
    if (jQuery.fn.select2) jQuery('#partyPicker').select2({ theme: 'bootstrap-5', dir: 'rtl' });
    document.getElementById('btnPartyLedger').addEventListener('click', function () {
        var v = document.getElementById('partyPicker').value;
        if (!v) return;
        var p = v.split(':');
        window.location = '{{ url('accounting/ledger/party') }}/' + p[0] + '/' + p[1];
    });

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
