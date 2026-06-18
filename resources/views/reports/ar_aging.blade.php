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
                    <a href="{{ route('reports.ar-aging') }}" class="btn btn-outline-danger btn-sm ms-1">
                        <i class="bi bi-x"></i>
                    </a>
                @endif
            </div>
        </form>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>

    {{-- Info Note --}}
    <div class="alert alert-info d-flex align-items-center mb-4 no-print" role="alert">
        <i class="bi bi-info-circle-fill fs-4 me-3 ms-2"></i>
        <div>
            <strong>دليل التقرير:</strong>
            يُظهر هذا التقرير جميع الديون (الذمم المدينة) المستحقة على العملاء والتي لم يتم سدادها بعد.
            ويقوم بتوزيع هذه الديون حسب أقدمية كل فاتورة لمساعدتك في متابعة التحصيل وتقليل الديون المعدومة.
            الرصيد المعروض هنا هو "الرصيد الصافي" (Net Outstanding) للفاتورة بعد خصم أي مدفوعات نقدية، شيكات، أرصدة إيداع، أو إشعارات دائنة وتصحيحات.
        </div>
    </div>

    {{-- Bucket Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-success bg-opacity-10 text-center h-100">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-success">{{ number_format($buckets['current'] ?? 0, 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">جارية (0–30 يوم)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-warning bg-opacity-10 text-center h-100">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-warning">{{ number_format($buckets['31_60'] ?? 0, 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">31–60 يوم</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 text-center h-100" style="background:#fff3e0;">
                <div class="card-body">
                    <div class="fs-5 fw-bold" style="color:#e65100;">{{ number_format($buckets['61_90'] ?? 0, 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">61–90 يوم</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-danger bg-opacity-10 text-center h-100">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-danger">{{ number_format($buckets['over_90'] ?? 0, 2) }} {{ $cur }}</div>
                    <div class="small fw-semibold">أكثر من 90 يوم</div>
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
            <table class="table table-hover mb-0" id="arAgingTable" style="width:100%" data-title="تقادم الذمم المدينة">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">العميل</th>
                        <th>رقم الفاتورة</th>
                        <th>تاريخ الفاتورة</th>
                        <th class="text-end">إجمالي الفاتورة ({{ $cur }})</th>
                        <th class="text-end">المستحق ({{ $cur }})</th>
                        <th class="text-center">عمر الدين (يوم)</th>
                        <th class="text-center">الفئة</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="4" class="ps-3">الإجمالي</td>
                        <td class="text-end text-danger">{{ number_format($totalOutstanding, 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
$(function () {
    $('#arAgingTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: "{{ route('reports.ar-aging') }}",
        order: [],
        columns: [
            { data: 'customer_col',    orderable: false, searchable: true  },
            { data: 'invoice_col',     orderable: false, searchable: true  },
            { data: 'invoice_date',    orderable: false, searchable: true  },
            { data: 'total_fmt',       orderable: false, searchable: false, className: 'text-end'    },
            { data: 'outstanding_fmt', orderable: false, searchable: false, className: 'text-end'    },
            { data: 'age_fmt',         orderable: false, searchable: false, className: 'text-center' },
            { data: 'bucket_col',      orderable: false, searchable: false, className: 'text-center' },
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json' },
    });
});
</script>
@endsection
