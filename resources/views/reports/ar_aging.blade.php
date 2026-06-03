@extends('layouts.app')
@section('title', 'تحليل أعمار الذمم المدينة')
@section('page-title', 'تحليل أعمار الذمم المدينة (AR Aging)')

@section('content')
<div class="container-fluid">

    {{-- Header + Filters --}}
    <div class="d-flex justify-content-between align-items-center mb-3 no-print flex-wrap gap-2">
        <form method="GET" class="row g-2 align-items-end mb-0">
            @include('components.branch-filter')
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> تصفية
                </button>
                @if(request('branch_id'))
                    <a href="{{ route('reports.ar_aging') }}" class="btn btn-outline-danger btn-sm ms-1">
                        <i class="bi bi-x"></i>
                    </a>
                @endif
            </div>
        </form>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>

    {{-- Bucket Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-success bg-opacity-10 text-center h-100">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-success">{{ number_format($buckets['current'], 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">جارية (0–30 يوم)</div>
                    <div class="small text-muted">{{ collect($rows)->where('bucket','current')->count() }} فاتورة</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-warning bg-opacity-10 text-center h-100">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-warning">{{ number_format($buckets['31_60'], 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">31–60 يوم</div>
                    <div class="small text-muted">{{ collect($rows)->where('bucket','31_60')->count() }} فاتورة</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 text-center h-100" style="background:#fff3e0;">
                <div class="card-body">
                    <div class="fs-5 fw-bold" style="color:#e65100;">{{ number_format($buckets['61_90'], 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">61–90 يوم</div>
                    <div class="small text-muted">{{ collect($rows)->where('bucket','61_90')->count() }} فاتورة</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-danger bg-opacity-10 text-center h-100">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-danger">{{ number_format($buckets['over_90'], 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">أكثر من 90 يوم</div>
                    <div class="small text-muted">{{ collect($rows)->where('bucket','over_90')->count() }} فاتورة</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Total --}}
    <div class="alert alert-secondary d-flex justify-content-between align-items-center mb-4">
        <strong><i class="bi bi-calculator"></i> إجمالي الذمم المستحقة:</strong>
        <strong class="fs-5 text-danger">{{ number_format($totalOutstanding, 2) }} {{ $cur }}</strong>
    </div>

    <div class="d-none d-print-block mb-3 text-center border-bottom pb-2">
        <strong>تحليل أعمار الذمم المدينة</strong> — {{ now()->format('Y-m-d') }}
    </div>

    {{-- Detail Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold"><i class="bi bi-table"></i> تفاصيل الفواتير المستحقة</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0 dt-table" data-title="تقادم الذمم المدينة">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">العميل</th>
                        <th>رقم الفاتورة</th>
                        <th>تاريخ الفاتورة</th>
                        <th class="text-center">عمر الدين (يوم)</th>
                        <th class="text-end">إجمالي الفاتورة ({{ $cur }})</th>
                        <th class="text-end">المدفوع ({{ $cur }})</th>
                        <th class="text-end">المستحق ({{ $cur }})</th>
                        <th class="text-center">الفئة</th>
                        <th class="no-print"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    @php
                        $bucket = $row['bucket'];
                        $rowClass = match($bucket) {
                            'current' => '',
                            '31_60'   => 'table-warning',
                            '61_90'   => '',
                            'over_90' => 'table-danger',
                        };
                        $badgeClass = match($bucket) {
                            'current' => 'bg-success',
                            '31_60'   => 'bg-warning text-dark',
                            '61_90'   => 'bg-orange',
                            'over_90' => 'bg-danger',
                        };
                        $bucketLabel = match($bucket) {
                            'current' => '0–30 يوم',
                            '31_60'   => '31–60 يوم',
                            '61_90'   => '61–90 يوم',
                            'over_90' => '> 90 يوم',
                        };
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td class="ps-3 fw-semibold">
                            <a href="{{ route('customers.show', $row['customer_id']) }}" class="text-decoration-none">
                                {{ $row['customer'] }}
                            </a>
                        </td>
                        <td class="text-muted small">{{ $row['invoice'] }}</td>
                        <td class="text-muted">{{ $row['invoice_date'] }}</td>
                        <td class="text-center fw-bold {{ $row['age_days'] > 60 ? 'text-danger' : '' }}">
                            {{ $row['age_days'] }}
                        </td>
                        <td class="text-end">{{ number_format($row['total'], 2) }}</td>
                        <td class="text-end text-success">{{ number_format($row['paid'], 2) }}</td>
                        <td class="text-end fw-bold text-danger">{{ number_format($row['outstanding'], 2) }}</td>
                        <td class="text-center">
                            <span class="badge {{ $badgeClass }}">{{ $bucketLabel }}</span>
                        </td>
                        <td class="pe-2 no-print">
                            <a href="{{ route('customer-payments.create', [$row['customer_id'], 'sale_id' => null]) }}"
                               class="btn btn-sm btn-outline-success py-0 px-2">
                                <i class="bi bi-cash"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-success py-4">
                            <i class="bi bi-check-circle fs-3"></i>
                            <div class="mt-1">لا توجد ذمم مستحقة — جميع الفواتير مسددة</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if(!empty($rows))
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="6" class="ps-3">الإجمالي</td>
                        <td class="text-end text-danger">{{ number_format($totalOutstanding, 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
@endsection
