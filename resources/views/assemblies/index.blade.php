@extends('layouts.app')

@section('page-title', 'التصنيع والتجميع')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-gear-wide-connected"></i> مستندات التصنيع والتجميع</h5>
        @can('assemblies.create')
        <a href="{{ route('assemblies.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> أمر تصنيع جديد
        </a>
        @endcan
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="مستندات التصنيع">
                <thead>
                    <tr>
                        <th>الرقم</th>
                        <th>التاريخ</th>
                        <th>الصنف المنتَج</th>
                        <th>الكمية</th>
                        <th>تكلفة الوحدة</th>
                        <th>إجمالي التكلفة ({{ $currency }})</th>
                        <th>المخزن</th>
                        <th>المستخدم</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($assemblies as $a)
                    <tr>
                        <td><a href="{{ route('assemblies.show', $a) }}">{{ $a->number }}</a></td>
                        <td>{{ $a->assembly_date->format('Y-m-d') }}</td>
                        <td>{{ $a->product->name }}</td>
                        <td>{{ number_format($a->quantity, 2) }}</td>
                        <td>{{ number_format($a->unit_cost, 4) }}</td>
                        <td>{{ number_format($a->total_cost, 2) }}</td>
                        <td>{{ $a->warehouse?->name ?? '—' }}</td>
                        <td>{{ $a->user?->name ?? '—' }}</td>
                        <td>
                            @if($a->is_posted)
                                <span class="badge bg-success">مُرحَّل</span>
                            @else
                                <span class="badge bg-secondary">غير مُرحَّل</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
