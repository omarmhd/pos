@extends('layouts.app')
@section('page-title', 'الفروع')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-building-fill-check text-primary me-2"></i>الفروع</h5>
        @can('branches.manage')
        <a href="{{ route('branches.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> فرع جديد
        </a>
        @endcan
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%">
                <thead class="table-light">
                <tr>
                    <th>الكود</th>
                    <th>الاسم</th>
                    <th>النوع</th>
                    <th class="text-center">المخازن</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center">افتراضي</th>
                    <th class="text-center">إجراءات</th>
                </tr>
                </thead>
                <tbody>
                @foreach($branches as $branch)
                <tr>
                    <td><code>{{ $branch->code }}</code></td>
                    <td class="fw-semibold">{{ $branch->name }}</td>
                    <td><span class="badge bg-secondary">{{ $branch->typeLabel() }}</span></td>
                    <td class="text-center">
                        <a href="{{ route('warehouses.index') }}?branch={{ $branch->id }}" class="badge bg-info text-dark">
                            {{ $branch->warehouses_count }} مخزن
                        </a>
                    </td>
                    <td class="text-center">
                        @if($branch->is_active)
                            <span class="badge bg-success">نشط</span>
                        @else
                            <span class="badge bg-secondary">موقوف</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($branch->is_default)
                            <i class="bi bi-star-fill text-warning" title="الفرع الافتراضي"></i>
                        @endif
                    </td>
                    <td class="text-center">
                        @can('branches.manage')
                        <a href="{{ route('branches.edit', $branch) }}" class="btn btn-sm btn-primary btn-action">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @if(!$branch->is_default)
                        <form action="{{ route('branches.destroy', $branch) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('حذف الفرع؟')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger btn-action"><i class="bi bi-trash"></i></button>
                        </form>
                        @endif
                        @endcan
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
