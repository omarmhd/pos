@extends('layouts.app')
@section('page-title', 'دفتر الأستاذ العام')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-journal-text"></i> دفتر الأستاذ العام
        @if(isset($branchId) && $branchId)
            @php $selectedBranch = $branches->firstWhere('id', $branchId); @endphp
            @if($selectedBranch)
                <span class="badge bg-primary ms-2">{{ $selectedBranch->name }}</span>
            @endif
        @else
            <span class="badge bg-secondary ms-2 small">جميع الفروع (موحد)</span>
        @endif
    </h4>
    <a href="{{ route('accounting.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-right"></i> لوحة المحاسبة
    </a>
</div>

{{-- Branch filter --}}
@if(isset($branches) && $branches->count() >= 1)
<div class="card mb-3 border-0 shadow-sm no-print">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            @include('components.branch-filter')
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> عرض
                </button>
                @if(isset($branchId) && $branchId)
                <a href="{{ route('accounting.ledger.index') }}" class="btn btn-outline-secondary btn-sm">
                    الكل
                </a>
                @endif
            </div>
        </form>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table id="ledger-table" class="table table-hover align-middle mb-0 w-100">
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
$('#ledger-table').DataTable($.extend(true, {}, window.dtDefaults, {
    processing: true,
    serverSide: true,
    ajax: {
        url: '{{ route('accounting.ledger.index') }}',
        data: function(d) {
            @if(isset($branchId) && $branchId)
            d.branch_id = {{ $branchId }};
            @endif
        }
    },
    order: [[0, 'asc']],
    columns: [
        { data: 'code',        name: 'a.code' },
        { data: 'name',        name: 'a.name' },
        { data: 'type_badge',  name: 'a.type', orderable: false },
        { data: 'total_debit',  name: 'total_debit',  searchable: false,
          render: v => '<span class="font-monospace">' + parseFloat(v).toLocaleString('ar-EG', {minimumFractionDigits:2}) + '</span>',
          className: 'text-end' },
        { data: 'total_credit', name: 'total_credit', searchable: false,
          render: v => '<span class="font-monospace">' + parseFloat(v).toLocaleString('ar-EG', {minimumFractionDigits:2}) + '</span>',
          className: 'text-end' },
        { data: 'net_balance', name: 'net_balance', orderable: false, searchable: false, className: 'text-end' },
        { data: 'action',      name: 'action',      orderable: false, searchable: false, className: 'text-center' }
    ]
}));
</script>
@endsection
