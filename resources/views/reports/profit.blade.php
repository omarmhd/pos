@extends('layouts.app')
@section('page-title', 'تقرير الأرباح والخسائر')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-currency-dollar text-success"></i> تقرير الأرباح والخسائر</h5>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary no-print">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>
    <div class="card-body">

        <form method="GET" class="row g-3 mb-4 no-print">
            <div class="col-md-4">
                <label class="form-label fw-semibold small">من تاريخ</label>
                <input type="date" name="date_from" class="form-control"
                       value="{{ request('date_from', \Carbon\Carbon::parse($dateFrom)->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">إلى تاريخ</label>
                <input type="date" name="date_to" class="form-control"
                       value="{{ request('date_to', \Carbon\Carbon::parse($dateTo)->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> عرض التقرير
                </button>
            </div>
        </form>

        {{-- Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-success h-100">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-arrow-up-circle fs-2 text-success mb-2"></i>
                        <div class="text-muted small">إجمالي الإيرادات</div>
                        <h4 class="text-success fw-bold">{{ number_format($revenue, 2) }} {{ $cur }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger h-100">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-arrow-down-circle fs-2 text-danger mb-2"></i>
                        <div class="text-muted small">إجمالي التكاليف (COGS)</div>
                        <h4 class="text-danger fw-bold">{{ number_format($cost, 2) }} {{ $cur }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card {{ $profit >= 0 ? 'border-primary' : 'border-danger' }} h-100">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-cash-stack fs-2 {{ $profit >= 0 ? 'text-primary' : 'text-danger' }} mb-2"></i>
                        <div class="text-muted small">صافي الربح</div>
                        <h4 class="{{ $profit >= 0 ? 'text-primary' : 'text-danger' }} fw-bold">
                            {{ number_format($profit, 2) }} {{ $cur }}
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info h-100">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-percent fs-2 text-info mb-2"></i>
                        <div class="text-muted small">هامش الربح</div>
                        <h4 class="text-info fw-bold">{{ number_format($profitMargin, 2) }}%</h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- Daily Breakdown --}}
        @if($dailyProfit->count())
        <div class="d-none d-print-block mb-3 text-center border-bottom pb-2">
            <strong>تقرير الأرباح والخسائر</strong> —
            من {{ \Carbon\Carbon::parse($dateFrom)->format('Y-m-d') }}
            إلى {{ \Carbon\Carbon::parse($dateTo)->format('Y-m-d') }}
        </div>

        <h6 class="fw-bold text-muted mb-3 mt-2">
            <i class="bi bi-calendar3"></i> التفصيل اليومي
        </h6>
        <div class="table-responsive">
            <table class="table table-hover dt-table" data-title="تقرير الأرباح اليومي">
                <thead class="table-light">
                    <tr>
                        <th>التاريخ</th>
                        <th class="text-center">عدد الفواتير</th>
                        <th class="text-end">الإيرادات ({{ $cur }})</th>
                        <th class="text-end">التكاليف ({{ $cur }})</th>
                        <th class="text-end">صافي الربح ({{ $cur }})</th>
                        <th class="text-end">هامش الربح</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($dailyProfit as $day)
                @php
                    $dayMargin = $day->revenue > 0 ? ($day->profit / $day->revenue) * 100 : 0;
                @endphp
                    <tr>
                        <td>{{ $day->date }}</td>
                        <td class="text-center"><span class="badge bg-secondary">{{ $day->tx_count }}</span></td>
                        <td class="text-end text-success">{{ number_format($day->revenue, 2) }}</td>
                        <td class="text-end text-danger">{{ number_format($day->cost, 2) }}</td>
                        <td class="text-end fw-bold {{ $day->profit >= 0 ? 'text-primary' : 'text-danger' }}">
                            {{ number_format($day->profit, 2) }}
                        </td>
                        <td class="text-end">
                            <span class="badge {{ $dayMargin >= 20 ? 'bg-success' : ($dayMargin >= 10 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                {{ number_format($dayMargin, 1) }}%
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td>الإجمالي</td>
                        <td class="text-center">{{ $dailyProfit->sum('tx_count') }}</td>
                        <td class="text-end text-success">{{ number_format($revenue, 2) }}</td>
                        <td class="text-end text-danger">{{ number_format($cost, 2) }}</td>
                        <td class="text-end {{ $profit >= 0 ? 'text-primary' : 'text-danger' }}">
                            {{ number_format($profit, 2) }}
                        </td>
                        <td class="text-end">{{ number_format($profitMargin, 1) }}%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @else
        <div class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-2"></i>
            <p class="mt-2">لا توجد بيانات مبيعات في هذه الفترة</p>
        </div>
        @endif

    </div>
</div>
@endsection
