@extends('layouts.app')
@section('page-title', 'إيداع / استرداد رصيد — ' . $customer->name)

@section('content')
<div class="row justify-content-center">
<div class="col-lg-6">

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-wallet2 text-primary me-2"></i>
            إيداع / استرداد رصيد — <strong>{{ $customer->name }}</strong>
        </h5>
    </div>
    <div class="card-body">

        {{-- Current balance display --}}
        <div class="alert {{ $balance > 0 ? 'alert-success' : 'alert-secondary' }} d-flex align-items-center mb-4">
            <i class="bi bi-piggy-bank fs-4 me-2"></i>
            <div>
                <div class="fw-bold">الرصيد الحالي للعميل</div>
                <div class="fs-5 fw-bold">{{ number_format($balance, 2) }} {{ $currency }}</div>
            </div>
        </div>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form action="{{ route('customer-deposits.store', $customer) }}" method="POST">
        @csrf

        {{-- Type toggle --}}
        <div class="mb-3">
            <label class="form-label fw-semibold">نوع العملية <span class="text-danger">*</span></label>
            <div class="d-flex gap-3">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="type" id="typeDeposit"
                           value="deposit" {{ old('type','deposit') === 'deposit' ? 'checked':'' }}>
                    <label class="form-check-label fw-semibold text-success" for="typeDeposit">
                        <i class="bi bi-arrow-down-circle me-1"></i> إيداع (العميل يودع لدينا)
                    </label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="type" id="typeRefund"
                           value="refund" {{ old('type') === 'refund' ? 'checked':'' }}>
                    <label class="form-check-label fw-semibold text-danger" for="typeRefund">
                        <i class="bi bi-arrow-up-circle me-1"></i> استرداد (نرد له المال)
                    </label>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">التاريخ <span class="text-danger">*</span></label>
                <input type="date" name="voucher_date" class="form-control @error('voucher_date') is-invalid @enderror"
                       value="{{ old('voucher_date', today()->format('Y-m-d')) }}" required>
                @error('voucher_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">طريقة الدفع <span class="text-danger">*</span></label>
                <select name="payment_method" class="form-select" required>
                    <option value="cash"          {{ old('payment_method','cash') == 'cash'          ? 'selected':'' }}>نقدي</option>
                    <option value="bank"          {{ old('payment_method')        == 'bank'          ? 'selected':'' }}>تحويل بنكي</option>
                    <option value="mobile_wallet" {{ old('payment_method')        == 'mobile_wallet' ? 'selected':'' }}>محفظة إلكترونية</option>
                </select>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">المبلغ ({{ $currency }}) <span class="text-danger">*</span></label>
                <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                       value="{{ old('amount') }}" step="0.01" min="0.01" placeholder="0.00" required>
                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">المرجع</label>
                <input type="text" name="reference" class="form-control"
                       value="{{ old('reference') }}" placeholder="رقم إيصال أو مرجع...">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">ملاحظات</label>
            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-save me-1"></i> حفظ وترحيل
            </button>
            <a href="{{ route('customers.show', $customer) }}" class="btn btn-secondary">إلغاء</a>
        </div>

        </form>
    </div>
</div>

</div>
</div>
@endsection
