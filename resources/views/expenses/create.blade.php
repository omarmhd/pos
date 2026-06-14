@extends('layouts.app')
@section('page-title', 'فاتورة مصروف جديدة')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-receipt-cutoff text-danger me-2"></i>تسجيل فاتورة مصروف جديدة
        </h5>
    </div>
    <div class="card-body">

        {{-- Accounting explanation box --}}
        <div class="alert alert-light border-start border-danger border-3 mb-4 small">
            <strong>القيد عند الحفظ:</strong><br>
            <span class="text-danger">مدين:</span> حساب المصروف المحدد &nbsp;&nbsp;
            <span class="text-success">دائن:</span> ذمم الموردين (2000) — يُسجَّل الالتزام قبل الدفع
        </div>

        <form action="{{ route('expense-invoices.store') }}" method="POST">
            @csrf

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">تاريخ الفاتورة <span class="text-danger">*</span></label>
                    <input type="date" name="invoice_date" class="form-control"
                           value="{{ old('invoice_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">تاريخ الاستحقاق</label>
                    <input type="date" name="due_date" class="form-control"
                           value="{{ old('due_date') }}">
                    <div class="form-text">اتركه فارغاً إذا لا يوجد موعد محدد</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">رقم فاتورة المورد</label>
                    <input type="text" name="vendor_invoice_number" class="form-control"
                           value="{{ old('vendor_invoice_number') }}"
                           placeholder="رقم الفاتورة الصادرة من المورد">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">اسم المورد / الجهة <span class="text-danger">*</span></label>
                    <input type="text" name="vendor_name"
                           class="form-control @error('vendor_name') is-invalid @enderror"
                           value="{{ old('vendor_name') }}"
                           placeholder="مثال: شركة الكهرباء، مكتب محاسبة المحاسب، صاحب العقار"
                           required>
                    @error('vendor_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">
                        المورد في النظام
                        <small class="text-muted">(اختياري — للربط بكشف حساب المورد)</small>
                    </label>
                    <select name="supplier_id" class="form-select">
                        <option value="">— لا يوجد مورد مرتبط —</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected':'' }}>
                                {{ $s->name }}{{ $s->company ? ' — '.$s->company : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label">
                        حساب المصروف <span class="text-danger">*</span>
                        <small class="text-muted">(الحساب الذي سيُخصَّم منه)</small>
                    </label>
                    <select name="expense_account_id"
                            class="form-select @error('expense_account_id') is-invalid @enderror" required>
                        <option value="">اختر حساب المصروف</option>
                        @foreach($expenseAccounts as $acc)
                            <option value="{{ $acc->id }}" {{ old('expense_account_id') == $acc->id ? 'selected':'' }}>
                                {{ $acc->code }} — {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('expense_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">
                        مثال: 6100 مصاريف إيجار | 6200 مصاريف رواتب | 6300 مصاريف خدمات
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">المبلغ الإجمالي <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" name="total_amount"
                               class="form-control @error('total_amount') is-invalid @enderror"
                               value="{{ old('total_amount') }}"
                               step="0.01" min="0.01" required>
                        <span class="input-group-text">{{ $currency }}</span>
                    </div>
                    @error('total_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">منها ضريبة مدخلات</label>
                    <div class="input-group">
                        <input type="number" name="tax_amount" class="form-control"
                               value="{{ old('tax_amount', 0) }}" step="0.01" min="0">
                        <span class="input-group-text">{{ $currency }}</span>
                    </div>
                    <div class="form-text">تُرحَّل لحساب 1260 وتدخل في صافي الضريبة</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="1"
                              placeholder="وصف المصروف أو أي ملاحظات">{{ old('notes') }}</textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-danger">
                <i class="bi bi-save me-1"></i> حفظ الفاتورة وترحيل القيد
            </button>
            <a href="{{ route('expense-invoices.index') }}" class="btn btn-secondary">
                <i class="bi bi-x-circle"></i> إلغاء
            </a>
        </form>
    </div>
</div>
@endsection
