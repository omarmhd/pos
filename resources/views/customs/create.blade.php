@extends('layouts.app')

@section('page-title', 'إقرار جمركي جديد')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-box-arrow-in-down text-primary"></i> تسجيل إقرار جمركي (ضريبة الواردات)</h5>
            </div>
            <div class="card-body">
                @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
                @endif

                <form action="{{ route('customs-declarations.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">تاريخ الإقرار <span class="text-danger">*</span></label>
                            <input type="date" name="declaration_date" class="form-control"
                                   value="{{ old('declaration_date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رقم البيان الجمركي الرسمي</label>
                            <input type="text" name="customs_ref" class="form-control" value="{{ old('customs_ref') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">المورد (مسجَّل)</label>
                            <select name="supplier_id" class="form-select">
                                <option value="">— اختر موردًا —</option>
                                @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">أو اسم المورد الأجنبي</label>
                            <input type="text" name="vendor_name" class="form-control" value="{{ old('vendor_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">قيمة الواردات + الرسوم الجمركية ({{ $currency }}) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="total_amount" class="form-control"
                                   value="{{ old('total_amount', 0) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ضريبة القيمة المضافة على الواردات (مدخلات) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="tax_amount" class="form-control"
                                   value="{{ old('tax_amount', 0) }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 py-2 small mb-0">
                        <i class="bi bi-info-circle"></i>
                        ضريبة مدخلات الواردات تُخصَم من صافي ض.ق.م المستحقة عند إدراج هذا الإقرار في كشف الإيرادات والمصروفات.
                    </div>

                    <div class="mt-3 text-end">
                        <a href="{{ route('customs-declarations.index') }}" class="btn btn-secondary">إلغاء</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
