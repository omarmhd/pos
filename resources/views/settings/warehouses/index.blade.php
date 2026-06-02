@extends('layouts.app')
@section('page-title', 'المخازن')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-archive-fill text-success me-2"></i>المخازن</h5>
        @can('branches.manage')
        <a href="{{ route('warehouses.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i> مخزن جديد
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
                    <th>الفرع</th>
                    <th class="text-center">عدد الأصناف</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center">افتراضي</th>
                    <th class="text-center">إجراءات</th>
                </tr>
                </thead>
                <tbody>
                @foreach($warehouses as $wh)
                <tr>
                    <td><code>{{ $wh->code }}</code></td>
                    <td class="fw-semibold">{{ $wh->name }}</td>
                    <td>
                        @if($wh->branch)
                            <span class="badge bg-light text-dark border">{{ $wh->branch->name }}</span>
                        @else
                            <span class="text-muted small">— مستقل —</span>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format($wh->stock_levels_count) }}</td>
                    <td class="text-center">
                        @if($wh->is_active)
                            <span class="badge bg-success">نشط</span>
                        @else
                            <span class="badge bg-secondary">موقوف</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($wh->is_default)
                            <i class="bi bi-star-fill text-warning" title="المخزن الافتراضي للنظام"></i>
                        @endif
                    </td>
                    <td class="text-center">
                        <a href="{{ route('warehouses.stock', $wh) }}" class="btn btn-sm btn-info btn-action" title="رصيد المخزون">
                            <i class="bi bi-boxes"></i>
                        </a>
                        @can('branches.manage')
                        <a href="{{ route('warehouses.edit', $wh) }}" class="btn btn-sm btn-success btn-action">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @if(!$wh->is_default)
                        <form action="{{ route('warehouses.destroy', $wh) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('حذف المخزن؟')">
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
