@extends('layouts.app')
@section('page-title', 'سجل الحساب: ' . $account->code)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-journal-bookmark"></i>
        <code>{{ $account->code }}</code> — {{ $account->name }}
    </h4>
    <a href="{{ route('accounting.ledger.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-right"></i> قائمة الحسابات
    </a>
</div>

{{-- Date Filter --}}
<div class="card mb-3 border-0 shadow-sm">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">من تاريخ</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="{{ $dateFrom?->toDateString() }}">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">إلى تاريخ</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="{{ $dateTo?->toDateString() }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> تصفية
                </button>
                <a href="{{ route('accounting.ledger.show', $account->id) }}" class="btn btn-outline-secondary btn-sm">
                    كل الحركات
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center py-2">
            <div class="small text-muted">رصيد الافتتاح</div>
            <div class="fw-bold font-monospace fs-6 {{ $openingBalance >= 0 ? 'text-success' : 'text-danger' }}">
                {{ number_format(abs($openingBalance), 2) }}
                @if($openingBalance < 0)<span class="text-danger small">(عكسي)</span>@endif
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
                @if($closingBalance < 0)<span class="text-danger small">(عكسي)</span>@endif
            </div>
        </div>
    </div>
</div>

{{-- Transactions Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:100px">التاريخ</th>
                        <th style="width:110px">رقم القيد</th>
                        <th>الوصف</th>
                        <th style="width:120px" class="text-end">مدين</th>
                        <th style="width:120px" class="text-end">دائن</th>
                        <th style="width:130px" class="text-end">الرصيد الجاري</th>
                    </tr>
                </thead>
                <tbody>
                @if($dateFrom && $openingBalance != 0)
                <tr class="table-secondary">
                    <td colspan="5" class="text-muted small">رصيد مرحّل قبل {{ $dateFrom->toDateString() }}</td>
                    <td class="text-end font-monospace fw-semibold">{{ number_format(abs($openingBalance), 2) }}</td>
                </tr>
                @endif

                @forelse($items as $line)
                <tr>
                    <td class="text-nowrap">{{ $line->entry_date }}</td>
                    <td>
                        <a href="{{ route('journal_entries.show', $line->journal_entry_id) }}"
                           class="font-monospace text-decoration-none">
                            {{ $line->entry_number }}
                        </a>
                    </td>
                    <td class="text-muted small">
                        {{ $line->line_description ?: $line->je_description }}
                    </td>
                    <td class="text-end font-monospace">
                        @if($line->debit > 0)
                            {{ number_format($line->debit, 2) }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end font-monospace">
                        @if($line->credit > 0)
                            {{ number_format($line->credit, 2) }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end font-monospace {{ $line->running_balance >= 0 ? '' : 'text-danger' }}">
                        {{ number_format(abs($line->running_balance), 2) }}
                        @if($line->running_balance < 0)
                            <span class="small">(ع)</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                        لا توجد حركات في الفترة المحددة
                    </td>
                </tr>
                @endforelse
                </tbody>
                @if($items->isNotEmpty())
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td colspan="3" class="text-end">الإجمالي</td>
                        <td class="text-end font-monospace">{{ number_format($periodDebit, 2) }}</td>
                        <td class="text-end font-monospace">{{ number_format($periodCredit, 2) }}</td>
                        <td class="text-end font-monospace {{ $closingBalance >= 0 ? 'text-primary' : 'text-danger' }}">
                            {{ number_format(abs($closingBalance), 2) }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
