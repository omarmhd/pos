@extends('layouts.app')

@section('page-title', 'تسجيل دفعة للمورد')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">

        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-truck"></i> {{ $supplier->name }}</h5>
            </div>
            <div class="card-body py-2">
                <div class="row text-center">
                    <div class="col">
                        <div class="small text-muted">إجمالي المستحق</div>
                        <div class="fw-bold text-danger fs-5">{{ number_format($outstandingTotal, 2) }} ج.م</div>
                    </div>
                    <div class="col">
                        <div class="small text-muted">فواتير غير مسددة</div>
                        <div class="fw-bold fs-5">{{ $unpaidPurchases->count() }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-cash-coin"></i> بيانات الدفعة</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('supplier-payments.store', $supplier) }}">
                    @csrf

                    {{-- Purchase selector --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">الفاتورة <span class="text-danger">*</span></label>
                        <select name="purchase_id" id="purchase_id" class="form-select @error('purchase_id') is-invalid @enderror"
                                onchange="updateRemaining(this)">
                            <option value="">-- اختر الفاتورة --</option>
                            @foreach($unpaidPurchases as $p)
                                <option value="{{ $p->id }}"
                                    data-remaining="{{ $p->remaining }}"
                                    {{ old('purchase_id', $selectedPurchase?->id) == $p->id ? 'selected' : '' }}>
                                    {{ $p->invoice_number }} — متبقي: {{ number_format($p->remaining, 2) }} ج.م
                                </option>
                            @endforeach
                        </select>
                        @error('purchase_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Remaining amount hint --}}
                    <div id="remaining-hint" class="alert alert-info py-2 small mb-3" style="display:none">
                        المبلغ المتبقي لهذه الفاتورة: <strong id="remaining-value"></strong> ج.م
                    </div>

                    {{-- Amount --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">المبلغ المدفوع <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="amount" id="amount" step="0.01" min="0.01"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   value="{{ old('amount') }}" placeholder="0.00">
                            <span class="input-group-text">ج.م</span>
                        </div>
                        @error('amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    {{-- Payment method --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">طريقة الدفع <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                            <option value="cash"          {{ old('payment_method','cash') == 'cash'          ? 'selected' : '' }}>نقدي</option>
                            <option value="card"          {{ old('payment_method')        == 'card'          ? 'selected' : '' }}>بطاقة (بنك)</option>
                            <option value="mobile_wallet" {{ old('payment_method')        == 'mobile_wallet' ? 'selected' : '' }}>محفظة إلكترونية</option>
                        </select>
                        @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Date --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">تاريخ الدفع <span class="text-danger">*</span></label>
                        <input type="date" name="paid_at"
                               class="form-control @error('paid_at') is-invalid @enderror"
                               value="{{ old('paid_at', now()->toDateString()) }}">
                        @error('paid_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Notes --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">ملاحظات</label>
                        <textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> تسجيل الدفعة وترحيل القيد
                        </button>
                        <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-outline-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Unpaid invoices table --}}
        @if($unpaidPurchases->count())
        <div class="card mt-3">
            <div class="card-header bg-white">
                <h6 class="mb-0 text-danger"><i class="bi bi-exclamation-circle"></i> الفواتير غير المسددة</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th class="text-end">الإجمالي</th>
                            <th class="text-end">المدفوع</th>
                            <th class="text-end">المتبقي</th>
                            <th class="text-center">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($unpaidPurchases as $p)
                        <tr>
                            <td>{{ $p->invoice_number }}</td>
                            <td class="text-end">{{ number_format($p->total_amount, 2) }}</td>
                            <td class="text-end text-success">{{ number_format($p->paid_amount, 2) }}</td>
                            <td class="text-end text-danger fw-bold">{{ number_format($p->remaining, 2) }}</td>
                            <td class="text-center">
                                @if($p->payment_status === 'partial')
                                    <span class="badge bg-warning">جزئي</span>
                                @else
                                    <span class="badge bg-danger">غير مدفوع</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection

@section('scripts')
<script>
function updateRemaining(sel) {
    const opt = sel.options[sel.selectedIndex];
    const rem = opt.dataset.remaining;
    if (rem) {
        document.getElementById('remaining-value').textContent = parseFloat(rem).toFixed(2);
        document.getElementById('remaining-hint').style.display = '';
        document.getElementById('amount').max = rem;
        document.getElementById('amount').value = parseFloat(rem).toFixed(2);
    } else {
        document.getElementById('remaining-hint').style.display = 'none';
    }
}
// Trigger on load if pre-selected
document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('purchase_id');
    if (sel.value) updateRemaining(sel);
});
</script>
@endsection
