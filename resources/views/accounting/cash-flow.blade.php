@extends('layouts.app')
@section('page-title', 'قائمة التدفقات النقدية')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">
                <i class="bi bi-cash-coin text-success me-2"></i>
                قائمة التدفقات النقدية — الطريقة غير المباشرة
            </h5>
            <small class="text-muted">وفق معيار IAS 7</small>
        </div>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary no-print">رجوع</a>
    </div>

    {{-- Filters --}}
    <div class="card-body border-bottom py-2 no-print">
        <form method="GET" class="row g-2 align-items-end">
            @include('components.branch-filter')
            <div class="col-md-2">
                <label class="form-label small">من تاريخ</label>
                <input type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small">إلى تاريخ</label>
                <input type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>عرض</button>
            </div>
        </form>
    </div>

    <div class="card-body">
        <div class="row">
        <div class="col-lg-8 mx-auto">

        {{-- Print header --}}
        <div class="text-center mb-4 d-none d-print-block">
            <h4>قائمة التدفقات النقدية</h4>
            <div class="text-muted">من {{ $from->toDateString() }} إلى {{ $to->toDateString() }}</div>
        </div>

        @php
            function cfRow(string $label, float $amount, string $currency, bool $bold = false, bool $sub = false): string {
                $indent  = $sub ? 'ps-4 text-muted' : '';
                $weight  = $bold ? 'fw-bold' : '';
                $color   = $bold ? ($amount >= 0 ? 'text-success' : 'text-danger') : '';
                $fmt     = number_format(abs($amount), 2) . ' ' . $currency;
                $sign    = $amount >= 0 ? '' : '(';
                $signEnd = $amount >= 0 ? '' : ')';
                return "<tr class='{$weight} {$color}'>"
                     . "<td class='{$indent}'>{$label}</td>"
                     . "<td class='text-end font-monospace'>{$sign}{$fmt}{$signEnd}</td>"
                     . "</tr>";
            }
        @endphp

        <table class="table table-sm table-borderless" style="font-size:.9rem">
            {{-- ══════════════════════════════════════════════════════════ --}}
            {{-- A. Operating --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <thead>
                <tr class="table-dark">
                    <td colspan="2" class="fw-bold">
                        أ) التدفقات النقدية من أنشطة التشغيل
                    </td>
                </tr>
            </thead>
            <tbody>
                <tr><td>صافي الربح / الخسارة للفترة</td>
                    <td class="text-end font-monospace {{ $netIncome >= 0 ? '' : 'text-danger' }}">
                        {{ number_format($netIncome, 2) }} {{ $currency }}
                    </td>
                </tr>

                <tr class="table-light"><td colspan="2" class="fw-semibold small text-muted ps-3">
                    تعديلات للبنود غير النقدية:
                </td></tr>
                @if($depreciation > 0)
                <tr><td class="ps-4 text-muted">+ استهلاك الأصول الثابتة</td>
                    <td class="text-end font-monospace">{{ number_format($depreciation, 2) }}</td>
                </tr>
                @endif
                @if($eosbProvision > 0)
                <tr><td class="ps-4 text-muted">+ مخصصات نهاية الخدمة</td>
                    <td class="text-end font-monospace">{{ number_format($eosbProvision, 2) }}</td>
                </tr>
                @endif

                @if(count($wcItems))
                <tr class="table-light"><td colspan="2" class="fw-semibold small text-muted ps-3">
                    تغييرات رأس المال العامل:
                </td></tr>
                @foreach($wcItems as $wc)
                <tr>
                    <td class="ps-4 text-muted">
                        {{ $wc['effect'] > 0 ? '↓' : '↑' }} {{ $wc['label'] }}
                        <small class="text-muted">({{ $wc['code'] }})</small>
                    </td>
                    <td class="text-end font-monospace {{ $wc['effect'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $wc['effect'] >= 0 ? '+' : '' }}{{ number_format($wc['effect'], 2) }}
                    </td>
                </tr>
                @endforeach
                @endif

                <tr class="border-top fw-bold {{ $operatingCash >= 0 ? 'text-success' : 'text-danger' }}">
                    <td>صافي النقد من أنشطة التشغيل</td>
                    <td class="text-end font-monospace fs-6">
                        {{ number_format($operatingCash, 2) }} {{ $currency }}
                    </td>
                </tr>
            </tbody>

            {{-- ══════════════════════════════════════════════════════════ --}}
            {{-- B. Investing --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <thead class="mt-3">
                <tr class="table-dark">
                    <td colspan="2" class="fw-bold">
                        ب) التدفقات النقدية من أنشطة الاستثمار
                    </td>
                </tr>
            </thead>
            <tbody>
                @if($assetPurchases > 0)
                <tr><td class="ps-4 text-muted">− شراء أصول ثابتة</td>
                    <td class="text-end font-monospace text-danger">({{ number_format($assetPurchases, 2) }})</td>
                </tr>
                @endif
                @if($assetDisposals > 0)
                <tr><td class="ps-4 text-muted">+ حصيلة بيع / استبعاد أصول</td>
                    <td class="text-end font-monospace text-success">{{ number_format($assetDisposals, 2) }}</td>
                </tr>
                @endif
                @if($assetPurchases == 0 && $assetDisposals == 0)
                <tr><td class="ps-4 text-muted fst-italic">لا توجد معاملات استثمارية في هذه الفترة</td>
                    <td class="text-end text-muted">—</td>
                </tr>
                @endif
                <tr class="border-top fw-bold {{ $investingCash >= 0 ? 'text-success' : 'text-danger' }}">
                    <td>صافي النقد من أنشطة الاستثمار</td>
                    <td class="text-end font-monospace">
                        {{ number_format($investingCash, 2) }} {{ $currency }}
                    </td>
                </tr>
            </tbody>

            {{-- ══════════════════════════════════════════════════════════ --}}
            {{-- C. Financing --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <thead>
                <tr class="table-dark">
                    <td colspan="2" class="fw-bold">
                        ج) التدفقات النقدية من أنشطة التمويل
                    </td>
                </tr>
            </thead>
            <tbody>
                @if(abs($financingCash) > 0.005)
                <tr><td class="ps-4 text-muted">
                        {{ $financingCash > 0 ? '+ مساهمات رأس المال' : '− مسحوبات / توزيعات أرباح' }}
                    </td>
                    <td class="text-end font-monospace {{ $financingCash >= 0 ? 'text-success':'text-danger' }}">
                        {{ number_format($financingCash, 2) }}
                    </td>
                </tr>
                @else
                <tr><td class="ps-4 text-muted fst-italic">لا توجد معاملات تمويلية في هذه الفترة</td>
                    <td class="text-end text-muted">—</td>
                </tr>
                @endif
                <tr class="border-top fw-bold {{ $financingCash >= 0 ? 'text-success':'text-danger' }}">
                    <td>صافي النقد من أنشطة التمويل</td>
                    <td class="text-end font-monospace">{{ number_format($financingCash, 2) }} {{ $currency }}</td>
                </tr>
            </tbody>

            {{-- ══════════════════════════════════════════════════════════ --}}
            {{-- D/E/F. Summary --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <tbody class="border-top">
                <tr class="table-secondary fw-bold fs-6">
                    <td>د) صافي التغيير في النقدية للفترة (أ + ب + ج)</td>
                    <td class="text-end font-monospace {{ $netChange >= 0 ? 'text-success':'text-danger' }}">
                        {{ $netChange >= 0 ? '+' : '' }}{{ number_format($netChange, 2) }} {{ $currency }}
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">ه) رصيد النقدية في بداية الفترة</td>
                    <td class="text-end font-monospace">{{ number_format($openingCash, 2) }} {{ $currency }}</td>
                </tr>
                <tr class="table-dark fw-bold fs-5">
                    <td>و) رصيد النقدية في نهاية الفترة (د + ه)</td>
                    <td class="text-end font-monospace">{{ number_format($closingCash, 2) }} {{ $currency }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Reconciliation check --}}
        @if(abs($reconciliationDiff) > 0.05)
        <div class="alert alert-warning mt-3 small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>ملاحظة تسوية:</strong>
            يوجد فرق {{ number_format(abs($reconciliationDiff), 2) }} {{ $currency }}
            بين صافي التغيير المحسوب والفرق الفعلي في أرصدة النقدية.
            قد يكون ناتجاً عن معاملات نقدية غير مصنَّفة أو قيود تصحيح يدوية.
        </div>
        @else
        <div class="alert alert-success mt-3 small">
            <i class="bi bi-check-circle me-1"></i>
            القائمة متوازنة — رصيد الختامي يتطابق مع دفتر الأستاذ
        </div>
        @endif

        <div class="text-muted small mt-3 no-print border-top pt-2">
            <i class="bi bi-info-circle me-1"></i>
            مُعَدَّة بالطريقة غير المباشرة وفق معيار المحاسبة الدولي IAS 7.
            مصدر البيانات: دفتر الأستاذ العام — من {{ $from->format('Y-m-d') }} إلى {{ $to->format('Y-m-d') }}.
        </div>

        </div>{{-- col --}}
        </div>{{-- row --}}
    </div>
</div>
@endsection
