@extends('layouts.app')
@section('page-title', 'تقرير المشتريات')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-bag-plus text-primary"></i> تقرير المشتريات</h5>
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

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card primary">
                    <h6>إجمالي المشتريات</h6>
                    <h3>{{ number_format($totalPurchases, 2) }} {{ $cur }}</h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card success">
                    <h6>المدفوع</h6>
                    <h3>{{ number_format($totalPaid, 2) }} {{ $cur }}</h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card warning">
                    <h6>المتبقي</h6>
                    <h3>{{ number_format($totalRemaining, 2) }} {{ $cur }}</h3>
                </div>
            </div>
        </div>

        <div class="d-none d-print-block mb-3 text-center border-bottom pb-2">
            <strong>تقرير المشتريات</strong> —
            من {{ \Carbon\Carbon::parse($dateFrom)->format('Y-m-d') }}
            إلى {{ \Carbon\Carbon::parse($dateTo)->format('Y-m-d') }}
        </div>

        @if($purchases->isEmpty())
        <div class="text-center text-muted py-5 border rounded">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            لا توجد مشتريات في هذه الفترة
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="تقرير المشتريات">
                <thead class="table-light">
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>المورد</th>
                        <th class="text-end">الإجمالي ({{ $cur }})</th>
                        <th class="text-end">المدفوع ({{ $cur }})</th>
                        <th class="text-end">المتبقي ({{ $cur }})</th>
                        <th class="text-center">حالة الدفع</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($purchases as $purchase)
                    <tr>
                        <td><strong>{{ $purchase->invoice_number }}</strong></td>
                        <td>{{ $purchase->supplier->name }}</td>
                        <td class="text-end">{{ number_format($purchase->total_amount, 2) }}</td>
                        <td class="text-end text-success">{{ number_format($purchase->paid_amount, 2) }}</td>
                        <td class="text-end {{ $purchase->remainingAmount() > 0 ? 'text-danger fw-bold' : '' }}">
                            {{ number_format($purchase->remainingAmount(), 2) }}
                        </td>
                        <td class="text-center">
                            @if($purchase->payment_status == 'paid')
                                <span class="badge bg-success">مدفوع</span>
                            @elseif($purchase->payment_status == 'partial')
                                <span class="badge bg-warning text-dark">جزئي</span>
                            @else
                                <span class="badge bg-danger">غير مدفوع</span>
                            @endif
                        </td>
                        <td>{{ $purchase->created_at->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
                </tbody>
                @if($purchases->count())
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="2">الإجمالي</td>
                        <td class="text-end">{{ number_format($totalPurchases, 2) }}</td>
                        <td class="text-end text-success">{{ number_format($totalPaid, 2) }}</td>
                        <td class="text-end text-danger">{{ number_format($totalRemaining, 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>

            </table>
        </div>
        @endif

    </div>
</div>
@endsection
