@extends('layouts.app')
@section('page-title', 'تسجيل دفعة — ' . $expenseInvoice->invoice_number)

@section('content')
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-cash-coin text-success me-2"></i>
                    تسجيل دفعة لفاتورة: <strong>{{ $expenseInvoice->invoice_number }}</strong>
                </h5>
            </div>
            <div class="card-body">

                {{-- Invoice summary --}}
                <div class="alert alert-light border mb-4">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="text-muted small">الإجمالي</div>
                            <div class="fw-bold">{{ number_format($expenseInvoice->total_amount, 2) }} {{ $currency }}</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">المدفوع</div>
                            <div class="fw-bold text-success">{{ number_format($expenseInvoice->paid_amount, 2) }} {{ $currency }}</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">المتبقي</div>
                            <div class="fw-bold text-danger">{{ number_format($expenseInvoice->remainingAmount(), 2) }} {{ $currency }}</div>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="small text-muted text-center">
                        المورد: <strong>{{ $expenseInvoice->vendor_name }}</strong>
                    </div>
                </div>

                {{-- Accounting note --}}
                <div class="alert alert-light border-start border-success border-3 mb-4 small">
                    <strong>القيد عند الدفع:</strong><br>
                    <span class="text-danger">مدين:</span> ذمم الموردين (2000) — يُغلق الالتزام &nbsp;&nbsp;
                    <span class="text-success">دائن:</span> الصندوق أو البنك
                </div>

                <form action="{{ route('expense-invoices.pay', $expenseInvoice) }}" method="POST">
                    @csrf

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">تاريخ الدفع <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control"
                                   value="{{ old('payment_date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">طريقة الدفع <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="cash" {{ old('payment_method','cash') === 'cash' ? 'selected':'' }}>نقدي</option>
                                <option value="bank" {{ old('payment_method') === 'bank' ? 'selected':'' }}>بنكي</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">المبلغ <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="amount" class="form-control"
                                       value="{{ old('amount', $expenseInvoice->remainingAmount()) }}"
                                       step="0.01" min="0.01"
                                       max="{{ $expenseInvoice->remainingAmount() }}" required>
                                <span class="input-group-text">{{ $currency }}</span>
                            </div>
                            <div class="form-text">الحد الأقصى: {{ number_format($expenseInvoice->remainingAmount(), 2) }} {{ $currency }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">مرجع الدفع</label>
                            <input type="text" name="reference" class="form-control"
                                   value="{{ old('reference') }}"
                                   placeholder="رقم الشيك / رقم التحويل">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save me-1"></i> تسجيل الدفعة وترحيل القيد
                    </button>
                    <a href="{{ route('expense-invoices.show', $expenseInvoice) }}" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
