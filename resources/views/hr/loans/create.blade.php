@extends('layouts.app')
@section('page-title', 'صرف سلفة موظف')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-6">
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-cash-coin text-warning me-2"></i>صرف سلفة موظف</h5>
    </div>
    <div class="card-body">

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- القيد المحاسبي توضيحي --}}
        <div class="alert alert-info small mb-4 d-flex gap-2">
            <i class="bi bi-journal-text fs-5 flex-shrink-0 mt-1"></i>
            <div>
                <strong>القيد عند الصرف:</strong><br>
                مدين: سلف الموظفين (1250) — دائن: الصندوق/البنك<br>
                <small class="text-muted">تُستقطع تلقائياً من الراتب عند اعتماد مسير الرواتب</small>
            </div>
        </div>

        <form action="{{ route('hr.loans.store') }}" method="POST" id="loanForm">
        @csrf

        <div class="row g-3 mb-3">
            <div class="col-12">
                <label class="form-label fw-semibold">الموظف <span class="text-danger">*</span></label>
                <select name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                    <option value="">اختر الموظف</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ old('employee_id') == $emp->id ? 'selected':'' }}>
                            {{ $emp->employee_code }} — {{ $emp->name }}
                        </option>
                    @endforeach
                </select>
                @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">تاريخ السلفة <span class="text-danger">*</span></label>
                <input type="date" name="loan_date" class="form-control @error('loan_date') is-invalid @enderror"
                       value="{{ old('loan_date', today()->format('Y-m-d')) }}" required>
                @error('loan_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">طريقة الصرف <span class="text-danger">*</span></label>
                <select name="payment_method" class="form-select" required>
                    <option value="cash" {{ old('payment_method','cash') == 'cash' ? 'selected':'' }}>نقدي</option>
                    <option value="bank" {{ old('payment_method')        == 'bank' ? 'selected':'' }}>تحويل بنكي</option>
                </select>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">مبلغ السلفة ({{ $currency }}) <span class="text-danger">*</span></label>
                <input type="number" name="amount" id="loanAmount"
                       class="form-control @error('amount') is-invalid @enderror"
                       value="{{ old('amount') }}" step="0.01" min="1" oninput="calcInstallment()" required>
                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">عدد الأقساط (شهر) <span class="text-danger">*</span></label>
                <input type="number" name="installments_total" id="loanInstallments"
                       class="form-control @error('installments_total') is-invalid @enderror"
                       value="{{ old('installments_total', 1) }}" min="1" max="60" oninput="calcInstallment()" required>
                @error('installments_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- القسط الشهري المحسوب --}}
        <div class="alert alert-success py-2 mb-3">
            <i class="bi bi-calculator me-1"></i>
            القسط الشهري: <strong id="installmentDisplay">—</strong> {{ $currency }}
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">ملاحظات</label>
            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-warning px-4 fw-bold">
                <i class="bi bi-cash-coin me-1"></i> صرف السلفة وترحيل القيد
            </button>
            <a href="{{ route('hr.loans.index') }}" class="btn btn-secondary">إلغاء</a>
        </div>

        </form>
    </div>
</div>
</div>
</div>
@endsection

@section('scripts')
<script>
function calcInstallment() {
    var amount = parseFloat(document.getElementById('loanAmount').value) || 0;
    var n      = parseInt(document.getElementById('loanInstallments').value) || 1;
    var inst   = n > 0 ? (amount / n).toFixed(2) : '—';
    document.getElementById('installmentDisplay').textContent = amount > 0 ? inst : '—';
}
$(document).ready(function () { calcInstallment(); });
</script>
@endsection
