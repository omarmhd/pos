@extends('layouts.app')
@section('page-title', 'تحويلات المخزون الداخلية')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">
                <i class="bi bi-arrow-left-right text-warning me-2"></i>
                تحويلات المخزون الداخلية
            </h5>
            <small class="text-muted">نقل بضاعة من مخزن لآخر (مخزن خلفي → معرض/رفوف)</small>
        </div>
        @can('stock_transfers.create')
        <a href="{{ route('stock-transfers.create') }}" class="btn btn-warning btn-sm">
            <i class="bi bi-plus-circle me-1"></i> تحويل جديد
        </a>
        @endcan
    </div>

    @if(isset($branches) && $branches->count() >= 1)
    <div class="card-body border-bottom py-2">
        <div id="filterForm" class="row g-2 align-items-end">
            @include('components.branch-filter', ['dtReload' => true])
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearTransfer">
                    <i class="bi bi-x"></i> مسح
                </button>
            </div>
        </div>
    </div>
    @endif

    <div class="card-body">
        <div class="table-responsive">
            <table id="transfer-table" class="table table-hover" style="width:100%"
                   data-url="{{ route('stock-transfers.index') }}">
                <thead class="table-light">
                <tr>
                    <th>رقم التحويل</th>
                    <th>من مخزن</th>
                    <th>إلى مخزن</th>
                    <th>التاريخ</th>
                    <th class="text-center">الحالة</th>
                    <th>المُنشئ</th>
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
    var table = $('#transfer-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: {
            url: $('#transfer-table').data('url'),
            data: function(d) { dtCollectFilters(d, '#filterForm'); }
        },
        order: [[3, 'desc']],
        columns: [
            { data: 'transfer_number' },
            { data: 'from_wh' },
            { data: 'to_wh' },
            { data: 'date' },
            { data: 'status_badge', className: 'text-center', orderable: false },
            { data: 'user_name' },
            { data: 'action', className: 'text-center', orderable: false },
        ],
    }));

    dtWireFilters(table, '#filterForm');

    $('#btnClearTransfer').on('click', function() {
        $('#filterForm select, #filterForm input').val('');
        table.ajax.reload();
    });
});
</script>
@endsection
