@extends('layouts.app')

@section('page-title', 'فاتورة إيراد خدمات جديدة')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-lightning-charge text-primary"></i> تسجيل فاتورة إيراد خدمات (IFRS 15)</h5>
            </div>
            <div class="card-body">
                @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif

                <form action="{{ route('service-invoices.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">التاريخ <span class="text-danger">*</span></label>
                            <input type="date" name="invoice_date" class="form-control" value="{{ old('invoice_date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">العميل (مسجَّل)</label>
                            <select name="customer_id" class="form-select">
                                <option value="">— اختر —</option>
                                @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">أو اسم العميل</label>
                            <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">حساب إيراد الخدمة</label>
                            <select name="service_account_id" class="form-select">
                                @foreach($revenueAccounts as $a)
                                <option value="{{ $a->id }}" {{ $a->code == $defaultService ? 'selected' : '' }}>{{ $a->code }} — {{ $a->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">نوع التحصيل</label>
                            <select name="is_credit" class="form-select">
                                <option value="0">نقدي</option>
                                <option value="1" {{ old('is_credit') == '1' ? 'selected' : '' }}>آجل (على الحساب)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الإجمالي شامل الضريبة ({{ $currency }}) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="total_amount" class="form-control" value="{{ old('total_amount', 0) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">منها ضريبة القيمة المضافة (مخرجات)</label>
                            <input type="number" step="0.01" min="0" name="tax_amount" class="form-control" value="{{ old('tax_amount', 0) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">بيان الخدمة</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 py-2 small mb-0">
                        <i class="bi bi-info-circle"></i>
                        يُرحَّل تلقائياً: مدين (نقدية/ذمم العميل) — دائن (إيرادات الخدمات صافي + ض.ق.م المستحقة).
                    </div>

                    <div class="mt-3 text-end">
                        <a href="{{ route('service-invoices.index') }}" class="btn btn-secondary">إلغاء</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> حفظ وترحيل</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
