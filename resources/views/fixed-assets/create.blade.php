@extends('layouts.app')
@section('page-title', 'تسجيل أصل ثابت جديد')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-building-add text-primary me-2"></i>تسجيل أصل ثابت جديد</h5>
    </div>
    <div class="card-body">

        <div class="alert alert-light border-start border-primary border-3 mb-4 small">
            <strong>القيد عند الحفظ (نقدي/بنكي):</strong>
            مدين: حساب الأصل الثابت &nbsp; دائن: الصندوق/البنك
        </div>

        <form action="{{ route('fixed-assets.store') }}" method="POST" id="fa-form">
            @csrf

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">اسم الأصل <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="مثال: ثلاجة عرض، كاشير، سيارة توصيل" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">الفئة <span class="text-danger">*</span></label>
                    <select name="category_id" id="categorySelect"
                            class="form-select @error('category_id') is-invalid @enderror" required>
                        <option value="">اختر الفئة</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}"
                                data-life="{{ $cat->useful_life_months }}"
                                data-method="{{ $cat->depreciation_method }}"
                                {{ old('category_id') == $cat->id ? 'selected':'' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">الفرع</label>
                    <select name="branch_id" class="form-select">
                        <option value="">— الافتراضي —</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ old('branch_id', auth()->user()->branch_id) == $b->id ? 'selected':'' }}>
                                {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">تاريخ الشراء <span class="text-danger">*</span></label>
                    <input type="date" name="purchase_date" class="form-control"
                           value="{{ old('purchase_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">تكلفة الشراء <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" name="purchase_cost" id="purchaseCost" class="form-control"
                               value="{{ old('purchase_cost') }}" step="0.01" min="0.01" required>
                        <span class="input-group-text">{{ $currency }}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">القيمة التخريدية</label>
                    <div class="input-group">
                        <input type="number" name="residual_value" class="form-control"
                               value="{{ old('residual_value', 0) }}" step="0.01" min="0">
                        <span class="input-group-text">{{ $currency }}</span>
                    </div>
                    <div class="form-text">القيمة المتبقية بعد نهاية العمر الإنتاجي</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">طريقة الدفع <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <option value="cash">نقدي (يُرحَّل قيد فوراً)</option>
                        <option value="bank">بنكي (يُرحَّل قيد فوراً)</option>
                        <option value="credit">آجل/ائتمان (لا يُرحَّل قيد الآن)</option>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">طريقة الاستهلاك <span class="text-danger">*</span></label>
                    <select name="depreciation_method" id="depMethod" class="form-select" required>
                        <option value="straight_line" {{ old('depreciation_method','straight_line') === 'straight_line' ? 'selected':'' }}>
                            القسط الثابت (Straight Line)
                        </option>
                        <option value="declining_balance" {{ old('depreciation_method') === 'declining_balance' ? 'selected':'' }}>
                            القسط المتناقص (Declining Balance)
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">العمر الإنتاجي (شهر) <span class="text-danger">*</span></label>
                    <input type="number" name="useful_life_months" id="usefulLife" class="form-control"
                           value="{{ old('useful_life_months', 60) }}" min="1" required>
                    <div class="form-text" id="lifeYears"></div>
                </div>
                <div class="col-md-3" id="rateWrap" style="display:none">
                    <label class="form-label">معدل الاستهلاك السنوي</label>
                    <div class="input-group">
                        <input type="number" name="depreciation_rate" class="form-control"
                               value="{{ old('depreciation_rate') }}" step="0.0001" min="0.001" max="1"
                               placeholder="مثال: 0.20 = 20%">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">اتركه فارغاً لحساب تلقائي (1 ÷ سنوات العمر)</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">اسم المورد/الجهة البائعة</label>
                    <input type="text" name="supplier_name" class="form-control"
                           value="{{ old('supplier_name') }}" placeholder="اختياري">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>

            {{-- Live monthly depreciation preview --}}
            <div class="alert alert-info d-none" id="deprPreview">
                القسط الشهري المتوقع: <strong id="monthlyDepr">—</strong> {{ $currency }}
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> حفظ الأصل
            </button>
            <a href="{{ route('fixed-assets.index') }}" class="btn btn-secondary">إلغاء</a>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
function updatePreview() {
    const cost     = parseFloat($('#purchaseCost').val()) || 0;
    const life     = parseInt($('#usefulLife').val()) || 1;
    const method   = $('#depMethod').val();
    const years    = (life / 12).toFixed(1);
    $('#lifeYears').text(years + ' سنة');

    if (cost > 0 && life > 0) {
        let monthly = 0;
        if (method === 'straight_line') {
            monthly = (cost / life).toFixed(2);
        } else {
            const rate = (1 / (life / 12)) / 12;
            monthly = (cost * rate).toFixed(2);
        }
        $('#monthlyDepr').text(parseFloat(monthly).toLocaleString('ar-EG', {minimumFractionDigits:2}));
        $('#deprPreview').removeClass('d-none');
    }
}

$('#depMethod').on('change', function() {
    $('#rateWrap').toggle($(this).val() === 'declining_balance');
    updatePreview();
});

$('#purchaseCost, #usefulLife').on('input', updatePreview);

// Auto-fill from category
$('#categorySelect').on('change', function() {
    const opt = $(this).find('option:selected');
    if (opt.val()) {
        $('#usefulLife').val(opt.data('life'));
        $('#depMethod').val(opt.data('method')).trigger('change');
    }
    updatePreview();
});

updatePreview();
</script>
@endsection
