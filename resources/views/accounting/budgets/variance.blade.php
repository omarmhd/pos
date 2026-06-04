@extends('layouts.app')
@section('page-title', 'تقرير الانحرافات — ' . $budget->name)

@section('content')
@php
    $monthNames = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',
                   7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-bar-chart-steps text-warning me-2"></i>
        {{ $budget->name }} — {{ $monthNames[$month] }} {{ $budget->year }}
    </h4>
    <a href="{{ route('budgets.index') }}" class="btn btn-sm btn-outline-secondary">رجوع</a>
</div>

{{-- Month selector --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">الشهر</label>
                <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($monthNames as $m => $name)
                        <option value="{{ $m }}" {{ $month == $m ? 'selected':'' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

{{-- Summary KPIs --}}
@php
    $totalBudgeted = collect($rows)->sum('budgeted');
    $totalActual   = collect($rows)->sum('actual');
    $totalVariance = collect($rows)->sum('variance');
@endphp
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card primary">
            <h6>الموازنة التقديرية</h6>
            <h3>{{ number_format($totalBudgeted, 2) }}</h3>
            <small>{{ $currency }}</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card {{ $totalActual > $totalBudgeted * 1.1 ? 'danger' : 'success' }}">
            <h6>الفعلي</h6>
            <h3>{{ number_format($totalActual, 2) }}</h3>
            <small>{{ $currency }}</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card {{ abs($totalVariance) < $totalBudgeted * 0.05 ? 'success' : ($totalVariance > 0 ? 'warning' : 'danger') }}">
            <h6>الانحراف</h6>
            <h3>{{ number_format(abs($totalVariance), 2) }}</h3>
            <small>{{ $totalVariance > 0 ? 'زيادة' : 'نقص' }} — {{ $currency }}</small>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0 dt-table" style="width:100%"
                   data-title="تقرير الانحرافات">
                <thead class="table-dark">
                    <tr>
                        <th>الحساب</th>
                        <th>النوع</th>
                        <th class="text-end">الموازنة</th>
                        <th class="text-end">الفعلي</th>
                        <th class="text-end">الانحراف</th>
                        <th class="text-center">% تنفيذ</th>
                        <th class="text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $row)
                <tr class="{{ $row['over'] ? 'table-warning' : '' }}">
                    <td>
                        <strong>{{ $row['account']?->name }}</strong>
                        <div class="text-muted small">{{ $row['account']?->code }}</div>
                    </td>
                    <td>
                        <span class="badge bg-{{ $row['account']?->type === 'revenue' ? 'success':'danger' }} small">
                            {{ $row['account']?->type === 'revenue' ? 'إيراد' : 'مصروف' }}
                        </span>
                    </td>
                    <td class="text-end font-monospace">{{ number_format($row['budgeted'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($row['actual'], 2) }}</td>
                    <td class="text-end font-monospace {{ $row['over'] ? 'text-danger fw-bold' : 'text-success' }}">
                        {{ $row['variance'] > 0 ? '+' : '' }}{{ number_format($row['variance'], 2) }}
                    </td>
                    <td class="text-center">
                        @if($row['pct'] !== null)
                        <div class="progress" style="height:14px;min-width:80px">
                            <div class="progress-bar bg-{{ $row['pct'] > 110 ? 'danger' : ($row['pct'] > 90 ? 'success' : 'warning') }}"
                                 style="width:{{ min(100, $row['pct']) }}%">
                                {{ $row['pct'] }}%
                            </div>
                        </div>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($row['over'])
                            <span class="badge bg-danger">تجاوز</span>
                        @elseif($row['pct'] >= 90)
                            <span class="badge bg-success">جيد</span>
                        @else
                            <span class="badge bg-secondary">دون الموازنة</span>
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
