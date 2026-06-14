@extends('layouts.app')

@section('page-title', 'العملات')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-currency-exchange"></i> العملات وأسعار الصرف</h5>
        @can('currencies.manage')
        <a href="{{ route('currencies.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> عملة جديدة
        </a>
        @endcan
    </div>
    <div class="card-body">
        <div class="alert alert-info py-2 small">
            <i class="bi bi-info-circle"></i>
            المحاسبة تتم دائماً بالعملة الأساسية. أسعار الأصناف المسجلة بعملة أجنبية تُحوَّل
            للعملة الأساسية عند حفظ الصنف باستخدام سعر الصرف الحالي.
        </div>
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="العملات">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>الاسم</th>
                        <th>الرمز</th>
                        <th>سعر الصرف (بالعملة الأساسية)</th>
                        <th>أصناف مرتبطة</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($currencies as $c)
                    <tr>
                        <td><strong>{{ $c->code }}</strong> @if($c->is_base)<span class="badge bg-primary">أساسية</span>@endif</td>
                        <td>{{ $c->name }}</td>
                        <td>{{ $c->symbol }}</td>
                        <td>{{ rtrim(rtrim(number_format($c->exchange_rate, 6), '0'), '.') }}</td>
                        <td>{{ $c->products_count }}</td>
                        <td>
                            @if($c->is_active)
                                <span class="badge bg-success">نشطة</span>
                            @else
                                <span class="badge bg-secondary">موقوفة</span>
                            @endif
                        </td>
                        <td>
                            @can('currencies.manage')
                            <a href="{{ route('currencies.edit', $c) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                            @if(!$c->is_base)
                            <form action="{{ route('currencies.destroy', $c) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('حذف العملة؟')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
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
