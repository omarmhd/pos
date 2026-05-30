@extends('layouts.app')
@section('title', 'تقرير تقادم الذمم الدائنة')
@section('page-title', 'تقرير تقادم الذمم الدائنة (AP Aging)')

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <span></span>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-primary bg-opacity-10 text-center h-100">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-primary">{{ number_format($buckets['current'], 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">الجاري (0–30 يوم)</div>
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
                    <div class="fs-5 fw-bold" style="color:#fd7e14">{{ number_format($buckets['61_90'], 2) }} {{ $cur }}</div>
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
        <strong><i class="bi bi-calculator"></i> إجمالي الذمم الدائنة المستحقة:</strong>
        <strong class="fs-5 text-danger">{{ number_format($totalOutstanding, 2) }} {{ $cur }}</strong>
    </div>

    <div class="d-none d-print-block mb-3 text-center border-bottom pb-2">
        <strong>تقرير تقادم الذمم الدائنة</strong> — {{ now()->format('Y-m-d') }}
    </div>

    {{-- Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold">
            <i class="bi bi-truck"></i> تفاصيل فواتير الموردين غير المسددة
        </div>
        <div class="card-body p-0">
            @if(count($rows) === 0)
                <div class="text-center text-muted py-5">
                    <i class="bi bi-check2-circle fs-1 text-success"></i>
                    <p class="mt-2">لا توجد ذمم دائنة مستحقة — جميع الفواتير مسددة</p>
                </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 small dt-table" data-title="تقادم الذمم الدائنة">
                    <thead class="table-dark">
                        <tr>
                            <th>المورد</th>
                            <th>رقم الفاتورة</th>
                            <th>تاريخ الفاتورة</th>
                            <th class="text-end">الإجمالي ({{ $cur }})</th>
                            <th class="text-end">المدفوع ({{ $cur }})</th>
                            <th class="text-end">المتبقي ({{ $cur }})</th>
                            <th class="text-center">الأيام</th>
                            <th class="text-center">التقادم</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td class="fw-semibold">
                                <a href="{{ route('suppliers.show', $row['supplier_id']) }}" class="text-decoration-none">
                                    {{ $row['supplier'] }}
                                </a>
                            </td>
                            <td><span class="badge bg-light text-dark">{{ $row['invoice'] }}</span></td>
                            <td>{{ $row['invoice_date'] }}</td>
                            <td class="text-end">{{ number_format($row['total'], 2) }}</td>
                            <td class="text-end text-success">{{ number_format($row['paid'], 2) }}</td>
                            <td class="text-end fw-bold {{ $row['age_days'] > 60 ? 'text-danger' : '' }}">
                                {{ number_format($row['outstanding'], 2) }}
                            </td>
                            <td class="text-center {{ $row['age_days'] > 90 ? 'text-danger fw-bold' : '' }}">
                                {{ $row['age_days'] }}
                            </td>
                            <td class="text-center">
                                @switch($row['bucket'])
                                    @case('current')
                                        <span class="badge bg-primary">جاري</span>
                                        @break
                                    @case('31_60')
                                        <span class="badge bg-warning text-dark">31–60</span>
                                        @break
                                    @case('61_90')
                                        <span class="badge" style="background:#fd7e14;color:white">61–90</span>
                                        @break
                                    @default
                                        <span class="badge bg-danger">+90 يوم</span>
                                @endswitch
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td colspan="5">الإجمالي</td>
                            <td class="text-end text-danger">{{ number_format($totalOutstanding, 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
        </div>
    </div>

</div>
@endsection
