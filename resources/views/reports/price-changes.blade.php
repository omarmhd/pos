@extends('layouts.app')

@section('page-title', 'تغيّر أسعار التكلفة')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-graph-up-arrow text-info"></i> سجل تغيّر أسعار التكلفة</h5>
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> التقارير</a>
</div>

<form method="GET" class="card card-body mb-3 d-print-none">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label mb-1">من تاريخ</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label mb-1">إلى تاريخ</label>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary"><i class="bi bi-funnel"></i> تطبيق</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="تغيّر أسعار التكلفة">
                <thead><tr>
                    <th>التاريخ</th><th>الصنف</th><th>التكلفة السابقة ({{ $cur }})</th>
                    <th>التكلفة الجديدة ({{ $cur }})</th><th>الفرق</th><th>الطريقة</th><th>بواسطة</th>
                </tr></thead>
                <tbody>
                    @foreach($changes as $c)
                    @php $diff = (float)$c->new_cost - (float)$c->old_cost; @endphp
                    <tr>
                        <td>{{ $c->created_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $c->product?->name ?? '#' . $c->product_id }}</td>
                        <td>{{ number_format($c->old_cost, 2) }}</td>
                        <td>{{ number_format($c->new_cost, 2) }}</td>
                        <td class="{{ $diff > 0 ? 'text-danger' : ($diff < 0 ? 'text-success' : '') }}">
                            {{ ($diff > 0 ? '+' : '') . number_format($diff, 2) }}
                        </td>
                        <td><span class="badge bg-light text-dark">{{ $c->methodLabel() }}</span></td>
                        <td>{{ $c->changedBy?->name ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($changes->isEmpty())
            <p class="text-muted text-center mb-0">لا تغييرات في التكلفة خلال هذه المدة.</p>
        @endif
    </div>
</div>
@endsection
