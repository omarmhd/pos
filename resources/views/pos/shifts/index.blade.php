@extends('layouts.app')
@section('page-title', 'الورديات النقدية')

@section('content')

{{-- Active shift banner --}}
@if($myShift)
<div class="alert alert-success d-flex justify-content-between align-items-center mb-3">
    <div>
        <i class="bi bi-unlock-fill me-2"></i>
        <strong>وردية نشطة:</strong>
        منذ {{ $myShift->opened_at->format('H:i') }} —
        الرصيد الافتتاحي: <strong>{{ number_format($myShift->opening_amount, 2) }} {{ $currency }}</strong>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('pos.index') }}" class="btn btn-success btn-sm">
            <i class="bi bi-cart"></i> نقطة البيع
        </a>
        @can('pos.shifts.close')
        <a href="{{ route('pos.shifts.close', $myShift) }}" class="btn btn-warning btn-sm text-dark">
            <i class="bi bi-lock-fill me-1"></i> إقفال الوردية
        </a>
        @endcan
    </div>
</div>
@else
<div class="alert alert-warning d-flex justify-content-between align-items-center mb-3">
    <div>
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        لا توجد وردية مفتوحة — افتح وردية قبل بدء المبيعات
    </div>
    @can('pos.shifts.open')
    <a href="{{ route('pos.shifts.open') }}" class="btn btn-success btn-sm">
        <i class="bi bi-unlock-fill me-1"></i> افتح وردية
    </a>
    @endcan
</div>
@endif

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-cash-register text-warning me-2"></i>سجل الورديات النقدية</h5>
        @can('pos.shifts.open')
        @unless($myShift)
        <a href="{{ route('pos.shifts.open') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i> وردية جديدة
        </a>
        @endunless
        @endcan
    </div>

    {{-- AJAX filters --}}
    @if(isset($branches) && $branches->count() >= 1)
    <div class="card-body border-bottom py-2">
        <div id="filterForm" class="row g-2 align-items-end">
            @include('components.branch-filter', ['dtReload' => true])
            <div class="col-auto">
                <label class="form-label small mb-1">الحالة</label>
                <select name="status" class="form-select form-select-sm" style="min-width:120px">
                    <option value="">الكل</option>
                    <option value="open">مفتوحة</option>
                    <option value="closed">مغلقة</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearShift">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
    </div>
    @endif

    <div class="card-body">
        <table id="shifts-table" class="table table-hover" style="width:100%">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>الكاشير</th>
                    <th>الفرع</th>
                    <th>الافتتاح</th>
                    <th>الإقفال</th>
                    <th>الحالة</th>
                    <th class="text-end">رصيد افتتاحي</th>
                    <th class="text-end">المتوقع</th>
                    <th class="text-end">الفعلي</th>
                    <th class="text-center">الفرق</th>
                    <th></th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    var table = $('#shifts-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("pos.shifts.index") }}',
            data: function(d) { dtCollectFilters(d, '#filterForm'); }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'id',           name: 'id',          width: '55px' },
            { data: 'cashier',      name: 'cashier' },
            { data: 'branch_name',  name: 'branch_name' },
            { data: 'opened_fmt',   name: 'opened_at' },
            { data: 'closed_fmt',   name: 'closed_at',   orderable: false },
            { data: 'status_badge', name: 'status',      orderable: false },
            { data: 'opening_fmt',  name: 'opening_amount', className: 'text-end', searchable: false },
            { data: 'expected_fmt', name: 'expected_amount', className: 'text-end', searchable: false },
            { data: 'closing_fmt',  name: 'closing_amount', className: 'text-end', searchable: false },
            { data: 'variance_fmt', name: 'variance_amount', className: 'text-center', orderable: false, searchable: false },
            { data: 'action',       name: 'action', orderable: false, searchable: false, className: 'text-center' },
        ]
    }));

    dtWireFilters(table, '#filterForm');
    $('#btnClearShift').on('click', function() {
        $('#filterForm select, #filterForm input').val('');
        table.ajax.reload();
    });
});
</script>
@endsection
