@extends('layouts.app')
@section('page-title', 'تفاصيل الوردية #' . $shift->id)

@section('content')
<div class="row g-4">

    {{-- Header card --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">
                        <i class="bi bi-cash-register me-2 text-warning"></i>
                        وردية نقدية #{{ $shift->id }}
                    </h5>
                    <small class="text-muted">
                        {{ $shift->user?->name }} —
                        {{ $shift->opened_at->format('Y-m-d H:i') }}
                        @if($shift->closed_at) → {{ $shift->closed_at->format('H:i') }} @endif
                    </small>
                </div>
                <div class="d-flex gap-2">
                    @if($shift->status === 'open')
                        @can('pos.shifts.close')
                        <a href="{{ route('pos.shifts.close', $shift) }}"
                           class="btn btn-warning btn-sm">
                            <i class="bi bi-lock-fill me-1"></i> إقفال الوردية
                        </a>
                        @endcan
                    @endif
                    <a href="{{ route('pos.shifts.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI row --}}
    <div class="col-md-3">
        <div class="stat-card {{ $shift->status === 'open' ? 'success' : 'secondary' }}">
            <h6>الحالة</h6>
            <h3>{{ $shift->status === 'open' ? 'مفتوحة' : 'مغلقة' }}</h3>
            <small>{{ $shift->durationLabel() }}</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card primary">
            <h6>رصيد افتتاحي</h6>
            <h3>{{ number_format($shift->opening_amount, 2) }}</h3>
            <small>{{ $currency }}</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card {{ ($shift->variance_amount !== null && abs($shift->variance_amount) > 0.005) ? ($shift->variance_amount < 0 ? 'danger' : 'info') : 'success' }}">
            <h6>الفرق النقدي</h6>
            <h3>
                @if($shift->variance_amount !== null)
                    {{ number_format(abs($shift->variance_amount), 2) }}
                @else
                    —
                @endif
            </h3>
            <small>{{ $shift->varianceLabel() }}</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <h6>المبيعات النقدية</h6>
            <h3>{{ number_format($shift->cash_sales ?? 0, 2) }}</h3>
            <small>{{ $currency }}</small>
        </div>
    </div>

    {{-- Details --}}
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><strong><i class="bi bi-cash-coin me-1"></i>تفاصيل النقدية</strong></div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="text-muted">رصيد افتتاحي</td>
                        <td class="text-end font-monospace">{{ number_format($shift->opening_amount, 2) }} {{ $currency }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">+ مبيعات نقدية</td>
                        <td class="text-end font-monospace text-success">+ {{ number_format($shift->cash_sales ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">− مرتجعات نقدية</td>
                        <td class="text-end font-monospace text-danger">− {{ number_format($shift->cash_returns ?? 0, 2) }}</td>
                    </tr>
                    <tr class="border-top fw-bold">
                        <td>المبلغ المتوقع</td>
                        <td class="text-end font-monospace">
                            {{ $shift->expected_amount !== null ? number_format($shift->expected_amount, 2) : '—' }} {{ $currency }}
                        </td>
                    </tr>
                    @if($shift->closing_amount !== null)
                    <tr class="fw-bold">
                        <td>جرد الإقفال الفعلي</td>
                        <td class="text-end font-monospace">{{ number_format($shift->closing_amount, 2) }} {{ $currency }}</td>
                    </tr>
                    <tr class="fw-bold {{ $shift->variance_amount < 0 ? 'text-danger' : ($shift->variance_amount > 0 ? 'text-info' : 'text-success') }}">
                        <td>الفرق</td>
                        <td class="text-end font-monospace fs-5">
                            {{ $shift->variance_amount > 0 ? '+' : '' }}{{ number_format($shift->variance_amount, 2) }} {{ $currency }}
                            <span class="badge bg-{{ $shift->varianceColor() }} ms-1">{{ $shift->varianceLabel() }}</span>
                        </td>
                    </tr>
                    @endif
                </table>
                @if($shift->variance_reason)
                <div class="alert alert-light border mt-2 small">
                    <strong>سبب الفرق:</strong> {{ $shift->variance_reason }}
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><strong><i class="bi bi-receipt me-1"></i>توزيع المبيعات بطريقة الدفع</strong></div>
            <div class="card-body">
                @forelse($salesSummary as $row)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-secondary">
                        {{ match($row->payment_method) {
                            'cash' => 'نقدي', 'card' => 'بطاقة',
                            'mobile_wallet' => 'محفظة', 'deposit_balance' => 'رصيد إيداع',
                            default => $row->payment_method
                        } }}
                    </span>
                    <span>{{ $row->count }} فاتورة</span>
                    <strong class="font-monospace">{{ number_format($row->total, 2) }} {{ $currency }}</strong>
                </div>
                @empty
                <p class="text-muted text-center py-3">لا توجد مبيعات في هذه الوردية</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- تسوية فرق الوردية --}}
    @if($shift->status === 'closed' && $shift->variance_amount !== null && abs($shift->variance_amount) > 0.005)
    @php $isShort = $shift->variance_amount < 0; @endphp
    <div class="col-12">
        <div class="card border-{{ $isShort ? 'danger' : 'info' }}">
            <div class="card-header bg-{{ $isShort ? 'danger' : 'info' }} bg-opacity-10">
                <strong><i class="bi bi-sliders me-1"></i> تسوية الفرق ({{ $isShort ? 'عجز' : 'فائض' }})</strong>
            </div>
            <div class="card-body">
                @if($shift->variance_settled)
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle me-1"></i> تمت التسوية:
                        <strong>{{ [
                            'charge_cashier'     => 'تحميل العجز على الكاشير (ذمة موظف)',
                            'accept_expense'     => 'اعتماد العجز كمصروف على المحل',
                            'customer_liability' => 'إثبات الفائض كأمانة عميل',
                            'accept_income'      => 'اعتماد الفائض كإيراد للمحل',
                        ][$shift->variance_settlement_type] ?? $shift->variance_settlement_type }}</strong>
                        @if($shift->settledBy) — بواسطة {{ $shift->settledBy->name }} @endif
                        @if($shift->variance_settled_at) ({{ $shift->variance_settled_at->format('Y-m-d H:i') }}) @endif
                        @if($shift->settlementEntry)
                            <a href="{{ route('journal_entries.show', $shift->variance_settlement_entry_id) }}"
                               class="btn btn-sm btn-outline-success ms-2">{{ $shift->settlementEntry->entry_number }}</a>
                        @endif
                    </div>
                @elseif(auth()->user()?->can('pos.shifts.close'))
                    <form method="POST" action="{{ route('pos.shifts.settle-variance', $shift) }}">
                        @csrf
                        <p class="small text-muted mb-2">
                            الفرق مُثبت حاليًا في حساب {{ $isShort ? 'العجز (مصروف)' : 'الفائض (إيراد)' }}.
                            اختر المعالجة المحاسبية (لا تمسّ الصندوق — إعادة تصنيف فقط):
                        </p>
                        @if($isShort)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="treatment" id="t1" value="charge_cashier" checked>
                            <label class="form-check-label" for="t1">تحميل على الكاشير — ذمة موظف (مدين سلف الموظف 1250 / دائن العجز 6530)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="treatment" id="t2" value="accept_expense">
                            <label class="form-check-label" for="t2">اعتماد كمصروف على المحل (يبقى في حساب العجز — دون قيد إضافي)</label>
                        </div>
                        @else
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="treatment" id="t1" value="customer_liability" checked>
                            <label class="form-check-label" for="t1">إثبات كأمانة عميل — التزام (مدين الفائض 4160 / دائن أمانات العملاء 2050)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="treatment" id="t2" value="accept_income">
                            <label class="form-check-label" for="t2">اعتماد كإيراد للمحل (يبقى في حساب الفائض — دون قيد إضافي)</label>
                        </div>
                        @endif
                        <button type="submit" class="btn btn-primary btn-sm mt-3"
                                onclick="return confirm('تأكيد تسوية الفرق؟');">
                            <i class="bi bi-check2 me-1"></i> تنفيذ التسوية
                        </button>
                    </form>
                @else
                    <p class="text-muted mb-0">الفرق لم يُسوَّ بعد. تحتاج صلاحية إقفال الورديات لإجراء التسوية.</p>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- GL Entry --}}
    @if($shift->journalEntry)
    <div class="col-12">
        <div class="card border-success">
            <div class="card-header bg-success bg-opacity-10">
                <strong><i class="bi bi-journal-check me-1 text-success"></i>القيد المحاسبي</strong>
                <a href="{{ route('journal_entries.show', $shift->journal_entry_id) }}"
                   class="btn btn-sm btn-outline-success ms-2">
                    {{ $shift->journalEntry->entry_number }}
                </a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>الحساب</th>
                            <th class="text-end">مدين</th>
                            <th class="text-end">دائن</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($shift->journalEntry->lines as $line)
                        <tr>
                            <td>{{ $line->account?->code }} — {{ $line->account?->name }}</td>
                            <td class="text-end font-monospace text-primary">
                                {{ $line->debit > 0 ? number_format($line->debit, 2) : '—' }}
                            </td>
                            <td class="text-end font-monospace text-success">
                                {{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection
