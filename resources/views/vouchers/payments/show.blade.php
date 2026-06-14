@extends('layouts.app')
@section('page-title', 'سند صرف — ' . $payment->voucher_number)

@section('content')
<div class="row g-4">

    {{-- ── Voucher card ── --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold">
                    <i class="bi bi-arrow-up-circle text-danger me-1"></i>
                    سند صرف — {{ $payment->voucher_number }}
                </span>
                @if($payment->is_posted)
                    <span class="badge bg-success">مُرحَّل</span>
                @else
                    <span class="badge bg-warning text-dark">مسودة</span>
                @endif
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">التاريخ</dt>
                    <dd class="col-7">{{ $payment->voucher_date->format('Y-m-d') }}</dd>

                    <dt class="col-5 text-muted">صُرف إلى</dt>
                    <dd class="col-7 fw-semibold">{{ $payment->paid_to }}</dd>

                    @if($payment->supplier)
                    <dt class="col-5 text-muted">المورد المرتبط</dt>
                    <dd class="col-7">{{ $payment->supplier->name }}</dd>
                    @endif

                    <dt class="col-5 text-muted">المبلغ</dt>
                    <dd class="col-7 fw-bold text-danger" style="font-size:1.1rem;">
                        {{ number_format($payment->amount, 2) }} {{ $currency }}
                    </dd>

                    @if($payment->currency_id && $payment->currency)
                    <dt class="col-5 text-muted">المبلغ بالعملة</dt>
                    <dd class="col-7">
                        {{ number_format($payment->amount_fc, 2) }} {{ $payment->currency->code }}
                        <small class="text-muted">(سعر الصرف: {{ rtrim(rtrim(number_format($payment->exchange_rate, 6), '0'), '.') }})</small>
                    </dd>
                    @endif

                    @if($payment->second_date)
                    <dt class="col-5 text-muted">تاريخ ثانٍ</dt>
                    <dd class="col-7">{{ $payment->second_date->format('Y-m-d') }}</dd>
                    @endif

                    <dt class="col-5 text-muted">طريقة الصرف</dt>
                    <dd class="col-7">{{ $payment->paymentMethodLabel() }}</dd>

                    <dt class="col-5 text-muted">الحساب المدين</dt>
                    <dd class="col-7">
                        <span class="badge bg-primary">{{ $payment->account?->code }}</span>
                        {{ $payment->account?->name }}
                    </dd>

                    <dt class="col-5 text-muted">الحساب الدائن</dt>
                    <dd class="col-7">
                        <span class="badge bg-success">{{ $payment->cashAccount?->code }}</span>
                        {{ $payment->cashAccount?->name }}
                    </dd>

                    @if($payment->reference)
                    <dt class="col-5 text-muted">المرجع</dt>
                    <dd class="col-7">{{ $payment->reference }}</dd>
                    @endif

                    @if($payment->notes)
                    <dt class="col-5 text-muted">ملاحظات</dt>
                    <dd class="col-7">{{ $payment->notes }}</dd>
                    @endif

                    <dt class="col-5 text-muted">أُنشئ بواسطة</dt>
                    <dd class="col-7">{{ $payment->user?->name }} — {{ $payment->created_at->format('Y-m-d H:i') }}</dd>
                </dl>
            </div>
            <div class="card-footer d-flex gap-2 bg-white">
                <a href="{{ route('vouchers.payments.pdf', $payment) }}" class="btn btn-outline-danger btn-sm" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-1"></i> طباعة PDF
                </a>
                <a href="{{ route('vouchers.payments.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right me-1"></i> العودة للقائمة
                </a>
                @can('vouchers.delete')
                @if(!$payment->is_posted)
                <form action="{{ route('vouchers.payments.destroy', $payment) }}" method="POST" class="ms-auto"
                      onsubmit="return confirm('حذف هذا السند؟')">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-secondary btn-sm text-danger">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </form>
                @endif
                @endcan
            </div>
        </div>
    </div>

    {{-- ── Journal Entry card ── --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-journal-text me-1"></i> القيد المحاسبي
                @if($payment->journalEntry)
                    <a href="{{ route('journal_entries.show', $payment->journalEntry) }}"
                       class="float-start btn btn-link btn-sm py-0 text-primary">
                        {{ $payment->journalEntry->entry_number }}
                    </a>
                @endif
            </div>
            <div class="card-body p-0">
                @if($payment->journalEntry)
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>الحساب</th>
                        <th class="text-end">مدين</th>
                        <th class="text-end">دائن</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($payment->journalEntry->lines as $line)
                    <tr>
                        <td>
                            <small class="text-muted">{{ $line->account?->code }}</small>
                            {{ $line->account?->name }}
                            @if($line->line_description)
                                <br><small class="text-muted">{{ $line->line_description }}</small>
                            @endif
                        </td>
                        <td class="text-end">{{ $line->debit  > 0 ? number_format($line->debit,  2) : '—' }}</td>
                        <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                    <tr>
                        <td>المجموع</td>
                        <td class="text-end">{{ number_format($payment->journalEntry->debit_total,  2) }}</td>
                        <td class="text-end">{{ number_format($payment->journalEntry->credit_total, 2) }}</td>
                    </tr>
                    </tfoot>
                </table>
                @else
                    <div class="p-3 text-muted text-center">لا يوجد قيد مرتبط</div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
