@extends('layouts.app')
@section('page-title', 'تقرير P&L بمراكز التكلفة')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between">
        <h5 class="mb-0"><i class="bi bi-diagram-2 text-info me-1"></i>P&L بمراكز التكلفة</h5>
        <a href="{{ route('cost-centers.index') }}" class="btn btn-sm btn-outline-secondary">رجوع</a>
    </div>
    <div class="card-body border-bottom py-2 no-print">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">من تاريخ</label>
                <input type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small">إلى تاريخ</label>
                <input type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>عرض</button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>مركز التكلفة</th>
                        <th class="text-end">الإيرادات</th>
                        <th class="text-end">المصروفات</th>
                        <th class="text-end">صافي الربح</th>
                        <th class="text-center">هامش الربح</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($data as $row)
                <tr>
                    <td>
                        <strong>{{ $row['center']->name }}</strong>
                        <div class="text-muted small">{{ $row['center']->code }}</div>
                    </td>
                    <td class="text-end font-monospace text-success">{{ number_format($row['revenue'], 2) }}</td>
                    <td class="text-end font-monospace text-danger">{{ number_format($row['expenses'], 2) }}</td>
                    <td class="text-end font-monospace fw-bold {{ $row['net'] < 0 ? 'text-danger':'text-success' }}">
                        {{ number_format($row['net'], 2) }} {{ $currency }}
                    </td>
                    <td class="text-center">
                        @php $margin = $row['revenue'] > 0 ? round($row['net'] / $row['revenue'] * 100, 1) : 0; @endphp
                        <span class="badge bg-{{ $margin >= 20 ? 'success' : ($margin >= 0 ? 'warning text-dark' : 'danger') }}">
                            {{ $margin }}%
                        </span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">
                    لا توجد بيانات — يجب ربط القيود بمراكز التكلفة أولاً
                </td></tr>
                @endforelse
                </tbody>
                @if($data->count())
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td>الإجمالي</td>
                        <td class="text-end font-monospace">{{ number_format($data->sum('revenue'), 2) }}</td>
                        <td class="text-end font-monospace">{{ number_format($data->sum('expenses'), 2) }}</td>
                        <td class="text-end font-monospace">{{ number_format($data->sum('net'), 2) }} {{ $currency }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
