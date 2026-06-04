@extends('layouts.app')
@section('page-title', 'قائمة الدخل الموحدة')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-bar-chart-line-fill text-success me-2"></i>قائمة الدخل الموحدة — جميع الفروع
        </h5>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary no-print">رجوع</a>
    </div>

    <div class="card-body border-bottom py-2 no-print">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">من تاريخ</label>
                <input type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small">إلى تاريخ</label>
                <input type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>عرض</button>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
                <thead class="table-dark">
                    <tr>
                        <th style="min-width:200px">الحساب</th>
                        @foreach($branches as $b)
                            <th class="text-end">{{ $b->name }}</th>
                        @endforeach
                        <th class="text-end table-secondary fw-bold">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                @php
                    $sections = [
                        'grossRevenue'  => ['label' => 'إيرادات المبيعات',   'class' => 'text-success'],
                        'contraRevenue' => ['label' => 'خصومات / مردودات',  'class' => 'text-danger'],
                        'otherIncome'   => ['label' => 'إيرادات أخرى',       'class' => 'text-info'],
                        'cogs'          => ['label' => 'تكلفة البضاعة (COGS)', 'class' => 'text-danger'],
                        'opex'          => ['label' => 'مصروفات تشغيلية',   'class' => 'text-warning'],
                    ];
                    $summaryRows = [
                        'totalNetRevenue' => 'صافي الإيرادات',
                        'grossProfit'     => 'مجمل الربح',
                        'netIncome'       => 'صافي الربح',
                    ];
                @endphp

                @foreach($sections as $sectionKey => $sectionMeta)
                    <tr class="table-light">
                        <td colspan="{{ $branches->count() + 2 }}" class="{{ $sectionMeta['class'] }} fw-bold small">
                            {{ $sectionMeta['label'] }}
                        </td>
                    </tr>
                    @foreach($allAccounts as $code => $account)
                        @php
                            $hasAny = false;
                            foreach($columns as $col) {
                                foreach($col['data'][$sectionKey] as $row) {
                                    if($row['account']->code === $code) { $hasAny = true; break 2; }
                                }
                            }
                        @endphp
                        @if($hasAny)
                        <tr>
                            <td class="ps-3 text-muted">{{ $account->code }} — {{ $account->name }}</td>
                            @foreach($columns as $branchId => $col)
                                @php
                                    $amt = 0;
                                    foreach($col['data'][$sectionKey] as $row) {
                                        if($row['account']->code === $code) { $amt = $row['amount']; break; }
                                    }
                                @endphp
                                <td class="text-end font-monospace {{ $amt < 0 ? 'text-danger' : '' }}">
                                    {{ $amt != 0 ? number_format($amt, 2) : '—' }}
                                </td>
                            @endforeach
                            @php
                                $totalAmt = 0;
                                foreach($consolidated[$sectionKey] as $row) {
                                    if($row['account']->code === $code) { $totalAmt = $row['amount']; break; }
                                }
                            @endphp
                            <td class="text-end font-monospace fw-semibold table-secondary">
                                {{ $totalAmt != 0 ? number_format($totalAmt, 2) : '—' }}
                            </td>
                        </tr>
                        @endif
                    @endforeach
                @endforeach

                {{-- Summary rows --}}
                <tr class="table-dark fw-bold border-top">
                    <td>صافي الإيرادات</td>
                    @foreach($columns as $col)
                        <td class="text-end font-monospace">{{ number_format($col['data']['totalNetRevenue'], 2) }}</td>
                    @endforeach
                    <td class="text-end font-monospace table-secondary">{{ number_format($consolidated['totalNetRevenue'], 2) }}</td>
                </tr>
                <tr class="fw-bold">
                    <td>مجمل الربح</td>
                    @foreach($columns as $col)
                        <td class="text-end font-monospace {{ $col['data']['grossProfit'] < 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($col['data']['grossProfit'], 2) }}
                        </td>
                    @endforeach
                    <td class="text-end font-monospace table-secondary {{ $consolidated['grossProfit'] < 0 ? 'text-danger' : 'text-success' }}">
                        {{ number_format($consolidated['grossProfit'], 2) }}
                    </td>
                </tr>
                <tr class="fw-bold fs-6 table-warning">
                    <td>صافي الربح / الخسارة</td>
                    @foreach($columns as $col)
                        <td class="text-end font-monospace {{ $col['data']['netIncome'] < 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($col['data']['netIncome'], 2) }} {{ $currency }}
                        </td>
                    @endforeach
                    <td class="text-end font-monospace {{ $consolidated['netIncome'] < 0 ? 'text-danger' : 'text-success' }}">
                        <strong>{{ number_format($consolidated['netIncome'], 2) }} {{ $currency }}</strong>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
