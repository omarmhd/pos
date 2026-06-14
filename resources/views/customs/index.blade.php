@extends('layouts.app')

@section('page-title', 'الإقرارات الجمركية')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-box-arrow-in-down text-primary"></i> الإقرارات الجمركية (ضريبة الواردات)</h5>
        @can('customs.manage')
        <a href="{{ route('customs-declarations.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> إقرار جديد
        </a>
        @endcan
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="الإقرارات الجمركية">
                <thead>
                    <tr>
                        <th>الرقم</th>
                        <th>التاريخ</th>
                        <th>المورد</th>
                        <th>رقم البيان الجمركي</th>
                        <th>قيمة الواردات/الرسوم ({{ $currency }})</th>
                        <th>ض.ق.م الواردات</th>
                        <th>الكشف</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($declarations as $d)
                    <tr>
                        <td><a href="{{ route('customs-declarations.show', $d) }}">{{ $d->declaration_number }}</a></td>
                        <td>{{ $d->declaration_date->format('Y-m-d') }}</td>
                        <td>{{ $d->supplier?->name ?? $d->vendor_name ?? '—' }}</td>
                        <td>{{ $d->customs_ref ?? '—' }}</td>
                        <td>{{ number_format($d->total_amount, 2) }}</td>
                        <td>{{ number_format($d->tax_amount, 2) }}</td>
                        <td>
                            @if($d->res_statement_id)
                                <span class="badge bg-success">{{ $d->resStatement?->number ?? 'مُدرَج' }}</span>
                            @else
                                <span class="badge bg-secondary">غير مُدرَج</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('customs-declarations.show', $d) }}" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                            @can('customs.manage')
                            @unless($d->res_statement_id)
                            <form action="{{ route('customs-declarations.destroy', $d) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('حذف الإقرار الجمركي؟')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            @endunless
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
