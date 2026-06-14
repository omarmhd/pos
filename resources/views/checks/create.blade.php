@extends('layouts.app')
@section('page-title', 'تسجيل شيك جديد')

@section('content')
<div class="row justify-content-center">
<div class="col-xl-8 col-lg-10">

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-bank2 text-primary me-2"></i>تسجيل شيك جديد</h5>
    </div>
    <div class="card-body">
        @include('components.alerts')

        <form action="{{ route('checks.store') }}" method="POST" id="checkForm">
            @csrf

            {{-- نوع الشيك --}}
            <div class="mb-4">
                <label class="form-label fw-semibold">نوع الشيك <span class="text-danger">*</span></label>
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="type" id="typeReceivable" value="receivable"
                               {{ old('type', 'receivable') === 'receivable' ? 'checked' : '' }}>
                        <label class="form-check-label" for="typeReceivable">
                            <span class="badge bg-success fs-6 px-3 py-2">
                                <i class="bi bi-arrow-down-circle me-1"></i> وارد (من عميل)
                            </span>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="type" id="typePayable" value="payable"
                               {{ old('type') === 'payable' ? 'checked' : '' }}>
                        <label class="form-check-label" for="typePayable">
                            <span class="badge bg-danger fs-6 px-3 py-2">
                                <i class="bi bi-arrow-up-circle me-1"></i> صادر (لمورد)
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="row g-3">

                {{-- الطرف الآخر — عميل أو مورد --}}
                <div class="col-md-6" id="customerSection">
                    <label class="form-label">العميل</label>
                    <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" id="customerSelect">
                        <option value="">— اختر عميلاً (اختياري) —</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6" id="supplierSection" style="display:none">
                    <label class="form-label">المورد</label>
                    <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" id="supplierSelect">
                        <option value="">— اختر مورداً (اختياري) —</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected' : '' }}>
                                {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">اسم الجهة (نص حر — إذا لم تختر من القائمة)</label>
                    <input type="text" name="party_name" class="form-control @error('party_name') is-invalid @enderror"
                           value="{{ old('party_name') }}" placeholder="اسم الشخص أو الشركة">
                    @error('party_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- رقم الشيك الورقي --}}
                <div class="col-md-6">
                    <label class="form-label">رقم الشيك (على الورق)</label>
                    <input type="text" name="check_ref" class="form-control @error('check_ref') is-invalid @enderror"
                           value="{{ old('check_ref') }}" placeholder="مثال: 001234">
                    @error('check_ref')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- التاريخ والاستحقاق --}}
                <div class="col-md-6">
                    <label class="form-label fw-semibold">تاريخ الشيك <span class="text-danger">*</span></label>
                    <input type="date" name="check_date" class="form-control @error('check_date') is-invalid @enderror"
                           value="{{ old('check_date', date('Y-m-d')) }}" required>
                    @error('check_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">تاريخ الاستحقاق <span class="text-danger">*</span></label>
                    <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                           value="{{ old('due_date', date('Y-m-d')) }}" required>
                    @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- المبلغ --}}
                <div class="col-md-6">
                    <label class="form-label fw-semibold">المبلغ ({{ $currency }}) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01"
                           class="form-control @error('amount') is-invalid @enderror"
                           value="{{ old('amount') }}" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">بالعملة الأساسية. لشيك بعملة أجنبية: املأ الحقول أدناه ويُحتسب تلقائياً.</div>
                </div>

                {{-- عملة الشيك (اختياري — للشيكات بعملة أجنبية) --}}
                @if(($currencies ?? collect())->count() > 1)
                <div class="col-md-4">
                    <label class="form-label">عملة الشيك</label>
                    <select name="currency_id" class="form-select">
                        <option value="">— الأساسية —</option>
                        @foreach($currencies as $cu)
                        <option value="{{ $cu->id }}" {{ old('currency_id') == $cu->id ? 'selected' : '' }}>
                            {{ $cu->code }} — {{ $cu->name }}{{ $cu->is_base ? ' (أساسية)' : '' }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">المبلغ بالعملة الأجنبية</label>
                    <input type="number" name="foreign_amount" step="0.01" min="0" class="form-control" value="{{ old('foreign_amount') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">سعر الصرف</label>
                    <input type="number" name="exchange_rate" step="0.000001" min="0" class="form-control" value="{{ old('exchange_rate', 1) }}">
                    <div class="form-text">المبلغ الأساسي = المبلغ الأجنبي × سعر الصرف.</div>
                </div>
                @endif

                {{-- البنك --}}
                <div class="col-md-4">
                    <label class="form-label">اسم البنك</label>
                    <input type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror"
                           value="{{ old('bank_name') }}" placeholder="مثال: بنك مصر">
                    @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- فرع البنك --}}
                <div class="col-md-2">
                    <label class="form-label">الفرع</label>
                    <input type="text" name="bank_branch" class="form-control @error('bank_branch') is-invalid @enderror"
                           value="{{ old('bank_branch') }}" placeholder="الفرع">
                    @error('bank_branch')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">رقم الحساب البنكي</label>
                    <input type="text" name="account_number" class="form-control @error('account_number') is-invalid @enderror"
                           value="{{ old('account_number') }}" placeholder="رقم الحساب">
                    @error('account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- الفرع --}}
                @if(count($branches) > 1 && !$branchLocked)
                <div class="col-md-6">
                    <label class="form-label">الفرع</label>
                    <select name="branch_id" class="form-select">
                        <option value="">— الفرع الافتراضي —</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected' : '' }}>
                                {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- ملاحظات --}}
                <div class="col-12">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" rows="2" class="form-control" placeholder="أي ملاحظات إضافية...">{{ old('notes') }}</textarea>
                </div>
            </div>

            {{-- معلومة القيد المحاسبي --}}
            <div class="alert alert-info mt-3 mb-0 py-2">
                <i class="bi bi-info-circle me-1"></i>
                <span id="glHint">
                    <strong>القيد:</strong> مدين: شيكات تحت التحصيل &nbsp;/&nbsp; دائن: ذمم العملاء
                </span>
            </div>

            <hr>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i> حفظ وترحيل
                </button>
                <a href="{{ route('checks.index') }}" class="btn btn-outline-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>

</div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    var glHints = {
        receivable: '<strong>القيد:</strong> مدين: شيكات تحت التحصيل &nbsp;/&nbsp; دائن: ذمم العملاء',
        payable:    '<strong>القيد:</strong> مدين: ذمم الموردين &nbsp;/&nbsp; دائن: شيكات مستحقة الدفع',
    };

    function toggleType() {
        var t = $('input[name="type"]:checked').val();
        if (t === 'receivable') {
            $('#customerSection').show();
            $('#supplierSection').hide();
        } else {
            $('#customerSection').hide();
            $('#supplierSection').show();
        }
        $('#glHint').html(glHints[t] || '');
    }

    $('input[name="type"]').on('change', toggleType);
    toggleType();
});
</script>
@endsection
