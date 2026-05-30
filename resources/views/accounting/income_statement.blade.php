@extends('layouts.app')

@section('title', 'قائمة الدخل')
@section('page-title', 'قائمة الدخل')

@section('content')
<div class="container-fluid">

    {{-- Date Filter --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">من تاريخ</label>
                    <input type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">إلى تاريخ</label>
                    <input type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> عرض
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Statement Header --}}
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white text-center py-3">
            <h4 class="mb-1"><i class="bi bi-bar-chart-line"></i> قائمة الدخل</h4>
            <small>للفترة من {{ $from->format('Y/m/d') }} إلى {{ $to->format('Y/m/d') }}</small>
        </div>

        <div class="card-body p-0">
            <table class="table table-hover mb-0 fs-6">

                {{-- ===== REVENUE ===== --}}
                <thead class="table-success">
                    <tr>
                        <th colspan="2" class="ps-4 py-3"><i class="bi bi-plus-circle"></i> الإيرادات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($revenue as $row)
                    <tr>
                        <td class="ps-5 text-muted">
                            <span class="badge bg-secondary me-2">{{ $row['account']->code }}</span>
                            {{ $row['account']->name }}
                        </td>
                        <td class="text-end pe-4 text-success fw-semibold">
                            {{ number_format($row['amount'], 2) }} {{ $currency }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="text-center text-muted ps-5">لا توجد إيرادات في هذه الفترة</td></tr>
                    @endforelse
                    <tr class="table-success fw-bold border-top border-2">
                        <td class="ps-4">إجمالي الإيرادات</td>
                        <td class="text-end pe-4">{{ number_format($totalRevenue, 2) }} {{ $currency }}</td>
                    </tr>
                </tbody>

                {{-- ===== COGS ===== --}}
                <thead class="table-warning">
                    <tr>
                        <th colspan="2" class="ps-4 py-3"><i class="bi bi-box-seam"></i> تكلفة البضاعة المباعة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cogs as $row)
                    <tr>
                        <td class="ps-5 text-muted">
                            <span class="badge bg-secondary me-2">{{ $row['account']->code }}</span>
                            {{ $row['account']->name }}
                        </td>
                        <td class="text-end pe-4 text-warning fw-semibold">
                            ({{ number_format($row['amount'], 2) }} {{ $currency }})
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="text-center text-muted ps-5">لا توجد تكاليف مباعة في هذه الفترة</td></tr>
                    @endforelse
                    <tr class="table-warning fw-bold border-top border-2">
                        <td class="ps-4">إجمالي تكلفة البضاعة المباعة</td>
                        <td class="text-end pe-4">({{ number_format($totalCogs, 2) }} {{ $currency }})</td>
                    </tr>
                </tbody>

                {{-- ===== GROSS PROFIT ===== --}}
                <tbody>
                    <tr class="bg-light fw-bold border-top border-3 border-dark fs-6">
                        <td class="ps-4 py-3">
                            <i class="bi bi-graph-up-arrow text-primary"></i> مجمل الربح
                            <small class="text-muted fw-normal ms-2">(هامش {{ $grossMargin }}%)</small>
                        </td>
                        <td class="text-end pe-4 py-3 {{ $grossProfit >= 0 ? 'text-primary' : 'text-danger' }}">
                            {{ number_format($grossProfit, 2) }} {{ $currency }}
                        </td>
                    </tr>
                </tbody>

                {{-- ===== OPERATING EXPENSES ===== --}}
                <thead class="table-danger">
                    <tr>
                        <th colspan="2" class="ps-4 py-3"><i class="bi bi-dash-circle"></i> المصروفات التشغيلية</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($opex as $row)
                    <tr>
                        <td class="ps-5 text-muted">
                            <span class="badge bg-secondary me-2">{{ $row['account']->code }}</span>
                            {{ $row['account']->name }}
                        </td>
                        <td class="text-end pe-4 text-danger fw-semibold">
                            ({{ number_format($row['amount'], 2) }} {{ $currency }})
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="text-center text-muted ps-5">لا توجد مصروفات تشغيلية في هذه الفترة</td></tr>
                    @endforelse
                    <tr class="table-danger fw-bold border-top border-2">
                        <td class="ps-4">إجمالي المصروفات التشغيلية</td>
                        <td class="text-end pe-4">({{ number_format($totalOpex, 2) }} {{ $currency }})</td>
                    </tr>
                </tbody>

                {{-- ===== NET INCOME ===== --}}
                <tbody>
                    <tr class="fw-bold fs-5 border-top border-3 border-dark {{ $netIncome >= 0 ? 'table-success' : 'table-danger' }}">
                        <td class="ps-4 py-3">
                            <i class="bi bi-trophy {{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}"></i>
                            {{ $netIncome >= 0 ? 'صافي الربح' : 'صافي الخسارة' }}
                            <small class="fw-normal ms-2">(صافي الهامش {{ $netMargin }}%)</small>
                        </td>
                        <td class="text-end pe-4 py-3 {{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format(abs($netIncome), 2) }} {{ $currency }}
                        </td>
                    </tr>
                </tbody>

            </table>
        </div>

        {{-- Summary Cards --}}
        <div class="card-footer bg-light">
            <div class="row text-center g-2">
                <div class="col-md-3">
                    <div class="p-2 border rounded bg-white">
                        <div class="text-success fw-bold fs-6">{{ number_format($totalRevenue, 2) }} {{ $currency }}</div>
                        <small class="text-muted">إجمالي الإيرادات</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 border rounded bg-white">
                        <div class="text-warning fw-bold fs-6">{{ number_format($totalCogs, 2) }} {{ $currency }}</div>
                        <small class="text-muted">تكلفة المبيعات</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 border rounded bg-white">
                        <div class="text-danger fw-bold fs-6">{{ number_format($totalOpex, 2) }} {{ $currency }}</div>
                        <small class="text-muted">المصروفات التشغيلية</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 border rounded bg-white">
                        <div class="fw-bold fs-6 {{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($netIncome, 2) }} {{ $currency }}
                        </div>
                        <small class="text-muted">{{ $netIncome >= 0 ? 'صافي الربح' : 'صافي الخسارة' }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
