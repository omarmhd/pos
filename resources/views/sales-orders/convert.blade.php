@extends('layouts.app')
@section('page-title', 'تحويل لفاتورة بيع')

@section('content')
<div class="row"><div class="col-lg-10 mx-auto">
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="bi bi-arrow-right-circle text-success me-2"></i>
                تحويل أمر البيع {{ $salesOrder->order_number }} إلى فاتورة
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-light border-start border-success border-3 mb-4 small">
                <strong>القيد عند الحفظ:</strong>
                مدين: صندوق/ذمم عملاء &nbsp; دائن: إيرادات المبيعات &nbsp;+&nbsp; قيد تكلفة البضاعة
            </div>

            <form action="{{ route('sales-orders.convert', $salesOrder) }}" method="POST">
                @csrf
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">نوع البيع</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_credit" id="isCreditConv"
                                   value="1" {{ $salesOrder->is_credit ? 'checked':'' }}>
                            <label class="form-check-label" for="isCreditConv">آجل (على الحساب)</label>
                        </div>
                    </div>
                    <div class="col-md-4" id="payMethodWrap">
                        <label class="form-label">طريقة الدفع</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash">نقدي</option>
                            <option value="card">بطاقة</option>
                            <option value="mobile_wallet">محفظة إلكترونية</option>
                        </select>
                    </div>
                </div>

                {{-- Items to deliver --}}
                <h6 class="text-muted mb-2">الأصناف المُسلَّمة الآن</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                        <tr><th>الصنف</th>
                            <th class="text-center">مطلوب</th>
                            <th class="text-center">مُسلَّم سابقاً</th>
                            <th class="text-center" style="min-width:120px">الكمية الآن</th>
                            <th class="text-end">سعر الوحدة</th>
                            <th class="text-end">المجموع</th></tr>
                        </thead>
                        <tbody>
                        @php $grandTotal = 0; @endphp
                        @foreach($salesOrder->items as $item)
                        @php $remaining = $item->remainingQuantity(); @endphp
                        @if($remaining > 0)
                        <tr>
                            <td>
                                {{ $item->product?->name }}
                                <input type="hidden" name="items[{{ $loop->index }}][so_item_id]" value="{{ $item->id }}">
                            </td>
                            <td class="text-center text-muted">{{ $item->quantity_ordered + 0 }}</td>
                            <td class="text-center text-success">{{ $item->quantity_delivered + 0 }}</td>
                            <td>
                                <input type="number"
                                       name="items[{{ $loop->index }}][quantity]"
                                       class="form-control form-control-sm text-center so-conv-qty"
                                       value="{{ $remaining + 0 }}"
                                       min="0.001" max="{{ $remaining }}" step="0.001"
                                       data-price="{{ $item->unit_price }}"
                                       oninput="calcSOConv(this)">
                            </td>
                            <td class="text-end">{{ number_format($item->unit_price,2) }} {{ $currency }}</td>
                            <td class="text-end fw-bold so-conv-sub">
                                {{ number_format($remaining * $item->unit_price, 2) }} {{ $currency }}
                            </td>
                        </tr>
                        @php $grandTotal += $remaining * $item->unit_price; @endphp
                        @endif
                        @endforeach
                        </tbody>
                        <tfoot class="table-success">
                        <tr>
                            <td colspan="5" class="text-end fw-bold">الإجمالي:</td>
                            <td class="text-end fw-bold" id="so-conv-total">
                                {{ number_format($grandTotal,2) }} {{ $currency }}
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2-circle me-1"></i> إنشاء الفاتورة وترحيل القيد
                </button>
                <a href="{{ route('sales-orders.show', $salesOrder) }}" class="btn btn-secondary">إلغاء</a>
            </form>
        </div>
    </div>
</div></div>
@endsection

@section('scripts')
<script>
const CURRENCY = "{{ $currency }}";

document.getElementById('isCreditConv').addEventListener('change', function() {
    document.getElementById('payMethodWrap').style.display = this.checked ? 'none' : '';
});

function calcSOConv(inp) {
    const row   = inp.closest('tr');
    const qty   = parseFloat(inp.value) || 0;
    const price = parseFloat(inp.dataset.price) || 0;
    row.querySelector('.so-conv-sub').textContent = (qty * price).toFixed(2) + ' ' + CURRENCY;
    let t = 0;
    document.querySelectorAll('.so-conv-qty').forEach(i => {
        t += (parseFloat(i.value)||0) * (parseFloat(i.dataset.price)||0);
    });
    document.getElementById('so-conv-total').textContent = t.toFixed(2) + ' ' + CURRENCY;
}
</script>
@endsection
