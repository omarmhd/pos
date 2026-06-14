@extends('layouts.app')
@section('title', 'الميزانية العمومية')
@section('page-title', 'الميزانية العمومية')

@section('content')
<div class="container-fluid">

    {{-- Controls --}}
    <div class="card mb-3 no-print">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold small mb-1">بتاريخ</label>
                    <input type="date" name="as_of" class="form-control form-control-sm"
                           value="{{ $asOf->toDateString() }}">
                </div>
                @include('components.branch-filter')
                @if(isset($currencies) && $currencies->count() > 1)
                <div class="col-md-2">
                    <label class="form-label fw-bold small mb-1">عملة العرض</label>
                    <select name="display_currency_id" class="form-select form-select-sm">
                        @foreach($currencies as $cu)
                        <option value="{{ $cu->id }}" {{ (string)($displayCurrencyId ?? '') === (string)$cu->id || ($displayCurrency && $displayCurrency->id === $cu->id) ? 'selected' : '' }}>
                            {{ $cu->code }}{{ $cu->is_base ? ' (أساسية)' : '' }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel"></i> عرض
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="window.print()">
                        <i class="bi bi-printer"></i> طباعة
                    </button>
                </div>
                <div class="col-auto ms-auto">
                    <a href="{{ route('accounting.income-statement') . (isset($branchId) && $branchId ? '?branch_id='.$branchId : '') }}"
                       class="btn btn-outline-success btn-sm">
                        <i class="bi bi-bar-chart-line"></i> قائمة الدخل
                    </a>
                </div>
                <div class="col-12">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        صافي الربح محتسب من {{ $ytdFrom->format('Y/m/d') }} حتى {{ $asOf->format('Y/m/d') }}
                    </small>
                </div>
            </form>
        </div>
    </div>

    {{-- تنبيه عملة العرض (تحويل عرضي فقط) --}}
    @if(isset($displayCurrency) && $displayCurrency && !$displayCurrency->is_base)
    <div class="alert alert-info py-2 small no-print">
        <i class="bi bi-currency-exchange"></i>
        المبالغ معروضة بعملة <strong>{{ $displayCurrency->code }}</strong> عبر <strong>تحويل عرضي بسعر الصرف</strong> فقط —
        وليست ترجمة رسمية وفق IAS 21 (لا تُعتمد للقوائم المالية النظامية). القوائم الرسمية بالعملة الأساسية.
    </div>
    @endif

    {{-- Balance alert --}}
    @if($isBalanced)
    <div class="alert alert-success py-2 d-flex align-items-center gap-2 no-print">
        <i class="bi bi-check-circle-fill"></i>
        <strong>الميزانية متوازنة</strong> — الأصول = الالتزامات + حقوق الملكية
    </div>
    @else
    <div class="alert alert-danger py-2 d-flex align-items-center gap-2 no-print">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>الميزانية غير متوازنة</strong> — الفرق:
        <strong>{{ number_format($difference, 2) }} {{ $currency }}</strong>
        <small class="text-muted">(تحقق من قيود غير مرحّلة أو حسابات ناقصة)</small>
    </div>
    @endif

    {{-- Letterhead (print only) --}}
    <div class="text-center mb-3 d-none print-only">
        <h4>{{ \App\Models\Setting::get('store_name', 'المتجر') }}</h4>
        <h5>الميزانية العمومية بتاريخ {{ $asOf->format('Y/m/d') }}
            @if(isset($branch) && $branch) — {{ $branch->name }} @endif
        </h5>
    </div>

    @php
        // Separate contra-assets (negative balance assets like accumulated depreciation)
        $normalCurrentAssets  = collect($currentAssets)->where('amount', '>', 0)->values();
        $normalFixedAssets    = collect($fixedAssets)->where('amount', '>', 0)->values();
        $contraAssets         = collect($fixedAssets)->where('amount', '<', 0)->values();
        $netFixedAssets       = $totalFixedAssets;   // already net (positive + negative)

        // Financial ratios
        $currentRatio  = $totalCurrentLiabilities > 0
            ? round($totalCurrentAssets / $totalCurrentLiabilities, 2) : null;
        $workingCapital = $totalCurrentAssets - $totalCurrentLiabilities;
        $debtToEquity  = $totalEquity > 0
            ? round($totalLiabilities / $totalEquity, 2) : null;
    @endphp

    {{-- Two-column balance sheet --}}
    <div class="row g-3">

        {{-- ═══ ASSETS (right in RTL) ═══ --}}
        <div class="col-lg-6">
            <div class="card bs-card h-100">
                <div class="bs-header bg-primary">
                    <i class="bi bi-building me-1"></i> الأصول
                    <span class="float-start fs-6 fw-bold">{{ number_format($totalAssets, 2) }} {{ $currency }}</span>
                </div>
                <table class="table table-borderless mb-0 bs-table">

                    {{-- Current Assets --}}
                    <tr class="bs-section border-start border-primary border-3">
                        <td colspan="2" class="py-2 ps-3 fw-bold text-primary small">الأصول المتداولة</td>
                    </tr>
                    @forelse($normalCurrentAssets as $row)
                    <tr class="bs-row">
                        <td class="ps-4">
                            <span class="code-badge">{{ $row['account']->code }}</span>
                            {{ $row['account']->name }}
                        </td>
                        <td class="text-end pe-3 text-primary">{{ number_format($row['amount'], 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="ps-4 text-muted fst-italic small">—</td></tr>
                    @endforelse
                    <tr class="bs-subtotal border-top">
                        <td class="ps-3 fw-bold">إجمالي الأصول المتداولة</td>
                        <td class="text-end pe-3 fw-bold text-primary">{{ number_format($totalCurrentAssets, 2) }}</td>
                    </tr>

                    <tr class="bs-spacer"><td colspan="2"></td></tr>

                    {{-- Fixed Assets --}}
                    <tr class="bs-section border-start border-info border-3">
                        <td colspan="2" class="py-2 ps-3 fw-bold text-info small">الأصول الثابتة (القيمة الدفترية)</td>
                    </tr>
                    @forelse($normalFixedAssets as $row)
                    <tr class="bs-row">
                        <td class="ps-4">
                            <span class="code-badge">{{ $row['account']->code }}</span>
                            {{ $row['account']->name }}
                        </td>
                        <td class="text-end pe-3">{{ number_format($row['amount'], 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="ps-4 text-muted fst-italic small">—</td></tr>
                    @endforelse

                    {{-- Contra-assets (accumulated depreciation — shown as deductions) --}}
                    @foreach($contraAssets as $row)
                    <tr class="bs-row">
                        <td class="ps-4 text-danger">
                            <span class="code-badge">{{ $row['account']->code }}</span>
                            {{ $row['account']->name }}
                            <small class="text-muted">(خصم)</small>
                        </td>
                        <td class="text-end pe-3 text-danger">({{ number_format(abs($row['amount']), 2) }})</td>
                    </tr>
                    @endforeach

                    <tr class="bs-subtotal border-top">
                        <td class="ps-3 fw-bold">صافي الأصول الثابتة</td>
                        <td class="text-end pe-3 fw-bold text-info">{{ number_format($netFixedAssets, 2) }}</td>
                    </tr>

                    {{-- Total Assets --}}
                    <tr class="bs-total border-top border-3 border-dark">
                        <td class="ps-3 py-3 fw-bold fs-6">
                            <i class="bi bi-sigma me-1"></i> إجمالي الأصول
                        </td>
                        <td class="text-end pe-3 py-3 fw-bold fs-6 text-primary">
                            {{ number_format($totalAssets, 2) }} {{ $currency }}
                        </td>
                    </tr>

                </table>
            </div>
        </div>

        {{-- ═══ LIABILITIES & EQUITY (left in RTL) ═══ --}}
        <div class="col-lg-6">
            <div class="card bs-card h-100">
                <div class="bs-header bg-danger">
                    <i class="bi bi-bank me-1"></i> الالتزامات وحقوق الملكية
                    <span class="float-start fs-6 fw-bold">{{ number_format($totalLiabAndEquity, 2) }} {{ $currency }}</span>
                </div>
                <table class="table table-borderless mb-0 bs-table">

                    {{-- Current Liabilities --}}
                    <tr class="bs-section border-start border-danger border-3">
                        <td colspan="2" class="py-2 ps-3 fw-bold text-danger small">الالتزامات المتداولة</td>
                    </tr>
                    @forelse($currentLiabilities as $row)
                    <tr class="bs-row">
                        <td class="ps-4">
                            <span class="code-badge">{{ $row['account']->code }}</span>
                            {{ $row['account']->name }}
                        </td>
                        <td class="text-end pe-3 text-danger">{{ number_format($row['amount'], 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="ps-4 text-muted fst-italic small">—</td></tr>
                    @endforelse
                    <tr class="bs-subtotal border-top">
                        <td class="ps-3 fw-bold text-danger">إجمالي الالتزامات المتداولة</td>
                        <td class="text-end pe-3 fw-bold text-danger">{{ number_format($totalCurrentLiabilities, 2) }}</td>
                    </tr>

                    <tr class="bs-spacer"><td colspan="2"></td></tr>

                    {{-- Long-term Liabilities --}}
                    <tr class="bs-section border-start border-secondary border-3">
                        <td colspan="2" class="py-2 ps-3 fw-bold text-secondary small">الالتزامات طويلة الأجل</td>
                    </tr>
                    @forelse($longTermLiabilities as $row)
                    <tr class="bs-row">
                        <td class="ps-4">
                            <span class="code-badge">{{ $row['account']->code }}</span>
                            {{ $row['account']->name }}
                        </td>
                        <td class="text-end pe-3">{{ number_format($row['amount'], 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="ps-4 text-muted fst-italic small">—</td></tr>
                    @endforelse
                    <tr class="bs-subtotal border-top">
                        <td class="ps-3 fw-bold">إجمالي الالتزامات طويلة الأجل</td>
                        <td class="text-end pe-3 fw-bold">{{ number_format($totalLongTermLiabilities, 2) }}</td>
                    </tr>

                    {{-- Total Liabilities --}}
                    <tr class="border-top border-2 bg-danger bg-opacity-10">
                        <td class="ps-3 py-2 fw-bold text-danger">إجمالي الالتزامات</td>
                        <td class="text-end pe-3 py-2 fw-bold text-danger">{{ number_format($totalLiabilities, 2) }} {{ $currency }}</td>
                    </tr>

                    <tr class="bs-spacer"><td colspan="2"></td></tr>

                    {{-- Equity --}}
                    <tr class="bs-section border-start border-success border-3">
                        <td colspan="2" class="py-2 ps-3 fw-bold text-success small">حقوق الملكية</td>
                    </tr>
                    @forelse($equityAccounts as $row)
                    <tr class="bs-row {{ $row['amount'] < 0 ? 'text-danger' : '' }}">
                        <td class="ps-4">
                            <span class="code-badge">{{ $row['account']->code }}</span>
                            {{ $row['account']->name }}
                            @if($row['amount'] < 0)
                                <small class="text-danger">(خصم)</small>
                            @endif
                        </td>
                        <td class="text-end pe-3 {{ $row['amount'] < 0 ? 'text-danger' : '' }}">
                            @if($row['amount'] < 0)
                                ({{ number_format(abs($row['amount']), 2) }})
                            @else
                                {{ number_format($row['amount'], 2) }}
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="ps-4 text-muted fst-italic small">—</td></tr>
                    @endforelse

                    {{-- Net income plug (YTD, before year-end closing) --}}
                    @if(abs($netIncome) > 0.01)
                    <tr class="bs-row {{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}">
                        <td class="ps-4">
                            <i class="bi bi-arrow-return-left me-1 opacity-50"></i>
                            {{ $netIncome >= 0 ? 'صافي ربح الفترة' : 'صافي خسارة الفترة' }}
                            <small class="text-muted opacity-75">
                                {{ $ytdFrom->format('Y/m/d') }}–{{ $asOf->format('Y/m/d') }}
                            </small>
                        </td>
                        <td class="text-end pe-3 fw-semibold {{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}">
                            @if($netIncome < 0)({{ number_format(abs($netIncome), 2) }})@else{{ number_format($netIncome, 2) }}@endif
                        </td>
                    </tr>
                    @endif

                    <tr class="bs-subtotal border-top">
                        <td class="ps-3 fw-bold text-success">إجمالي حقوق الملكية</td>
                        <td class="text-end pe-3 fw-bold text-success">{{ number_format($totalEquity, 2) }} {{ $currency }}</td>
                    </tr>

                    {{-- Total Liabilities + Equity --}}
                    <tr class="bs-total border-top border-3 border-dark">
                        <td class="ps-3 py-3 fw-bold fs-6">
                            <i class="bi bi-sigma me-1"></i> إجمالي الالتزامات وحقوق الملكية
                        </td>
                        <td class="text-end pe-3 py-3 fw-bold fs-6 {{ $isBalanced ? 'text-danger' : 'text-warning' }}">
                            {{ number_format($totalLiabAndEquity, 2) }} {{ $currency }}
                        </td>
                    </tr>

                </table>
            </div>
        </div>
    </div>

    {{-- Financial Ratios --}}
    <div class="card mt-3 no-print">
        <div class="card-header bg-light fw-bold small py-2">
            <i class="bi bi-calculator me-1"></i> مؤشرات مالية رئيسية
        </div>
        <div class="card-body py-2">
            <div class="row text-center g-3">
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100">
                        <div class="fw-bold fs-5 text-primary">{{ number_format($totalAssets, 2) }}</div>
                        <small class="text-muted">إجمالي الأصول</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100 {{ $workingCapital >= 0 ? '' : 'border-danger' }}">
                        <div class="fw-bold fs-5 {{ $workingCapital >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($workingCapital, 2) }}
                        </div>
                        <small class="text-muted">رأس المال العامل</small>
                        <div class="text-muted" style="font-size:.7rem">أصول متداولة − التزامات متداولة</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100 {{ $currentRatio === null || $currentRatio >= 1 ? '' : 'border-warning' }}">
                        @if($currentRatio !== null)
                            <div class="fw-bold fs-5 {{ $currentRatio >= 2 ? 'text-success' : ($currentRatio >= 1 ? 'text-warning' : 'text-danger') }}">
                                {{ $currentRatio }}x
                            </div>
                        @else
                            <div class="fw-bold fs-5 text-muted">—</div>
                        @endif
                        <small class="text-muted">نسبة التداول</small>
                        <div class="text-muted" style="font-size:.7rem">أصول متداولة ÷ التزامات متداولة (>1 جيد)</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100">
                        @if($debtToEquity !== null)
                            <div class="fw-bold fs-5 {{ $debtToEquity <= 1 ? 'text-success' : ($debtToEquity <= 2 ? 'text-warning' : 'text-danger') }}">
                                {{ $debtToEquity }}x
                            </div>
                        @else
                            <div class="fw-bold fs-5 text-muted">—</div>
                        @endif
                        <small class="text-muted">الديون / الملكية</small>
                        <div class="text-muted" style="font-size:.7rem">إجمالي الالتزامات ÷ حقوق الملكية (<1 أفضل)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@push('styles')
<style>
.bs-card { border: 1px solid #dee2e6; border-radius: 10px; overflow: hidden; }
.bs-header {
    color: #fff;
    padding: 12px 16px;
    font-weight: 700;
    font-size: .95rem;
}
.bs-table { font-size: .88rem; }
.bs-section td { font-size: .78rem; text-transform: uppercase; letter-spacing: .3px; background: rgba(0,0,0,.02); }
.bs-row:hover { background: rgba(0,0,0,.02); }
.bs-row td { padding: 5px 8px; }
.bs-subtotal td { background: rgba(0,0,0,.03); font-size: .85rem; padding: 6px 8px; }
.bs-total td { padding: 12px 8px; background: rgba(0,0,0,.05); }
.bs-spacer td { padding: 4px 0 !important; border: none !important; }
.code-badge {
    display: inline-block;
    font-family: monospace;
    font-size: .72rem;
    background: #f0f0f0;
    color: #666;
    padding: 1px 5px;
    border-radius: 3px;
    margin-left: 6px;
}

@media print {
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    .bs-card { box-shadow: none !important; border: 1px solid #999 !important; }
    .bs-table { font-size: .78rem; }
    .card { box-shadow: none !important; }
}
.print-only { display: none; }
</style>
@endpush
@endsection
