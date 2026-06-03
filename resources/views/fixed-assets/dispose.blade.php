@extends('layouts.app')
@section('page-title', 'استبعاد أصل — ' . $fixedAsset->name)

@section('content')
<div class="row"><div class="col-lg-6 mx-auto">
    <div class="card border-danger">
        <div class="card-header bg-danger bg-opacity-10">
            <h5 class="mb-0 text-danger">
                <i class="bi bi-trash3 me-2"></i>استبعاد / بيع أصل ثابت — {{ $fixedAsset->asset_code }}
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning small mb-4">
                <strong>القيد المحاسبي للاستبعاد:</strong><br>
                مدين: مجمع الاستهلاك + الصندوق (إن بيع) &nbsp; دائن: حساب الأصل<br>
                الفرق = ربح أو خسارة بيع أصل ثابت
            </div>

            <div class="row text-center mb-4">
                <div class="col-4"><div class="text-muted small">تكلفة الشراء</div>
                    <strong>{{ number_format($fixedAsset->purchase_cost,2) }} {{ $currency }}</strong></div>
                <div class="col-4"><div class="text-muted small">الاستهلاك المتراكم</div>
                    <strong class="text-warning">{{ number_format($fixedAsset->accumulated_depreciation,2) }} {{ $currency }}</strong></div>
                <div class="col-4"><div class="text-muted small">القيمة الدفترية</div>
                    <strong class="text-primary">{{ number_format($fixedAsset->net_book_value,2) }} {{ $currency }}</strong></div>
            </div>

            <form action="{{ route('fixed-assets.dispose', $fixedAsset) }}" method="POST">
                @csrf
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">تاريخ الاستبعاد <span class="text-danger">*</span></label>
                        <input type="date" name="disposal_date" class="form-control"
                               value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">طريقة الاسترداد</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash">نقدي</option>
                            <option value="bank">بنكي</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">قيمة البيع / حصيلة الاستبعاد <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" name="disposal_amount" id="disposalAmt" class="form-control"
                               value="0" step="0.01" min="0" required>
                        <span class="input-group-text">{{ $currency }}</span>
                    </div>
                    <div class="form-text">أدخل 0 إذا كان إتلافاً بدون عائد</div>
                    <div id="gainLossPreview" class="mt-2"></div>
                </div>
                <div class="mb-4">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="2"
                              placeholder="سبب الاستبعاد، تفاصيل البيع…"></textarea>
                </div>
                <button type="submit" class="btn btn-danger"
                        onclick="return confirm('هل أنت متأكد من استبعاد هذا الأصل؟ لا يمكن التراجع.')">
                    <i class="bi bi-check2 me-1"></i> تأكيد الاستبعاد وترحيل القيد
                </button>
                <a href="{{ route('fixed-assets.show', $fixedAsset) }}" class="btn btn-secondary">إلغاء</a>
            </form>
        </div>
    </div>
</div></div>
@endsection

@section('scripts')
<script>
const nbv      = {{ (float) $fixedAsset->net_book_value }};
const currency = '{{ $currency }}';

$('#disposalAmt').on('input', function() {
    const sale = parseFloat($(this).val()) || 0;
    const diff = sale - nbv;
    const fmt  = Math.abs(diff).toLocaleString('ar-EG', {minimumFractionDigits:2});
    let html = '';
    if (Math.abs(diff) > 0.005) {
        if (diff > 0) {
            html = `<span class="badge bg-success">ربح بيع: ${fmt} ${currency}</span>`;
        } else {
            html = `<span class="badge bg-danger">خسارة بيع: ${fmt} ${currency}</span>`;
        }
    } else {
        html = '<span class="badge bg-secondary">لا ربح ولا خسارة</span>';
    }
    $('#gainLossPreview').html(html);
});
$('#disposalAmt').trigger('input');
</script>
@endsection
