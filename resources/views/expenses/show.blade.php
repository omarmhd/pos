@extends('layouts.app')
@section('page-title', 'فاتورة مصروف — ' . $expenseInvoice->invoice_number)

@section('content')
<div class="row">
    <div class="col-lg-10 mx-auto">

        {{-- Main invoice card --}}
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-receipt-cutoff text-danger me-2"></i>
                    {{ $expenseInvoice->invoice_number }}
                </h5>
                <div class="d-flex gap-2">
                    @can('expenses.pay')
                    @if($expenseInvoice->payment_status !== 'paid')
                    <a href="{{ route('expense-invoices.pay-form', $expenseInvoice) }}"
                       class="btn btn-success btn-sm">
                        <i class="bi bi-cash-coin me-1"></i> تسجيل دفعة
                    </a>
                    @endif
                    @endcan
                    <a href="{{ route('expense-invoices.index') }}" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-right"></i> رجوع
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    {{-- Invoice info --}}
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">معلومات الفاتورة</h6>
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:45%">رقم الفاتورة:</th>
                                <td><strong>{{ $expenseInvoice->invoice_number }}</strong></td></tr>
                            <tr><th class="text-muted">تاريخ الفاتورة:</th>
                                <td>{{ $expenseInvoice->invoice_date->format('Y-m-d') }}</td></tr>
                            @if($expenseInvoice->due_date)
                            <tr><th class="text-muted">تاريخ الاستحقاق:</th>
                                <td class="{{ $expenseInvoice->isOverdue() ? 'text-danger fw-bold' : '' }}">
                                    {{ $expenseInvoice->due_date->format('Y-m-d') }}
                                    @if($expenseInvoice->isOverdue())
                                        <span class="badge bg-danger ms-1">متأخرة</span>
                                    @endif
                                </td>
                            </tr>
                            @endif
                            @if($expenseInvoice->vendor_invoice_number)
                            <tr><th class="text-muted">رقم فاتورة المورد:</th>
                                <td>{{ $expenseInvoice->vendor_invoice_number }}</td></tr>
                            @endif
                            <tr><th class="text-muted">المسجِّل:</th>
                                <td>{{ $expenseInvoice->user->name }}</td></tr>
                            @if($expenseInvoice->branch)
                            <tr><th class="text-muted">الفرع:</th>
                                <td><span class="badge bg-primary">{{ $expenseInvoice->branch->name }}</span></td></tr>
                            @endif
                        </table>
                    </div>

                    {{-- Vendor + financial summary --}}
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">المورد والملخص المالي</h6>
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:45%">المورد/الجهة:</th>
                                <td><strong>{{ $expenseInvoice->vendor_name }}</strong></td></tr>
                            @if($expenseInvoice->supplier)
                            <tr><th class="text-muted">مرتبط بمورد:</th>
                                <td>{{ $expenseInvoice->supplier->name }}</td></tr>
                            @endif
                            <tr><th class="text-muted">حساب المصروف:</th>
                                <td>{{ $expenseInvoice->expenseAccount->code }}
                                    — {{ $expenseInvoice->expenseAccount->name }}</td></tr>
                            <tr><th class="text-muted">الإجمالي:</th>
                                <td class="fw-bold fs-5">
                                    {{ number_format($expenseInvoice->total_amount, 2) }} {{ $currency }}</td></tr>
                            <tr><th class="text-muted">المدفوع:</th>
                                <td class="text-success">
                                    {{ number_format($expenseInvoice->paid_amount, 2) }} {{ $currency }}</td></tr>
                            <tr><th class="text-muted">المتبقي:</th>
                                <td class="{{ $expenseInvoice->remainingAmount() > 0 ? 'text-danger fw-bold' : 'text-success' }}">
                                    {{ number_format($expenseInvoice->remainingAmount(), 2) }} {{ $currency }}</td></tr>
                            <tr><th class="text-muted">الحالة:</th>
                                <td>
                                    @if($expenseInvoice->payment_status === 'paid')
                                        <span class="badge bg-success">مدفوعة بالكامل</span>
                                    @elseif($expenseInvoice->payment_status === 'partial')
                                        <span class="badge bg-warning text-dark">مدفوعة جزئياً</span>
                                    @else
                                        <span class="badge bg-secondary">غير مدفوعة</span>
                                    @endif
                                </td></tr>
                        </table>
                        @if($expenseInvoice->notes)
                        <div class="alert alert-light border small mt-2">
                            <i class="bi bi-sticky me-1"></i> {{ $expenseInvoice->notes }}
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Payments history --}}
                @if($expenseInvoice->payments->count() > 0)
                <h6 class="text-muted mb-2">سجل الدفعات</h6>
                <table class="table table-sm table-bordered mb-4">
                    <thead class="table-light">
                    <tr>
                        <th>التاريخ</th>
                        <th>طريقة الدفع</th>
                        <th class="text-end">المبلغ</th>
                        <th>مرجع</th>
                        <th>المسجِّل</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($expenseInvoice->payments as $pmt)
                    <tr>
                        <td>{{ $pmt->payment_date->format('Y-m-d') }}</td>
                        <td>
                            @if($pmt->payment_method === 'bank')
                                <span class="badge bg-info text-dark">بنكي</span>
                            @else
                                <span class="badge bg-success">نقدي</span>
                            @endif
                        </td>
                        <td class="text-end fw-bold">{{ number_format($pmt->amount, 2) }} {{ $currency }}</td>
                        <td class="text-muted small">{{ $pmt->reference ?? '—' }}</td>
                        <td>{{ $pmt->user?->name ?? '—' }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
                @endif

                {{-- Journal Entry --}}
                @if($expenseInvoice->journalEntry)
                <div class="card bg-light">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="bi bi-journal-text me-1"></i>
                            القيد المحاسبي — {{ $expenseInvoice->journalEntry->reference }}
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-secondary">
                            <tr><th>الحساب</th><th class="text-end">مدين</th>
                                <th class="text-end">دائن</th><th>البيان</th></tr>
                            </thead>
                            <tbody>
                            @foreach($expenseInvoice->journalEntry->lines as $line)
                            <tr>
                                <td>{{ $line->account->code }} — {{ $line->account->name }}</td>
                                <td class="text-end">{{ $line->debit  > 0 ? number_format($line->debit,  2).' '.$currency : '—' }}</td>
                                <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, 2).' '.$currency : '—' }}</td>
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
    </div>
</div>
@endsection
