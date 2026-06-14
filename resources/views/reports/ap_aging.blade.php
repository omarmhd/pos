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

    {{-- Info Note --}}
    <div class="alert alert-info d-flex align-items-center mb-4 no-print" role="alert">
        <i class="bi bi-info-circle-fill fs-4 me-3 ms-2"></i>
        <div>
            <strong>دليل التقرير:</strong>
            يُظهر هذا التقرير جميع الديون (الذمم الدائنة) المستحقة للموردين أو لجهات المصاريف والتي لم يتم سدادها بعد.
            ويقوم بتوزيع هذه الديون حسب أقدمية كل فاتورة لمساعدتك في أولوية السداد وإدارة التدفق النقدي (السيولة).
            يتضمن التقرير فقط المبالغ المتبقية بعد طرح المدفوعات وأي مرتجعات.
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-primary bg-opacity-10 text-center h-100">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-primary">{{ number_format($buckets['current'], 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">الجاري (0–30 يوم)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-warning bg-opacity-10 text-center h-100">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-warning">{{ number_format($buckets['31_60'], 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">31–60 يوم</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 text-center h-100" style="background:#fff3e0;">
                <div class="card-body">
                    <div class="fs-5 fw-bold" style="color:#fd7e14">{{ number_format($buckets['61_90'], 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">61–90 يوم</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-danger bg-opacity-10 text-center h-100">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-danger">{{ number_format($buckets['over_90'], 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">أكثر من 90 يوم</div>
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
            <div class="table-responsive">
                <table class="table table-hover mb-0 small" id="apAgingTable" style="width:100%" data-title="تقادم الذمم الدائنة">
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
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
$(function () {
    $('#apAgingTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: "{{ route('reports.ap-aging') }}",
        order: [],
        columns: [
            { data: 'vendor_col',      orderable: false, searchable: true  },
            { data: 'invoice_col',     orderable: false, searchable: true  },
            { data: 'invoice_date',    orderable: false, searchable: true  },
            { data: 'total_fmt',       orderable: false, searchable: false, className: 'text-end'    },
            { data: 'paid_fmt',        orderable: false, searchable: false, className: 'text-end'    },
            { data: 'outstanding_fmt', orderable: false, searchable: false, className: 'text-end'    },
            { data: 'age_fmt',         orderable: false, searchable: false, className: 'text-center' },
            { data: 'bucket_col',      orderable: false, searchable: false, className: 'text-center' },
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json' },
    });
});
</script>
@endsection
