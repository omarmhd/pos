@extends('layouts.app')

@section('page-title', 'سجل المراجعة')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-shield-check"></i> سجل المراجعة</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="audit-table" class="table table-hover table-sm align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>الوقت</th>
                        <th>المستخدم</th>
                        <th>الحدث</th>
                        <th>النوع</th>
                        <th>المرجع</th>
                        <th>قبل</th>
                        <th>بعد</th>
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
    $('#audit-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route('audit.logs.index') }}' },
        order: [[0, 'desc']],
        buttons: window.dtDefaults.buttons.map(function(b) {
            return $.extend(true, {}, b, { exportOptions: { columns: ':not(:nth-child(n+7))' } });
        }),
        columns: [
            { data: 'id',             name: 'id' },
            { data: 'time',           name: 'created_at' },
            { data: 'user_name',      name: 'user_name' },
            { data: 'action_badge',   name: 'action',         orderable: true,  searchable: false },
            { data: 'model_name',     name: 'model_name' },
            { data: 'auditable_id',   name: 'auditable_id',   orderable: true,  searchable: false },
            { data: 'old_values_fmt', name: 'old_values',     orderable: false, searchable: false },
            { data: 'new_values_fmt', name: 'new_values',     orderable: false, searchable: false },
        ]
    }));
});
</script>
@endsection
