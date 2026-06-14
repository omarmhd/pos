@extends('layouts.app')
@section('page-title', 'السندات المتعددة')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-stack text-primary me-2"></i>السندات المتعددة — إدخال دفعي</h5>
    </div>
    <div class="card-body">

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="alert alert-info py-2 small">
            <i class="bi bi-info-circle"></i>
            لإدخال أعداد كبيرة من سندات القبض أو الصرف دفعة واحدة — كل سطر يُنشئ سنداً مستقلاً بقيده المحاسبي.
            تُحفظ جميع الأسطر معاً أو لا يُحفظ شيء (معاملة واحدة).
        </div>

        <form action="{{ route('vouchers.bulk.store') }}" method="POST">
        @csrf

        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <label class="form-label fw-semibold">النوع <span class="text-danger">*</span></label>
                <select name="voucher_type" class="form-select" required>
                    <option value="receipt" {{ old('voucher_type', 'receipt') == 'receipt' ? 'selected':'' }}>سندات قبض</option>
                    <option value="payment" {{ old('voucher_type') == 'payment' ? 'selected':'' }}>سندات صرف</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">التاريخ <span class="text-danger">*</span></label>
                <input type="date" name="voucher_date" class="form-control"
                       value="{{ old('voucher_date', today()->format('Y-m-d')) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">الطريقة <span class="text-danger">*</span></label>
                <select name="payment_method" class="form-select" required>
                    <option value="cash">نقدي</option>
                    <option value="bank">تحويل بنكي</option>
                    <option value="mobile_wallet">محفظة إلكترونية</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">حساب النقدية / البنك <span class="text-danger">*</span></label>
                <select name="cash_account_id" class="form-select" required>
                    @foreach($accounts->where('type', 'asset') as $acc)
                        <option value="{{ $acc->id }}"
                            {{ old('cash_account_id', $defaultCashAccount?->id) == $acc->id ? 'selected':'' }}>
                            {{ $acc->code }} — {{ $acc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if(!($branchLocked ?? false))
            <div class="col-md-3">
                <label class="form-label fw-semibold">الفرع</label>
                <select name="branch_id" class="form-select">
                    <option value="">— الافتراضي —</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}">[{{ $b->code }}] {{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle" id="bulkTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:4%">#</th>
                        <th style="width:22%">الاسم (المستلم منه / المصروف له) *</th>
                        <th style="width:28%">الحساب المقابل *</th>
                        <th style="width:13%">المبلغ ({{ $currency }}) *</th>
                        <th style="width:14%">المرجع</th>
                        <th style="width:15%">ملاحظات</th>
                        <th style="width:4%"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addBulkRow()">
            <i class="bi bi-plus"></i> إضافة سطر
        </button>

        <div class="d-flex justify-content-between align-items-center">
            <div class="fw-bold">الإجمالي: <span id="bulkTotal">0.00</span> {{ $currency }} — <span id="bulkCount">0</span> سند</div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success px-4"
                        onclick="return confirm('سيتم إنشاء وترحيل جميع السندات المكتملة. متابعة؟')">
                    <i class="bi bi-save me-1"></i> حفظ وترحيل الكل
                </button>
                <a href="{{ route('vouchers.receipts.index') }}" class="btn btn-secondary">إلغاء</a>
            </div>
        </div>

        </form>
    </div>
</div>

<div id="__bulkAccounts" style="display:none">{{ json_encode($accounts->map(fn($a) => [
    'id' => $a->id, 'label' => $a->code . ' — ' . $a->name,
])) }}</div>
@endsection

@section('scripts')
<script>
const BULK_ACCOUNTS = JSON.parse(document.getElementById('__bulkAccounts').textContent);
const accountOptions = '<option value="">اختر الحساب</option>' +
    BULK_ACCOUNTS.map(a => `<option value="${a.id}">${a.label}</option>`).join('');

let bulkIdx = 0;

function addBulkRow() {
    const i = bulkIdx++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="text-center text-muted row-num"></td>
        <td><input type="text" name="rows[${i}][party]" class="form-control form-control-sm"></td>
        <td><select name="rows[${i}][account_id]" class="form-select form-select-sm">${accountOptions}</select></td>
        <td><input type="number" name="rows[${i}][amount]" class="form-control form-control-sm bulk-amount"
                   step="0.01" min="0.01" oninput="recalcBulk()"></td>
        <td><input type="text" name="rows[${i}][reference]" class="form-control form-control-sm"></td>
        <td><input type="text" name="rows[${i}][notes]" class="form-control form-control-sm"></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="this.closest('tr').remove(); recalcBulk();"><i class="bi bi-x"></i></button>
        </td>`;
    document.querySelector('#bulkTable tbody').appendChild(tr);
    recalcBulk();
}

function recalcBulk() {
    let total = 0, count = 0;
    document.querySelectorAll('#bulkTable tbody tr').forEach((tr, idx) => {
        tr.querySelector('.row-num').textContent = idx + 1;
        const v = parseFloat(tr.querySelector('.bulk-amount').value) || 0;
        if (v > 0) { total += v; count++; }
    });
    document.getElementById('bulkTotal').textContent = total.toFixed(2);
    document.getElementById('bulkCount').textContent = count;
}

document.addEventListener('DOMContentLoaded', () => {
    for (let i = 0; i < 5; i++) addBulkRow();   // 5 أسطر جاهزة
});
</script>
@endsection
