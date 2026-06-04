@extends('layouts.app')
@section('page-title', 'مراكز التكلفة')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-diagram-2 text-info me-2"></i>مراكز التكلفة</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('cost-centers.report') }}" class="btn btn-outline-success btn-sm">
            <i class="bi bi-bar-chart me-1"></i>تقرير P&L بالمركز
        </a>
        @can('accounts.create')
        <a href="{{ route('cost-centers.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>مركز جديد
        </a>
        @endcan
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover dt-table align-middle mb-0" style="width:100%">
            <thead class="table-light">
                <tr>
                    <th>الكود</th><th>الاسم</th><th>الفرع</th><th>ملاحظات</th><th>الحالة</th><th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($centers as $c)
            <tr>
                <td><code>{{ $c->code }}</code></td>
                <td><strong>{{ $c->name }}</strong></td>
                <td>{{ $c->branch?->name ?? '—' }}</td>
                <td class="text-muted small">{{ Str::limit($c->notes, 50) }}</td>
                <td><span class="badge bg-{{ $c->is_active ? 'success':'secondary' }}">{{ $c->is_active ? 'نشط':'موقوف' }}</span></td>
                <td>
                    @can('accounts.edit')
                    <a href="{{ route('cost-centers.edit', $c) }}" class="btn btn-sm btn-outline-primary btn-action">
                        <i class="bi bi-pencil"></i>
                    </a>
                    @endcan
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
