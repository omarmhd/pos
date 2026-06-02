{{-- resources/views/sales/show.blade.php --}}
@extends('layouts.app')

@section('page-title', 'تفاصيل الفاتورة')

@section('content')
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-receipt"></i> فاتورة: {{ $sale->invoice_number }}</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('pos.receipt', $sale) }}" class="btn btn-primary btn-sm" target="_blank">
                            <i class="bi bi-printer"></i> طباعة
                        </a>
                        <a href="{{ route('sales.pdf', $sale) }}" class="btn btn-danger btn-sm" target="_blank">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </a>
                        @if(auth()->user()->hasRole('admin') && $sale->is_posted && !$sale->is_reversed)
                        <a href="{{ route('reversals.create', ['original_type' => \App\Models\Sale::class, 'original_id' => $sale->id]) }}"
                           class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-arrow-counterclockwise"></i> قيد عكسي
                        </a>
                        @endif
                        @if($sale->is_reversed)
                        <span class="badge bg-warning text-dark align-self-center">مُعكوسة</span>
                        @endif
                        <a href="{{ route('sales.index') }}" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-right"></i> رجوع
                        </a>
                    </div>
                </div>
                <div class="card-body">

                    <div class="row mb-4 g-3">
                        {{-- ── Invoice info ── --}}
                        <div class="col-md-6">
                            <h6 class="text-muted fw-semibold mb-2"><i class="bi bi-info-circle me-1"></i>معلومات الفاتورة</h6>
                            <dl class="row mb-0 small">
                                <dt class="col-5">رقم الفاتورة</dt>
                                <dd class="col-7 fw-semibold">{{ $sale->invoice_number }}</dd>

                                <dt class="col-5">التاريخ</dt>
                                <dd class="col-7">{{ $sale->created_at->format('Y-m-d H:i') }}</dd>

                                <dt class="col-5">الكاشير</dt>
                                <dd class="col-7">{{ $sale->user->name }}</dd>

                                <dt class="col-5">طريقة الدفع</dt>
                                <dd class="col-7">
                                    @php
                                        $methodMap = [
                                            'cash'            => ['success', 'نقدي'],
                                            'card'            => ['primary', 'بطاقة بنكية'],
                                            'mobile_wallet'   => ['info',    'محفظة إلكترونية'],
                                            'deposit_balance' => ['purple',  'رصيد إيداع'],
                                        ];
                                        [$color,$label] = $methodMap[$sale->payment_method] ?? ['secondary',$sale->payment_method];
                                    @endphp
                                    <span class="badge bg-{{ $color }}">{{ $label }}</span>
                                </dd>

                                @if($sale->balance_used > 0)
                                <dt class="col-5">رصيد إيداع مُستخدم</dt>
                                <dd class="col-7 text-primary fw-semibold">{{ number_format($sale->balance_used, 2) }} {{ $currency }}</dd>
                                @endif

                                <dt class="col-5">حالة الترحيل</dt>
                                <dd class="col-7">
                                    @if($sale->is_posted)
                                        <span class="badge bg-success">مُرحَّل</span>
                                        @if($journalEntry)
                                            <a href="{{ route('journal_entries.show', $journalEntry) }}"
                                               class="btn btn-link btn-sm py-0 px-1 text-primary" title="عرض القيد المحاسبي">
                                                <i class="bi bi-journal-text"></i> {{ $journalEntry->entry_number }}
                                            </a>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary">غير مُرحَّل</span>
                                    @endif
                                </dd>
                            </dl>
                        </div>

                        {{-- ── Customer + Summary ── --}}
                        <div class="col-md-6">
                            {{-- Customer card --}}
                            @if($sale->customer)
                            <div class="alert alert-info py-2 px-3 mb-2 d-flex align-items-center gap-2" style="border-radius:8px;">
                                <i class="bi bi-person-circle fs-5"></i>
                                <div>
                                    <div class="fw-semibold">
                                        <a href="{{ route('customers.show', $sale->customer) }}" class="text-decoration-none text-dark">
                                            {{ $sale->customer->name }}
                                        </a>
                                    </div>
                                    <small class="text-muted">
                                        {{ $sale->is_credit ? 'بيع آجل' : 'عميل محدد' }}
                                        @if($sale->customer->phone) — {{ $sale->customer->phone }} @endif
                                    </small>
                                </div>
                                @if($sale->is_credit)
                                    <span class="badge bg-warning text-dark ms-auto">آجل</span>
                                @endif
                            </div>
                            @endif

                            {{-- Financial summary --}}
                            <div class="card bg-light border-0">
                                <div class="card-body py-2 px-3">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-muted">المجموع الفرعي</span>
                                        <strong>{{ number_format($sale->subtotal, 2) }} {{ $currency }}</strong>
                                    </div>
                                    @if($sale->discount > 0)
                                    <div class="d-flex justify-content-between small mb-1 text-danger">
                                        <span>خصم</span>
                                        <strong>− {{ number_format($sale->discount, 2) }} {{ $currency }}</strong>
                                    </div>
                                    @endif
                                    @if($sale->tax > 0)
                                    <div class="d-flex justify-content-between small mb-1 text-warning">
                                        <span>ضريبة</span>
                                        <strong>+ {{ number_format($sale->tax, 2) }} {{ $currency }}</strong>
                                    </div>
                                    @endif
                                    <hr class="my-1">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-semibold">الإجمالي</span>
                                        <strong class="text-success fs-5">{{ number_format($sale->total_amount, 2) }} {{ $currency }}</strong>
                                    </div>
                                    @if(!$sale->is_credit)
                                    <div class="d-flex justify-content-between small mt-1 text-muted">
                                        <span>المدفوع نقداً</span>
                                        <span>{{ number_format($sale->paid_amount, 2) }} {{ $currency }}</span>
                                    </div>
                                    @if($sale->balance_used > 0)
                                    <div class="d-flex justify-content-between small text-primary">
                                        <span>رصيد إيداع مُستخدم</span>
                                        <span>{{ number_format($sale->balance_used, 2) }} {{ $currency }}</span>
                                    </div>
                                    @endif
                                    @if($sale->change_amount > 0)
                                    <div class="d-flex justify-content-between small text-success">
                                        <span>الباقي للعميل</span>
                                        <span>{{ number_format($sale->change_amount, 2) }} {{ $currency }}</span>
                                    </div>
                                    @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ── Items table ── --}}
                    <h6 class="mb-2 text-muted fw-semibold"><i class="bi bi-cart3 me-1"></i>المنتجات</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>المنتج</th>
                                <th class="text-center">الكمية</th>
                                <th class="text-end">سعر الوحدة</th>
                                <th class="text-end">المجموع</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($sale->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item->product->name }}</td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-end">{{ number_format($item->unit_price, 2) }} {{ $currency }}</td>
                                    <td class="text-end"><strong>{{ number_format($item->total_price, 2) }} {{ $currency }}</strong></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- ── Journal Entry lines ── --}}
                    @if($journalEntry)
                    <div class="card border-0 bg-light">
                        <div class="card-header bg-transparent border-0 pb-0">
                            <h6 class="mb-0 text-muted fw-semibold">
                                <i class="bi bi-journal-text me-1"></i>القيد المحاسبي
                                <a href="{{ route('journal_entries.show', $journalEntry) }}"
                                   class="btn btn-link btn-sm py-0 text-primary">
                                    {{ $journalEntry->entry_number }} <i class="bi bi-box-arrow-up-right ms-1"></i>
                                </a>
                            </h6>
                        </div>
                        <div class="card-body pt-2 p-0">
                            <table class="table table-sm mb-0 small">
                                <thead class="table-light">
                                <tr>
                                    <th>الحساب</th>
                                    <th class="text-end">مدين</th>
                                    <th class="text-end">دائن</th>
                                    <th>البيان</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($journalEntry->lines as $line)
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary opacity-75 me-1">{{ $line->account?->code }}</span>
                                        {{ $line->account?->name }}
                                    </td>
                                    <td class="text-end">{{ $line->debit  > 0 ? number_format($line->debit,  2) : '—' }}</td>
                                    <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}</td>
                                    <td class="text-muted">{{ $line->line_description }}</td>
                                </tr>
                                @endforeach
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>المجموع</td>
                                    <td class="text-end">{{ number_format($journalEntry->debit_total, 2) }}</td>
                                    <td class="text-end">{{ number_format($journalEntry->credit_total, 2) }}</td>
                                    <td>
                                        @if($journalEntry->is_balanced)
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>متوازن</span>
                                        @else
                                            <span class="badge bg-danger">غير متوازن</span>
                                        @endif
                                    </td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    @elseif(!$sale->is_posted)
                    <div class="alert alert-warning small py-2 mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        هذه الفاتورة لم تُرحَّل بعد في دفتر الأستاذ.
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
@endsection
