@extends('layouts.app')
@section('page-title', 'القيود اليومية')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-journal-text"></i> سجل القيود اليومية
                @if(isset($branchId) && $branchId)
                    @php $b = \App\Models\Branch::find($branchId); @endphp
                    @if($b)<span class="badge bg-primary ms-2">{{ $b->name }}</span>@endif
                @endif
            </h5>
            @can('journal_entries.create')
            <a href="{{ route('journal_entries.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> قيد يدوي جديد
            </a>
            @endcan
        </div>
        {{-- Branch filter (only when multiple branches exist) --}}
        @if(isset($branches) && $branches->count() > 1)
        <form method="GET" class="d-flex gap-2 align-items-center mt-2">
            @include('components.branch-filter')
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-funnel"></i>
            </button>
            @if(isset($branchId) && $branchId)
            <a href="{{ route('journal_entries.index') }}" class="btn btn-sm btn-outline-secondary">
                الكل
            </a>
            @endif
        </form>
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
    $('#je-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing : true,
        serverSide : true,
        ajax: {
            url: '{{ route('journal_entries.index') }}',
            data: function(d) {
                @if(isset($branchId) && $branchId)
                d.branch_id = {{ $branchId }};
                @endif
            }
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
});
</script>
@endsection
