@extends('layouts.app')
@section('page-title', 'الموازنات التقديرية')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-calculator text-primary me-2"></i>الموازنات التقديرية</h4>
    @can('accounting.post')
    <a href="{{ route('budgets.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>موازنة جديدة
    </a>
    @endcan
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover dt-table align-middle mb-0" style="width:100%">
            <thead class="table-light">
                <tr>
                    <th>الاسم</th><th>السنة</th><th>الفرع</th><th>الحالة</th><th>أُنشئت بواسطة</th><th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($budgets as $b)
            <tr>
                <td><strong>{{ $b->name }}</strong></td>
                <td>{{ $b->year }}</td>
                <td>{{ $b->branch?->name ?? 'كل الفروع' }}</td>
                <td><span class="badge bg-{{ $b->is_active ? 'success':'secondary' }}">{{ $b->is_active ? 'نشطة':'معطلة' }}</span></td>
                <td class="text-muted small">{{ $b->createdBy?->name }}</td>
                <td>
                    <a href="{{ route('budgets.show', $b) }}" class="btn btn-sm btn-info btn-action"><i class="bi bi-eye"></i></a>
                    <a href="{{ route('budgets.variance', $b) }}" class="btn btn-sm btn-warning btn-action" title="تقرير الانحرافات">
                        <i class="bi bi-bar-chart-steps"></i>
                    </a>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
