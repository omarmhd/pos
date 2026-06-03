@extends('layouts.app')
@section('page-title', 'القيود اليومية')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-journal-text"></i> سجل القيود اليومية</h5>
            @can('journal_entries.create')
            <a href="{{ route('journal_entries.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> قيد يدوي جديد
            </a>
            @endcan
        </div>

        {{-- AJAX filter bar — no form submit, dtWireFilters() handles reload --}}
        @if(isset($branches) && $branches->count() >= 1)
        <div id="filterForm" class="row g-2 align-items-end mt-2">
            @include('components.branch-filter', ['dtReload' => true])
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearJeFilters">
                    <i class="bi bi-x"></i> مسح
                </button>
            </div>
        </div>
        @endif
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="je-table" class="table table-hover align-middle" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>رقم القيد</th>
                        <th>التاريخ</th>
                        <th>الوصف</th>
                        <th>المرجع</th>
                        <th>المصدر</th>
                        <th class="text-end text-primary">المدين</th>
                        <th class="text-end text-success">الدائن</th>
                        <th class="text-center">توازن</th>
                        <th>بواسطة</th>
                        <th class="text-center">إجراءات</th>
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
    var table = $('#je-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing : true,
        serverSide : true,
        ajax: {
            url: '{{ route('journal_entries.index') }}',
            data: function(d) { dtCollectFilters(d, '#filterForm'); }
        },
        order      : [[1, 'desc']],
        columns: [
            { data: 'entry_number',  name: 'entry_number'  },
            { data: 'entry_date_fmt',name: 'entry_date',     searchable: true  },
            { data: 'description',   name: 'description'   },
            { data: 'reference',     name: 'reference',     defaultContent: '—' },
            { data: 'source_badge',  name: 'source_badge',  orderable: false  },
            { data: 'debit_fmt',     name: 'debit_fmt',     orderable: true,  searchable: false, className: 'text-end' },
            { data: 'credit_fmt',    name: 'credit_fmt',    orderable: false, searchable: false, className: 'text-end' },
            { data: 'balanced_icon', name: 'balanced_icon', orderable: false, searchable: false, className: 'text-center' },
            { data: 'user_name',     name: 'user_name',     orderable: false  },
            { data: 'action',        name: 'action',        orderable: false, searchable: false, className: 'text-center' },
        ]
    }));

    // Wire filters to DataTable (no page reload)
    dtWireFilters(table, '#filterForm');

    $('#btnClearJeFilters').on('click', function() {
        $('#filterForm select, #filterForm input').val('');
        table.ajax.reload();
    });
});
</script>
@endsection
