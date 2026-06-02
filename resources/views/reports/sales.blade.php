@extends('layouts.app')
@section('page-title', 'تقرير المبيعات')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-graph-up text-primary"></i> تقرير المبيعات</h5>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary no-print">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>
    <div class="card-body">

        <form method="GET" class="row g-2 mb-4 no-print align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold small">من تاريخ</label>
                <input type="date" name="date_from" class="form-control"
                       value="{{ request('date_from', \Carbon\Carbon::parse($dateFrom)->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">إلى تاريخ</label>
                <input type="date" name="date_to" class="form-control"
                       value="{{ request('date_to', \Carbon\Carbon::parse($dateTo)->format('Y-m-d')) }}">
            </div>
            @include('components.branch-filter')
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> عرض
                </button>
            </div>
        </form>

        {{-- Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <h6>إجمالي المبيعات</h6>
                    <h3>{{ number_format($totalSales, 2) }} {{ $cur }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <h6>عدد العمليات</h6>
                    <h3>{{ $totalTransactions }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <h6>إجمالي الخصومات</h6>
                    <h3>{{ number_format($totalDiscount, 2) }} {{ $cur }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background:linear-gradient(135deg,#6f42c1,#8b5cf6);color:#fff">
                    <h6>إجمالي الضريبة</h6>
                    <h3>{{ number_format($totalTax, 2) }} {{ $cur }}</h3>
                </div>
            </div>
        </div>

        {{-- Print header (only shown when printing) --}}
        <div class="d-none d-print-block mb-3 text-center border-bottom pb-2">
            <strong>تقرير المبيعات</strong> —
            من {{ \Carbon\Carbon::parse($dateFrom)->format('Y-m-d') }}
            إلى {{ \Carbon\Carbon::parse($dateTo)->format('Y-m-d') }}
        </div>

        <div class="table-responsive">
            <table class="table table-hover dt-table" data-title="تقرير المبيعات">
                <thead class="table-light">
                    <tr>
                        <th>التاريخ</th>
                        <th class="text-center">عدد العمليات</th>
                        <th class="text-end">الخصم ({{ $cur }})</th>
                        <th class="text-end">الضريبة ({{ $cur }})</th>
                        <th class="text-end">الإجمالي ({{ $cur }})</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td>{{ $sale->date }}</td>
                        <td class="text-center"><span class="badge bg-primary">{{ $sale->count }}</span></td>
                        <td class="text-end">{{ number_format($sale->discount, 2) }}</td>
                        <td class="text-end">{{ number_format($sale->tax, 2) }}</td>
                        <td class="text-end fw-bold">{{ number_format($sale->total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">لا توجد مبيعات في هذه الفترة</td>
                    </tr>
                @endforelse
                </tbody>
                @if($sales->count())
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td>الإجمالي</td>
                        <td class="text-center">{{ $totalTransactions }}</td>
                        <td class="text-end">{{ number_format($totalDiscount, 2) }}</td>
                        <td class="text-end">{{ number_format($totalTax, 2) }}</td>
                        <td class="text-end">{{ number_format($totalSales, 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

    </div>
</div>
@endsection
