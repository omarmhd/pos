@extends('layouts.app')
@section('page-title', 'كشف حساب: ' . $party->name)

@php
    function partyBalanceLabel($v) { return $v >= 0 ? 'مدين' : 'دائن'; }
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-person-vcard"></i>
        كشف حساب {{ $label }}: <strong>{{ $party->name }}</strong>
        @if(isset($branch) && $branch)
            <span class="badge bg-primary ms-2">{{ $branch->name }}</span>
        @endif
    </h4>
    <div class="d-flex gap-2 no-print">
        <div class="btn-group btn-group-sm" role="group">
            <a href="{{ route('accounting.ledger.party', [$type, $party->id]) }}"
               class="btn {{ $mode === 'amounts' ? 'btn-primary' : 'btn-outline-primary' }}">كشف الذمم</a>
            <a href="{{ route('accounting.ledger.party', [$type, $party->id, 'mode' => 'full']) }}"
               class="btn {{ $mode === 'full' ? 'btn-primary' : 'btn-outline-primary' }}">كشف كامل (نشاط)</a>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> طباعة الكشف
        </button>
        <a href="{{ route('accounting.ledger.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-right"></i> دفتر الأستاذ
        </a>
    </div>
</div>

<div class="alert alert-light border small no-print">
    <i class="bi bi-info-circle"></i>
    @if($mode === 'full')
        <strong>كشف حساب كامل (نشاط)</strong> لهذا {{ $label }} — يعرض كل الفواتير (نقدًا وآجلًا) والمدفوعات برصيد جارٍ.
        الرصيد الختامي يساوي رصيد الذمم (المعاملات النقدية تُصافى لصفر فلا تغيّر الرصيد).
    @else
        <strong>كشف الأستاذ المساعد (دفتر الذمم)</strong> لهذا {{ $label }} — يعرض الحركات الآجلة والمدفوعات فقط،
        ومجموعه يطابق حساب المراقبة في الأستاذ العام. المبيعات/المشتريات النقدية لا تظهر هنا (لا تُنشئ ذمة).
    @endif
    الرصيد: <strong>مدين</strong> = يستحق عليه لصالحنا، <strong>دائن</strong> = مستحق له علينا.
</div>

{{-- Date Filter --}}
<div class="card mb-3 border-0 shadow-sm no-print">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">من تاريخ</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom?->toDateString() }}">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">إلى تاريخ</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo?->toDateString() }}">
            </div>
            @include('components.branch-filter')
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> تصفية</button>
                <a href="{{ route('accounting.ledger.party', [$type, $party->id]) }}" class="btn btn-outline-secondary btn-sm">كل الحركات</a>
            </div>
        </form>
    </div>
</div>

{{-- Summary --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center py-2">
            <div class="small text-muted">رصيد الافتتاح</div>
            <div class="fw-bold font-monospace fs-6">{{ number_format(abs($openingBalance), 2) }}
                <span class="small {{ $openingBalance >= 0 ? 'text-success' : 'text-danger' }}">({{ partyBalanceLabel($openingBalance) }})</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center py-2">
            <div class="small text-muted">إجمالي المدين</div>
            <div class="fw-bold font-monospace fs-6">{{ number_format($periodDebit, 2) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center py-2">
            <div class="small text-muted">إجمالي الدائن</div>
            <div class="fw-bold font-monospace fs-6">{{ number_format($periodCredit, 2) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center py-2">
            <div class="small text-muted">الرصيد الختامي</div>
            <div class="fw-bold font-monospace fs-6 {{ $closingBalance >= 0 ? 'text-primary' : 'text-danger' }}">
                {{ number_format(abs($closingBalance), 2) }}
                <span class="small">({{ partyBalanceLabel($closingBalance) }})</span>
            </div>
        </div>
    </div>
</div>

{{-- Transactions --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:100px">التاريخ</th>
                        <th style="width:140px">المرجع / المستند</th>
                        <th>الوصف</th>
                        <th style="width:120px" class="text-end">مدين</th>
                        <th style="width:120px" class="text-end">دائن</th>
                        <th style="width:150px" class="text-end">الرصيد الجاري</th>
                    </tr>
                </thead>
                <tbody>
                @if($dateFrom && $openingBalance != 0)
                <tr class="table-secondary">
                    <td colspan="5" class="text-muted small">رصيد مرحّل قبل {{ $dateFrom->toDateString() }}</td>
                    <td class="text-end font-monospace fw-semibold">{{ number_format(abs($openingBalance), 2) }} ({{ partyBalanceLabel($openingBalance) }})</td>
                </tr>
                @endif

                @forelse($items as $line)
                <tr>
                    <td class="text-nowrap">{{ $line->date }}</td>
                    <td>
                        @if($line->url)
                            <a href="{{ $line->url }}" class="font-monospace text-decoration-none">{{ $line->ref }}</a>
                        @else
                            <span class="font-monospace">{{ $line->ref }}</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $line->desc }}</td>
                    <td class="text-end font-monospace">{{ $line->debit > 0 ? number_format($line->debit, 2) : '—' }}</td>
                    <td class="text-end font-monospace">{{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}</td>
                    <td class="text-end font-monospace {{ $line->running_balance >= 0 ? '' : 'text-danger' }}">
                        {{ number_format(abs($line->running_balance), 2) }}
                        <span class="small">({{ partyBalanceLabel($line->running_balance) }})</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-4 d-block mb-1"></i>لا توجد حركات لهذا {{ $label }} في الفترة</td></tr>
                @endforelse
                </tbody>
                @if($items->isNotEmpty())
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td colspan="3" class="text-end">الإجمالي</td>
                        <td class="text-end font-monospace">{{ number_format($periodDebit, 2) }}</td>
                        <td class="text-end font-monospace">{{ number_format($periodCredit, 2) }}</td>
                        <td class="text-end font-monospace {{ $closingBalance >= 0 ? 'text-primary' : 'text-danger' }}">
                            {{ number_format(abs($closingBalance), 2) }} ({{ partyBalanceLabel($closingBalance) }})
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
