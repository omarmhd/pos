@extends('layouts.app')

@section('page-title', 'تقرير خصم المصدر')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-percent text-warning"></i> خصم المصدر (المستقطع لضريبة الدخل)</h5>
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
        <div class="col-md-3 text-md-end">
            <span class="badge bg-warning text-dark fs-6">إجمالي المستقطع: {{ number_format($total, 2) }} {{ $cur }}</span>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="خصم المصدر">
                <thead><tr>
                    <th>رقم السند</th><th>التاريخ</th><th>المستلَم منه</th>
                    <th>الحساب المقابل</th><th>النسبة %</th><th>المستقطع ({{ $cur }})</th>
                </tr></thead>
                <tbody>
                    @foreach($vouchers as $v)
                    <tr>
                        <td class="font-monospace">{{ $v->voucher_number }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($v->voucher_date)->format('Y-m-d') }}</td>
                        <td>{{ $v->received_from }}</td>
                        <td>{{ $v->account?->name ?? '—' }}</td>
                        <td>{{ number_format($v->source_discount_rate, 2) }}%</td>
                        <td class="fw-bold">{{ number_format($v->source_discount_amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($vouchers->isEmpty())
            <p class="text-muted text-center mb-0">لا مستقطعات خصم مصدر خلال هذه المدة.</p>
        @endif
    </div>
</div>
@endsection
