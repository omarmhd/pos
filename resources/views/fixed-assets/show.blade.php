@extends('layouts.app')
@section('page-title', $fixedAsset->asset_code . ' — ' . $fixedAsset->name)

@section('content')
<div class="row">
  <div class="col-lg-11 mx-auto">

    {{-- Header card --}}
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-building-gear text-primary me-2"></i>
                {{ $fixedAsset->asset_code }} — {{ $fixedAsset->name }}
                @if($fixedAsset->status === 'active')
                    <span class="badge bg-success ms-2">نشط</span>
                @elseif($fixedAsset->status === 'fully_depreciated')
                    <span class="badge bg-warning text-dark ms-2">مستهلك بالكامل</span>
                @else
                    <span class="badge bg-secondary ms-2">مُستبعَد</span>
                @endif
            </h5>
            <div class="d-flex gap-2">
                @can('fixed_assets.depreciate')
                @if($fixedAsset->status === 'active')
                <a href="{{ route('fixed-assets.depreciate-form', $fixedAsset) }}" class="btn btn-warning btn-sm">
                    <i class="bi bi-calendar-minus me-1"></i> استهلاك
                </a>
                @endif
                @endcan
                @can('fixed_assets.dispose')
                @if($fixedAsset->status !== 'disposed')
                <a href="{{ route('fixed-assets.dispose-form', $fixedAsset) }}" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash3 me-1"></i> استبعاد
                </a>
                @endif
                @endcan
                <a href="{{ route('fixed-assets.index') }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> رجوع
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h6 class="text-muted">معلومات الأصل</h6>
                    <table class="table table-sm table-borderless small">
                        <tr><th class="text-muted" width="45%">الفئة:</th><td>{{ $fixedAsset->category->name }}</td></tr>
                        <tr><th class="text-muted">الفرع:</th><td>{{ $fixedAsset->branch?->name ?? '—' }}</td></tr>
                        <tr><th class="text-muted">تاريخ الشراء:</th><td>{{ $fixedAsset->purchase_date->format('Y-m-d') }}</td></tr>
                        <tr><th class="text-muted">المورد:</th><td>{{ $fixedAsset->supplier_name ?? '—' }}</td></tr>
                        <tr><th class="text-muted">طريقة الاستهلاك:</th>
                            <td>{{ $fixedAsset->depreciation_method === 'straight_line' ? 'قسط ثابت' : 'قسط متناقص' }}</td></tr>
                        <tr><th class="text-muted">العمر الإنتاجي:</th>
                            <td>{{ $fixedAsset->useful_life_months }} شهراً
                                ({{ round($fixedAsset->useful_life_months/12, 1) }} سنة)</td></tr>
                    </table>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted">الأرقام المالية</h6>
                    <table class="table table-sm table-borderless small">
                        <tr><th class="text-muted" width="50%">تكلفة الشراء:</th>
                            <td class="fw-bold">{{ number_format($fixedAsset->purchase_cost, 2) }} {{ $currency }}</td></tr>
                        <tr><th class="text-muted">القيمة التخريدية:</th>
                            <td>{{ number_format($fixedAsset->residual_value, 2) }} {{ $currency }}</td></tr>
                        <tr><th class="text-muted">المبلغ القابل للاستهلاك:</th>
                            <td>{{ number_format($fixedAsset->depreciableAmount(), 2) }} {{ $currency }}</td></tr>
                        <tr><th class="text-muted">الاستهلاك المتراكم:</th>
                            <td class="text-warning fw-bold">{{ number_format($fixedAsset->accumulated_depreciation, 2) }} {{ $currency }}</td></tr>
                        <tr><th class="text-muted">القيمة الدفترية:</th>
                            <td class="text-primary fw-bold fs-6">{{ number_format($fixedAsset->net_book_value, 2) }} {{ $currency }}</td></tr>
                        @if($fixedAsset->status === 'active')
                        <tr><th class="text-muted">القسط الشهري القادم:</th>
                            <td class="text-success">{{ number_format($fixedAsset->nextPeriodDepreciation(), 2) }} {{ $currency }}</td></tr>
                        @endif
                    </table>
                </div>
                <div class="col-md-4">
                    {{-- Progress bar --}}
                    @php
                        $pct = $fixedAsset->purchase_cost > 0
                            ? min(100, round(($fixedAsset->accumulated_depreciation / $fixedAsset->purchase_cost) * 100))
                            : 0;
                    @endphp
                    <h6 class="text-muted">نسبة الاستهلاك</h6>
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>0%</span><span>{{ $pct }}%</span><span>100%</span>
                    </div>
                    <div class="progress" style="height:14px;">
                        <div class="progress-bar {{ $pct >= 100 ? 'bg-secondary' : 'bg-warning' }}"
                             style="width:{{ $pct }}%"></div>
                    </div>
                    <div class="mt-2 small text-muted">
                        {{ $fixedAsset->depreciationEntries->count() }} شهر من أصل {{ $fixedAsset->useful_life_months }} شهراً
                    </div>
                    @if($fixedAsset->notes)
                    <div class="alert alert-light border mt-3 small">
                        <i class="bi bi-sticky me-1"></i> {{ $fixedAsset->notes }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Depreciation Schedule --}}
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="bi bi-table me-1"></i>جدول الاستهلاك</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light sticky-top">
                    <tr>
                        <th>الفترة</th>
                        <th class="text-end">قسط الاستهلاك</th>
                        <th class="text-end">المتراكم</th>
                        <th class="text-end">القيمة الدفترية</th>
                        <th class="text-center">الحالة</th>
                    </tr>
                    </thead>
                    <tbody>
                    {{-- Posted entries --}}
                    @foreach($fixedAsset->depreciationEntries->sortBy(['period_year','period_month']) as $entry)
                    <tr class="table-success">
                        <td>{{ $entry->periodLabel() }}</td>
                        <td class="text-end">{{ number_format($entry->depreciation_amount, 2) }} {{ $currency }}</td>
                        <td class="text-end">{{ number_format($entry->accumulated_after, 2) }} {{ $currency }}</td>
                        <td class="text-end">{{ number_format($entry->net_book_value_after, 2) }} {{ $currency }}</td>
                        <td class="text-center">
                            <span class="badge bg-success">مرحَّل</span>
                            @if($entry->journalEntry)
                                <small class="text-muted ms-1">{{ $entry->journalEntry->entry_number }}</small>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    {{-- Future schedule --}}
                    @foreach($schedule as $row)
                    <tr class="text-muted">
                        <td>{{ $row['label'] }}</td>
                        <td class="text-end">{{ number_format($row['depr'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['accum'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['nbv'], 2) }}</td>
                        <td class="text-center"><span class="badge bg-light text-muted border">مخطط</span></td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Purchase GL Entry --}}
    @if($fixedAsset->journalEntry)
    <div class="card bg-light">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="bi bi-journal-text me-1"></i>قيد الشراء — {{ $fixedAsset->journalEntry->entry_number }}</h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-secondary"><tr><th>الحساب</th><th class="text-end">مدين</th><th class="text-end">دائن</th><th>البيان</th></tr></thead>
                <tbody>
                @foreach($fixedAsset->journalEntry->lines as $line)
                <tr>
                    <td>{{ $line->account->code }} — {{ $line->account->name }}</td>
                    <td class="text-end">{{ $line->debit  > 0 ? number_format($line->debit,2).' '.$currency : '—' }}</td>
                    <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit,2).' '.$currency : '—' }}</td>
                    <td class="text-muted small">{{ $line->line_description }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

  </div>
</div>
@endsection
