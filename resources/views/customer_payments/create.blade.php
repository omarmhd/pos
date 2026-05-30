@extends('layouts.app')
@section('title', 'تسجيل دفعة')
@section('page-title', 'تسجيل دفعة من عميل')

@section('content')
<div class="col-lg-6 mx-auto">
    <div class="card">
        <div class="card-header">
            <i class="bi bi-cash-coin"></i>
            تحصيل من: <strong>{{ $customer->name }}</strong>
            @if($customer->outstandingBalance() > 0)
            <span class="badge bg-danger ms-2">مستحق: {{ number_format($customer->outstandingBalance(), 2) }}</span>
            @endif
        </div>
        <div class="card-body">

            @if($unpaidSales->isEmpty())
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> لا توجد فواتير آجلة مستحقة لهذا العميل
            </div>
            @else

            <form method="POST" action="{{ route('customer-payments.store', $customer) }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-bold">الفاتورة <span class="text-danger">*</span></label>
                    <select name="sale_id" id="saleSelect" class="form-select @error('sale_id') is-invalid @enderror" required>
                        <option value="">— اختر فاتورة —</option>
                        @foreach($unpaidSales as $sale)
                        <option value="{{ $sale->id }}"
                                data-outstanding="{{ $sale->outstanding }}"
                                {{ (old('sale_id') == $sale->id || ($selectedSale && $selectedSale->id == $sale->id)) ? 'selected' : '' }}>
                            {{ $sale->invoice_number }} —
                            {{ $sale->created_at->format('Y/m/d') }} —
                            مستحق: {{ number_format($sale->outstanding, 2) }}
                        </option>
                        @endforeach
                    </select>
                    @error('sale_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">المبلغ <span class="text-danger">*</span></label>
                    <input type="number" name="amount" id="amountInput"
                           class="form-control form-control-lg @error('amount') is-invalid @enderror"
                           value="{{ old('amount', $selectedSale?->outstanding) }}"
                           min="0.01" step="0.01" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div id="outstandingHint" class="form-text text-muted"></div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">طريقة الدفع</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>نقدي</option>
                            <option value="card" {{ old('payment_method') == 'card' ? 'selected' : '' }}>بطاقة</option>
                            <option value="mobile_wallet" {{ old('payment_method') == 'mobile_wallet' ? 'selected' : '' }}>محفظة إلكترونية</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">تاريخ الاستلام</label>
                        <input type="date" name="received_at" class="form-control"
                               value="{{ old('received_at', now()->toDateString()) }}" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> تأكيد الدفعة وترحيل القيد
                    </button>
                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
            @endif

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const saleSelect   = document.getElementById('saleSelect');
    const amountInput  = document.getElementById('amountInput');
    const hint         = document.getElementById('outstandingHint');

    function updateHint() {
        const opt = saleSelect.options[saleSelect.selectedIndex];
        const outstanding = parseFloat(opt.dataset.outstanding || 0);
        if (outstanding > 0) {
            hint.textContent = 'الرصيد المستحق لهذه الفاتورة: ' + outstanding.toFixed(2);
            amountInput.max = outstanding;
        } else {
            hint.textContent = '';
        }
    }

    saleSelect.addEventListener('change', updateHint);
    updateHint();
</script>
@endsection
