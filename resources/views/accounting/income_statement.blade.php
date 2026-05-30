@extends('layouts.app')
@section('title', 'قائمة الدخل')
@section('page-title', 'قائمة الدخل')

@section('content')

    {{-- Controls --}}
    <div class="card mb-3 no-print">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold small mb-1">من تاريخ</label>
                    <input type="date" name="from" class="form-control form-control-sm"
                           value="{{ $from->toDateString() }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small mb-1">إلى تاريخ</label>
                    <input type="date" name="to" class="form-control form-control-sm"
                           value="{{ $to->toDateString() }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel"></i> عرض
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="window.print()">
                        <i class="bi bi-printer"></i> طباعة
                    </button>
                </div>
                <div class="col-auto ms-auto">
                    <a href="{{ route('accounting.balance-sheet') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-layout-split"></i> الميزانية العمومية
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Statement card --}}
    <div class="card shadow-sm">

        {{-- Letterhead --}}
        <div class="card-header bg-dark text-white text-center py-3">
            <div class="fw-bold fs-5">{{ \App\Models\Setting::get('store_name', 'المتجر') }}</div>
            <div class="fs-6 mt-1"><i class="bi bi-bar-chart-line me-1"></i>قائمة الدخل</div>
            <div class="small opacity-75 mt-1">
                للفترة من <strong>{{ $from->format('Y/m/d') }}</strong>
                إلى <strong>{{ $to->format('Y/m/d') }}</strong>
            </div>
        </div>

        <div class="card-body p-0">
        <table class="table table-borderless mb-0" style="font-size:.9rem;">

            {{-- MACRO for account row (2 cols: name | amount) --}}
            {{-- ══ REVENUE ══ --}}
            <tr style="background:rgba(25,135,84,.07);">
                <td colspan="2" class="py-2 px-3 fw-bold text-success"
                    style="border-right:4px solid #198754;">
                    <i class="bi bi-plus-circle-fill me-1"></i> الإيرادات
                </td>
            </tr>

            @forelse($grossRevenue as $row)
            <tr class="is-acct-row">
                <td class="px-4">
                    <span class="acct-code">{{ $row['account']->code }}</span>
                    {{ $row['account']->name }}
                </td>
                <td class="text-end px-4 fw-semibold text-success">
                    {{ number_format($row['amount'], 2) }}
                </td>
            </tr>
            @empty
            <tr><td colspan="2" class="px-4 text-muted fst-italic small">لا توجد إيرادات</td></tr>
            @endforelse

            @foreach($contraRevenue as $row)
            <tr class="is-acct-row">
                <td class="px-4 text-danger">
                    <span class="acct-code">{{ $row['account']->code }}</span>
                    {{ $row['account']->name }}
                    <small class="opacity-60">(خصم)</small>
                </td>
                <td class="text-end px-4 fw-semibold text-danger">
                    ({{ number_format($row['amount'], 2) }})
                </td>
            </tr>
            @endforeach

            @if($totalContra > 0)
            <tr style="background:rgba(0,0,0,.02);">
                <td class="px-4 text-muted small">إجمالي المبيعات قبل الخصومات</td>
                <td class="text-end px-4 text-muted small">{{ number_format($totalGrossRevenue, 2) }}</td>
            </tr>
            @endif

            <tr class="is-total-row border-top border-2">
                <td class="px-3 fw-bold text-success">صافي الإيرادات</td>
                <td class="text-end px-4 fw-bold text-success fs-6">
                    {{ number_format($totalNetRevenue, 2) }} {{ $currency }}
                </td>
            </tr>

            <tr class="is-spacer"><td colspan="2"></td></tr>

            {{-- ══ COGS ══ --}}
            <tr style="background:rgba(255,193,7,.09);">
                <td colspan="2" class="py-2 px-3 fw-bold text-warning"
                    style="border-right:4px solid #ffc107;">
                    <i class="bi bi-box-seam me-1"></i> تكلفة البضاعة المباعة
                </td>
            </tr>

            @forelse($cogs as $row)
            <tr class="is-acct-row">
                <td class="px-4">
                    <span class="acct-code">{{ $row['account']->code }}</span>
                    {{ $row['account']->name }}
                </td>
                <td class="text-end px-4 fw-semibold text-warning">
                    ({{ number_format($row['amount'], 2) }})
                </td>
            </tr>
            @empty
            <tr><td colspan="2" class="px-4 text-muted fst-italic small">لا توجد تكاليف</td></tr>
            @endforelse

            <tr class="is-total-row border-top border-2">
                <td class="px-3 fw-bold text-warning">إجمالي تكلفة البضاعة المباعة</td>
                <td class="text-end px-4 fw-bold text-warning">
                    ({{ number_format($totalCogs, 2) }}) {{ $currency }}
                </td>
            </tr>

            <tr class="is-spacer"><td colspan="2"></td></tr>

            {{-- ══ GROSS PROFIT ══ --}}
            <tr style="background:rgba(13,110,253,.06); border-top:2px solid #0d6efd;">
                <td class="px-3 py-3 fw-bold fs-6">
                    <i class="bi bi-graph-up-arrow text-primary me-1"></i>
                    مجمل الربح
                    <span class="badge bg-primary ms-1 fw-normal">هامش {{ $grossMargin }}%</span>
                </td>
                <td class="text-end px-4 py-3 fw-bold fs-6 {{ $grossProfit >= 0 ? 'text-primary' : 'text-danger' }}">
                    @if($grossProfit < 0)({{ number_format(abs($grossProfit), 2) }})
                    @else{{ number_format($grossProfit, 2) }}@endif
                    {{ $currency }}
                </td>
            </tr>

            <tr class="is-spacer"><td colspan="2"></td></tr>

            {{-- ══ OPEX ══ --}}
            <tr style="background:rgba(220,53,69,.07);">
                <td colspan="2" class="py-2 px-3 fw-bold text-danger"
                    style="border-right:4px solid #dc3545;">
                    <i class="bi bi-dash-circle-fill me-1"></i> المصروفات التشغيلية
                </td>
            </tr>

            @forelse($opex as $row)
            <tr class="is-acct-row">
                <td class="px-4">
                    <span class="acct-code">{{ $row['account']->code }}</span>
                    {{ $row['account']->name }}
                </td>
                <td class="text-end px-4 fw-semibold text-danger">
                    ({{ number_format($row['amount'], 2) }})
                </td>
            </tr>
            @empty
            <tr><td colspan="2" class="px-4 text-muted fst-italic small">لا توجد مصروفات</td></tr>
            @endforelse

            <tr class="is-total-row border-top border-2">
                <td class="px-3 fw-bold text-danger">
                    إجمالي المصروفات التشغيلية
                    <span class="badge bg-danger ms-1 fw-normal">{{ $opexRatio }}% من الإيراد</span>
                </td>
                <td class="text-end px-4 fw-bold text-danger">
                    ({{ number_format($totalOpex, 2) }}) {{ $currency }}
                </td>
            </tr>

            <tr class="is-spacer"><td colspan="2"></td></tr>

            {{-- ══ OPERATING INCOME (EBIT) ══ --}}
            <tr style="background:rgba(0,0,0,.04); border-top:2px solid #aaa;">
                <td class="px-3 py-2 fw-bold">
                    <i class="bi bi-buildings me-1 text-secondary"></i>
                    ربح التشغيل (EBIT)
                </td>
                <td class="text-end px-4 py-2 fw-bold {{ $operatingIncome >= 0 ? 'text-dark' : 'text-danger' }}">
                    @if($operatingIncome < 0)({{ number_format(abs($operatingIncome), 2) }})
                    @else{{ number_format($operatingIncome, 2) }}@endif
                    {{ $currency }}
                </td>
            </tr>

            {{-- ══ OTHER INCOME ══ --}}
            @if(count($otherIncome) > 0)
            <tr class="is-spacer"><td colspan="2"></td></tr>
            <tr style="background:rgba(13,202,240,.07);">
                <td colspan="2" class="py-2 px-3 fw-bold text-info"
                    style="border-right:4px solid #0dcaf0;">
                    <i class="bi bi-plus-circle me-1"></i> الإيرادات الأخرى (غير تشغيلية)
                </td>
            </tr>
            @foreach($otherIncome as $row)
            <tr class="is-acct-row">
                <td class="px-4">
                    <span class="acct-code">{{ $row['account']->code }}</span>
                    {{ $row['account']->name }}
                </td>
                <td class="text-end px-4 fw-semibold text-info">
                    {{ number_format($row['amount'], 2) }}
                </td>
            </tr>
            @endforeach
            <tr class="is-total-row border-top">
                <td class="px-3 fw-bold text-info">إجمالي الإيرادات الأخرى</td>
                <td class="text-end px-4 fw-bold text-info">
                    {{ number_format($totalOtherIncome, 2) }} {{ $currency }}
                </td>
            </tr>
            @endif

            <tr class="is-spacer"><td colspan="2"></td></tr>

            {{-- ══ NET INCOME ══ --}}
            <tr class="{{ $netIncome >= 0 ? 'bg-success' : 'bg-danger' }} bg-opacity-10"
                style="border-top:3px double #000;">
                <td class="px-3 py-3 fw-bold fs-5">
                    <i class="bi bi-{{ $netIncome >= 0 ? 'trophy-fill text-success' : 'exclamation-triangle-fill text-danger' }} me-1"></i>
                    {{ $netIncome >= 0 ? 'صافي الربح' : 'صافي الخسارة' }}
                    <span class="badge {{ $netIncome >= 0 ? 'bg-success' : 'bg-danger' }} ms-1 fw-normal">
                        {{ $netMargin }}%
                    </span>
                </td>
                <td class="text-end px-4 py-3 fw-bold fs-5 {{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}">
                    @if($netIncome < 0)({{ number_format(abs($netIncome), 2) }})
                    @else{{ number_format(abs($netIncome), 2) }}@endif
                    {{ $currency }}
                </td>
            </tr>

        </table>
        </div>

        {{-- KPI footer --}}
        <div class="card-footer bg-white no-print">
            <div class="row text-center g-2">
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="text-success fw-bold">{{ number_format($totalNetRevenue, 2) }}</div>
                        <small class="text-muted">صافي الإيراد</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="text-primary fw-bold">{{ number_format($grossProfit, 2) }}</div>
                        <small class="text-muted">مجمل الربح ({{ $grossMargin }}%)</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="text-danger fw-bold">{{ number_format($totalOpex, 2) }}</div>
                        <small class="text-muted">المصروفات ({{ $opexRatio }}%)</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="fw-bold {{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($netIncome, 2) }}
                        </div>
                        <small class="text-muted">{{ $netIncome >= 0 ? 'صافي الربح' : 'صافي الخسارة' }} ({{ $netMargin }}%)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

@push('styles')
<style>
.is-acct-row:hover { background: rgba(0,0,0,.025); }
.acct-code {
    display: inline-block;
    font-family: monospace;
    font-size: .72rem;
    background: #f0f0f0;
    color: #666;
    padding: 1px 4px;
    border-radius: 3px;
    margin-left: 6px;
}
.is-total-row td { background: rgba(0,0,0,.025); padding-top: 7px; padding-bottom: 7px; }
.is-spacer td { padding: 5px 0 !important; border: none !important; }
@media print {
    .no-print { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ccc !important; }
    table { font-size: .8rem !important; }
}
</style>
@endpush
@endsection
