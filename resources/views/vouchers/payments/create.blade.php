@extends('layouts.app')
@section('page-title', 'سند صرف جديد')

@section('content')
<div class="card" style="max-width:780px; margin:auto;">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-arrow-up-circle text-danger me-2"></i>إنشاء سند صرف</h5>
    </div>
    <div class="card-body">

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form action="{{ route('vouchers.payments.store') }}" method="POST">
        @csrf

        {{-- Branch selector (SAP: Company Code) — visible to global users only --}}
        @if(!($branchLocked ?? false))
        <div class="alert alert-light border mb-3 py-2">
            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0 fw-semibold small">
                        <i class="bi bi-building-fill-check text-primary me-1"></i>الفرع (Company Code)
                    </label>
                </div>
                <div class="col-md-4">
                    <select name="branch_id" class="form-select form-select-sm">
                        <option value="">— فرع المستخدم الافتراضي —</option>
                        @foreach($branches ?? [] as $b)
                            <option value="{{ $b->id }}" {{ old('branch_id', $branchId ?? '') == $b->id ? 'selected':'' }}>
                                [{{ $b->code }}] {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <small class="text-muted">أنت مدير عام — يمكنك اختيار الفرع الذي ينتمي إليه هذا السند</small>
                </div>
            </div>
        </div>
        @else
        <input type="hidden" name="branch_id" value="{{ auth()->user()?->branch_id }}">
        @endif

        <div class="row g-3 mb-3">
            {{-- Voucher date --}}
            <div class="col-md-4">
                <label class="form-label fw-semibold">التاريخ <span class="text-danger">*</span></label>
                <input type="date" name="voucher_date" class="form-control @error('voucher_date') is-invalid @enderror"
                       value="{{ old('voucher_date', today()->format('Y-m-d')) }}" required>
                @error('voucher_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Second date (كما في الأصيل) --}}
            <div class="col-md-4">
                <label class="form-label fw-semibold">تاريخ ثانٍ (اختياري)</label>
                <input type="date" name="second_date" class="form-control"
                       value="{{ old('second_date') }}">
            </div>

            {{-- Payment method --}}
            <div class="col-md-4">
                <label class="form-label fw-semibold">طريقة الصرف <span class="text-danger">*</span></label>
                <select name="payment_method" class="form-select" required>
                    <option value="cash"          {{ old('payment_method','cash') == 'cash'          ? 'selected':'' }}>نقدي</option>
                    <option value="bank"          {{ old('payment_method')        == 'bank'          ? 'selected':'' }}>تحويل بنكي</option>
                    <option value="mobile_wallet" {{ old('payment_method')        == 'mobile_wallet' ? 'selected':'' }}>محفظة إلكترونية</option>
                </select>
            </div>
        </div>

        <div class="row g-3 mb-3">
            {{-- Paid to --}}
            <div class="col-md-8">
                <label class="form-label fw-semibold">صُرف إلى <span class="text-danger">*</span></label>
                <input type="text" name="paid_to" class="form-control @error('paid_to') is-invalid @enderror"
                       value="{{ old('paid_to') }}" placeholder="اسم الشخص أو الجهة" required>
                @error('paid_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Supplier link (optional) --}}
            <div class="col-md-4">
                <label class="form-label fw-semibold">ربط بمورد (اختياري)</label>
                <select name="supplier_id" class="form-select form-select-sm">
                    <option value="">— لا يوجد —</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected':'' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row g-3 mb-3">
            {{-- Currency --}}
            <div class="col-md-3">
                <label class="form-label fw-semibold">العملة</label>
                <select name="currency_id" id="vCurrency" class="form-select" onchange="onCurrencyChange()">
                    <option value="" data-rate="1">الأساسية ({{ $currency }})</option>
                    @foreach(($currencies ?? collect())->where('is_base', false) as $cur)
                        <option value="{{ $cur->id }}" data-rate="{{ $cur->exchange_rate }}"
                            {{ old('currency_id') == $cur->id ? 'selected':'' }}>
                            {{ $cur->code }} — صرف: {{ rtrim(rtrim(number_format($cur->exchange_rate, 6), '0'), '.') }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Amount in FC --}}
            <div class="col-md-3" id="fcWrap" style="display:none">
                <label class="form-label fw-semibold">المبلغ بالعملة <span class="text-danger">*</span></label>
                <input type="number" name="amount_fc" id="vAmountFc" class="form-control @error('amount_fc') is-invalid @enderror"
                       value="{{ old('amount_fc') }}" step="0.0001" min="0.01" oninput="onCurrencyChange()">
                @error('amount_fc')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Amount (base) --}}
            <div class="col-md-3">
                <label class="form-label fw-semibold">المبلغ ({{ $currency }}) <span class="text-danger">*</span></label>
                <input type="number" name="amount" id="vAmount" class="form-control @error('amount') is-invalid @enderror"
                       value="{{ old('amount') }}" step="0.01" min="0.01" placeholder="0.00">
                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Reference --}}
            <div class="col-md-3">
                <label class="form-label fw-semibold">المرجع / رقم الوثيقة</label>
                <input type="text" name="reference" class="form-control"
                       value="{{ old('reference') }}" placeholder="رقم الفاتورة أو المرجع">
            </div>
        </div>

        {{-- ── Accounting section ── --}}
        <hr class="my-3">
        <p class="fw-bold text-muted small mb-2"><i class="bi bi-journal-text me-1"></i> القيد المحاسبي</p>

        <div class="row g-3 mb-3">
            {{-- Debit account --}}
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    الحساب المقابل <span class="text-danger">*</span>
                    <span class="badge bg-primary ms-1">مدين</span>
                </label>
                <select name="account_id" id="vAccount" class="form-select @error('account_id') is-invalid @enderror" required onchange="fetchBalance()">
                    <option value="">اختر الحساب</option>
                    @php
                        $typeLabels = ['asset'=>'أصول','liability'=>'التزامات','equity'=>'حقوق الملكية','revenue'=>'إيرادات','expense'=>'مصروفات'];
                    @endphp
                    @foreach($accounts->groupBy('type') as $type => $group)
                        <optgroup label="{{ $typeLabels[$type] ?? $type }}">
                            @foreach($group as $acc)
                                <option value="{{ $acc->id }}" {{ old('account_id') == $acc->id ? 'selected':'' }}>
                                    {{ $acc->code }} — {{ $acc->name }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div id="vBalance" class="form-text fw-semibold" style="display:none"></div>
            </div>

            {{-- Cash/Bank account (CREDIT side) --}}
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    حساب النقدية / البنك <span class="text-danger">*</span>
                    <span class="badge bg-success ms-1">دائن</span>
                </label>
                <select name="cash_account_id" class="form-select @error('cash_account_id') is-invalid @enderror" required>
                    <option value="">اختر الحساب</option>
                    @foreach($accounts->where('type', 'asset') as $acc)
                        <option value="{{ $acc->id }}"
                            {{ old('cash_account_id', $defaultCashAccount?->id) == $acc->id ? 'selected':'' }}>
                            {{ $acc->code }} — {{ $acc->name }}
                        </option>
                    @endforeach
                </select>
                @error('cash_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Notes --}}
        <div class="mb-4">
            <label class="form-label fw-semibold">ملاحظات</label>
            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger px-4">
                <i class="bi bi-save me-1"></i> حفظ وترحيل
            </button>
            <a href="{{ route('vouchers.payments.index') }}" class="btn btn-secondary">إلغاء</a>
        </div>

        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
// ── العملة: تحويل تلقائي للعملة الأساسية ─────────────────────────────────
function onCurrencyChange() {
    const sel    = document.getElementById('vCurrency');
    const opt    = sel.options[sel.selectedIndex];
    const rate   = parseFloat(opt.dataset.rate) || 1;
    const fcWrap = document.getElementById('fcWrap');
    const amount = document.getElementById('vAmount');
    const fc     = document.getElementById('vAmountFc');

    if (sel.value) {
        fcWrap.style.display = '';
        amount.readOnly = true;
        const v = parseFloat(fc.value) || 0;
        amount.value = v > 0 ? (v * rate).toFixed(2) : '';
    } else {
        fcWrap.style.display = 'none';
        amount.readOnly = false;
    }
}

// ── رصيد الحساب المقابل ──────────────────────────────────────────────────
function fetchBalance() {
    const id  = document.getElementById('vAccount').value;
    const box = document.getElementById('vBalance');
    if (!id) { box.style.display = 'none'; return; }
    fetch(`{{ url('vouchers/account-balance') }}/${id}`)
        .then(r => r.ok ? r.json() : null)
        .then(d => {
            if (!d) { box.style.display = 'none'; return; }
            box.style.display = '';
            box.className = 'form-text fw-semibold ' + (d.side === 'مدين' ? 'text-danger' : 'text-success');
            box.innerHTML = 'الرصيد الحالي: ' + d.balance_fmt + ' {{ $currency }} (' + d.side + ')';
        })
        .catch(() => { box.style.display = 'none'; });
}

document.addEventListener('DOMContentLoaded', () => { onCurrencyChange(); fetchBalance(); });
</script>
@endsection
