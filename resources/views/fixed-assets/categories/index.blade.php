@extends('layouts.app')
@section('page-title', 'فئات الأصول الثابتة')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-tags text-primary me-2"></i>فئات الأصول الثابتة</h5>
        @can('fixed_assets.create')
        <a href="{{ route('fixed-asset-categories.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> فئة جديدة
        </a>
        @endcan
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%">
                <thead class="table-light">
                <tr>
                    <th>الكود</th><th>الفئة</th><th>حساب الأصل</th>
                    <th>مجمع الاستهلاك</th><th>مصروف الاستهلاك</th>
                    <th>الطريقة</th><th>العمر</th><th>الأصول</th><th>إجراءات</th>
                </tr>
                </thead>
                <tbody>
                @foreach($categories as $cat)
                <tr>
                    <td><code>{{ $cat->code }}</code></td>
                    <td class="fw-semibold">{{ $cat->name }}</td>
                    <td class="small">{{ $cat->assetAccount?->code }} — {{ $cat->assetAccount?->name }}</td>
                    <td class="small">{{ $cat->accumulatedDepAccount?->code }} — {{ $cat->accumulatedDepAccount?->name }}</td>
                    <td class="small">{{ $cat->depreciationExpAccount?->code }} — {{ $cat->depreciationExpAccount?->name }}</td>
                    <td>{{ $cat->depreciation_method === 'straight_line' ? 'ثابت' : 'متناقص' }}</td>
                    <td>{{ $cat->usefulLifeLabel() }}</td>
                    <td class="text-center"><span class="badge bg-info text-dark">{{ $cat->assets_count }}</span></td>
                    <td>
                        @can('fixed_assets.create')
                        <a href="{{ route('fixed-asset-categories.edit', $cat) }}" class="btn btn-sm btn-primary btn-action">
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
</div>
@endsection
