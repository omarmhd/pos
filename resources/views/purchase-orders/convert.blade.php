@extends('layouts.app')
@section('page-title', 'تحويل إلى فاتورة شراء')

@section('content')
<div class="row"><div class="col-lg-10 mx-auto">
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="bi bi-arrow-right-circle text-success me-2"></i>
                تحويل أمر الشراء إلى فاتورة — {{ $purchaseOrder->po_number }}
            </h5>
        </div>
        <div class="card-body">

            <div class="alert alert-light border-start border-success border-3 mb-4 small">
                <strong>القيد عند الحفظ:</strong>
                مدين: مخزون (1300) &nbsp; دائن: ذمم موردين (2000) أو صندوق
            </div>

            <form action="{{ route('purchase-orders.convert', $purchaseOrder) }}" method="POST">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">حالة الدفع <span class="text-danger">*</span></label>
                        <select name="payment_status" id="paymentStatus" class="form-select" required>
                            <option value="unpaid">غير مدفوع</option>
                            <option value="partial">مدفوع جزئياً</option>
                            <option value="paid">مدفوع بالكامل</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">المبلغ المدفوع</label>
                        <input type="number" name="paid_amount" id="paidAmount"
                               class="form-control" value="0" step="0.01" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">رقم فاتورة المورد</label>
                        <input type="text" name="supplier_invoice_number" class="form-control"
                               placeholder="رقم الفاتورة من المورد (اختياري)">
                    </div>
                </div>

                {{-- Items to receive --}}
                <h6 class="text-muted mb-2">الأصناف المُستلَمة</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                        <tr>
                            <th>الصنف</th>
                            <th class="text-center">مطلوب</th>
                            <th class="text-center">مُستلَم سابقاً</th>
                            <th class="text-center" style="min-width:120px">الكمية الآن</th>
                            <th class="text-end" style="min-width:130px">سعر الوحدة</th>
                            <th class="text-end">المجموع</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php $grandTotal = 0; @endphp
                        @foreach($purchaseOrder->items as $item)
                        @php
                            $remaining = $item->remainingQuantity();
                        @endphp
                        @if($remaining > 0)
                        <tr>
                            <td>
                                {{ $item->product?->name }}
                                <input type="hidden" name="items[{{ $loop->index }}][po_item_id]" value="{{ $item->id }}">
                            </td>
                            <td class="text-center text-muted">{{ $item->quantity_ordered + 0 }}</td>
                            <td class="text-center text-success">{{ $item->quantity_received + 0 }}</td>
                            <td>
                                <input type="number"
                                       name="items[{{ $loop->index }}][quantity]"
                                       class="form-control form-control-sm receive-qty text-center"
                                       value="{{ $remaining + 0 }}"
                                       min="0.001" max="{{ $remaining }}"
                                       step="0.001"
                                       data-price="{{ $item->unit_price }}"
                                       oninput="calcConvert(this)">
                            </td>
                            <td>
                                <input type="number"
                                       name="items[{{ $loop->index }}][unit_price]"
                                       class="form-control form-control-sm receive-price text-end"
                                       value="{{ $item->unit_price }}"
                                       min="0" step="0.01"
                                       oninput="calcConvert(this.closest('tr').querySelector('.receive-qty'))">
                            </td>
                            <td class="text-end fw-bold receive-subtotal">
                                {{ number_format($remaining * $item->unit_price, 2) }} {{ $currency }}
                            </td>
                        </tr>
                        @php $grandTotal += $remaining * $item->unit_price; @endphp
                        @endif
                        @endforeach
                        </tbody>
                        <tfoot class="table-warning">
                        <tr>
                            <td colspan="5" class="text-end fw-bold">الإجمالي:</td>
                            <td class="text-end fw-bold" id="convertTotal">
                                {{ number_format($grandTotal, 2) }} {{ $currency }}
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mb-4">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2-circle me-1"></i> إنشاء فاتورة الشراء وترحيل القيد
                </button>
                <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-secondary">إلغاء</a>
            </form>
        </div>
    </div>
</div></div>
@endsection

@section('scripts')
<script>
const CURRENCY = "{{ $currency }}";

function calcConvert(qtyInput) {
    const row   = qtyInput.closest('tr');
    const qty   = parseFloat(qtyInput.value) || 0;
    const price = parseFloat(row.querySelector('.receive-price').value) || 0;
    const sub   = qty * price;
    row.querySelector('.receive-subtotal').textContent = sub.toFixed(2) + ' ' + CURRENCY;

    let total = 0;
    document.querySelectorAll('.receive-qty').forEach(inp => {
        const r = inp.closest('tr');
        const q = parseFloat(inp.value) || 0;
        const p = parseFloat(r.querySelector('.receive-price').value) || 0;
        total += q * p;
    });
    document.getElementById('convertTotal').textContent = total.toFixed(2) + ' ' + CURRENCY;

    if (document.getElementById('paymentStatus').value === 'paid') {
        document.getElementById('paidAmount').value = total.toFixed(2);
    }
}

document.getElementById('paymentStatus').addEventListener('change', function() {
    const total = parseFloat(document.getElementById('convertTotal').textContent) || 0;
    if (this.value === 'paid') {
        document.getElementById('paidAmount').value = total.toFixed(2);
    } else if (this.value === 'unpaid') {
        document.getElementById('paidAmount').value = '0';
    }
});
</script>
@endsection
