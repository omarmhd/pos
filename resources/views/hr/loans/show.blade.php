@extends('layouts.app')
@section('page-title', 'سلفة موظف')

@section('content')
<div class="row g-4">

    {{-- ── Loan card ── --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-cash-coin text-warning me-1"></i>تفاصيل السلفة</span>
                @if($loan->status === 'active')
                    <span class="badge bg-warning text-dark">نشطة</span>
                @elseif($loan->status === 'settled')
                    <span class="badge bg-success">مُسدَّدة بالكامل</span>
                @else
                    <span class="badge bg-secondary">ملغاة</span>
                @endif
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">الموظف</dt>
                    <dd class="col-7 fw-semibold">{{ $loan->employee?->name }}</dd>

                    <dt class="col-5 text-muted">تاريخ السلفة</dt>
                    <dd class="col-7">{{ $loan->loan_date->format('Y-m-d') }}</dd>

                    <dt class="col-5 text-muted">المبلغ الأصلي</dt>
                    <dd class="col-7 fw-bold">{{ number_format($loan->amount, 2) }} {{ $currency }}</dd>

                    <dt class="col-5 text-muted">القسط الشهري</dt>
                    <dd class="col-7">{{ number_format($loan->monthly_installment, 2) }} {{ $currency }}</dd>

                    <dt class="col-5 text-muted">الأقساط</dt>
                    <dd class="col-7">{{ $loan->installments_paid }} / {{ $loan->installments_total }} قسط</dd>

                    <dt class="col-5 text-muted">الرصيد المتبقي</dt>
                    <dd class="col-7 fw-bold fs-5 {{ $loan->remaining_balance > 0 ? 'text-danger' : 'text-success' }}">
                        {{ number_format($loan->remaining_balance, 2) }} {{ $currency }}
                    </dd>
                </dl>

                {{-- Progress bar --}}
                @php $pct = $loan->installments_total > 0 ? ($loan->installments_paid / $loan->installments_total * 100) : 0; @endphp
                <div class="mt-3">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>نسبة السداد</span>
                        <span>{{ round($pct) }}%</span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-success" style="width:{{ $pct }}%"></div>
                    </div>
                </div>

                @if($loan->notes)
                <p class="mt-3 small text-muted mb-0"><i class="bi bi-chat-left-text me-1"></i>{{ $loan->notes }}</p>
                @endif
            </div>
            <div class="card-footer d-flex gap-2 bg-white">
                <a href="{{ route('hr.loans.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right me-1"></i> العودة
                </a>
                @if($loan->status === 'active')
                @can('hr.manage_loans')
                <form action="{{ route('hr.loans.cancel', $loan) }}" method="POST" class="ms-auto"
                      onsubmit="return confirm('إلغاء هذه السلفة؟')">
                    @csrf @method('PATCH')
                    <button class="btn btn-outline-secondary btn-sm text-danger">
                        <i class="bi bi-x-circle me-1"></i> إلغاء السلفة
                    </button>
                </form>
                @endcan
                @endif
            </div>
        </div>
    </div>

    {{-- ── Journal Entry ── --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-journal-text me-1"></i> قيد صرف السلفة
                @if($loan->journalEntry)
                    <a href="{{ route('journal_entries.show', $loan->journalEntry) }}"
                       class="float-start btn btn-link btn-sm py-0">
                        {{ $loan->journalEntry->entry_number }}
                    </a>
                @endif
            </div>
            <div class="card-body p-0">
                @if($loan->journalEntry)
                <table class="table table-sm mb-0 small">
                    <thead class="table-light">
                    <tr>
                        <th>الحساب</th>
                        <th class="text-end">مدين</th>
                        <th class="text-end">دائن</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($loan->journalEntry->lines as $line)
                    <tr>
                        <td>
                            <span class="badge bg-secondary opacity-75 me-1">{{ $line->account?->code }}</span>
                            {{ $line->account?->name }}
                        </td>
                        <td class="text-end">{{ $line->debit  > 0 ? number_format($line->debit,  2) : '—' }}</td>
                        <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
                @else
                    <div class="p-3 text-center text-muted">لا يوجد قيد مرتبط</div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
