@extends('layouts.app')
@section('page-title', 'معالج الأرصدة الافتتاحية')

@section('content')
<div class="row g-4">

    {{-- Diagnosis panel --}}
    <div class="col-md-4">
        <div class="card border-{{ $bs['isBalanced'] ? 'success' : 'danger' }}">
            <div class="card-header bg-{{ $bs['isBalanced'] ? 'success' : 'danger' }} bg-opacity-10">
                <strong>
                    <i class="bi bi-{{ $bs['isBalanced'] ? 'check-circle text-success' : 'exclamation-triangle text-danger' }} me-1"></i>
                    حالة الميزانية العمومية
                </strong>
            </div>
            <div class="card-body small">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">إجمالي الأصول</td>
                        <td class="text-end font-monospace">{{ number_format($bs['totalAssets'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">إجمالي الالتزامات</td>
                        <td class="text-end font-monospace">{{ number_format($bs['totalLiabilities'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">حقوق الملكية</td>
                        <td class="text-end font-monospace">{{ number_format($bs['totalEquityAccounts'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">صافي الربح (م.ج.)</td>
                        <td class="text-end font-monospace">{{ number_format($ni, 2) }}</td>
                    </tr>
                    <tr class="border-top fw-bold {{ $bs['isBalanced'] ? 'text-success' : 'text-danger' }}">
                        <td>الفرق</td>
                        <td class="text-end font-monospace">{{ number_format($bs['difference'], 2) }} {{ $currency }}</td>
                    </tr>
                </table>
                @if(!$bs['isBalanced'])
                <div class="alert alert-warning mt-2 small mb-0">
                    <i class="bi bi-lightbulb me-1"></i>
                    الفرق <strong>{{ number_format(abs($bs['difference']), 2) }} {{ $currency }}</strong>
                    يمثل رأس المال الافتتاحي الذي لم يُسجَّل بعد.
                    أدخله كـ<strong> دائن — حساب رأس المال (3000)</strong>.
                </div>
                @endif
            </div>
        </div>

        <div class="card mt-3 border-info">
            <div class="card-header bg-info bg-opacity-10">
                <strong><i class="bi bi-journal-text me-1 text-info"></i>القيد النموذجي</strong>
            </div>
            <div class="card-body small">
                <table class="w-100">
                    <tr>
                        <td class="text-danger fw-bold">مدين:</td>
                        <td>الصندوق (1000)</td>
                        <td class="text-end">{{ number_format(abs($bs['difference']), 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-success fw-bold">دائن:</td>
                        <td>رأس المال (3000)</td>
                        <td class="text-end">{{ number_format(abs($bs['difference']), 2) }}</td>
                    </tr>
                </table>
                <div class="text-muted mt-2">
                    أو أي حسابات أخرى تعكس الوضع الحقيقي للمنشأة
                </div>
            </div>
        </div>
    </div>

    {{-- Entry form --}}
    <div class="col-md-8">
        @if($hasOpening)
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            يوجد قيد افتتاحي مسجَّل مسبقاً (OB-{{ date('Y') }}).
            يمكنك إنشاء قيد تصحيحي إضافي إذا لزم الأمر.
        </div>
        @endif

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-journal-plus me-1 text-primary"></i>قيد الأرصدة الافتتاحية</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('accounting.opening-balance.store') }}" method="POST" id="obForm">
                @csrf

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">تاريخ القيد</label>
                        <input type="date" name="entry_date" class="form-control"
                               value="{{ old('entry_date', date('Y-m-d')) }}" required>
                    </div>
                </div>

                <table class="table table-sm" id="obLines">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40%">الحساب</th>
                            <th class="text-end" style="width:22%">مدين</th>
                            <th class="text-end" style="width:22%">دائن</th>
                            <th style="width:12%">وصف</th>
                            <th style="width:4%"></th>
                        </tr>
                    </thead>
                    <tbody id="obBody">
                        {{-- Pre-fill with cash + capital if difference exists --}}
                        @php
                            $prefillAmt = abs($bs['difference']);
                        @endphp
                        <tr data-row="0">
                            <td>
                                <select name="lines[0][account_id]" class="form-select form-select-sm" required>
                                    <option value="">اختر الحساب</option>
                                    @foreach($assetAccounts as $a)
                                        <option value="{{ $a->id }}"
                                            {{ $a->id == $cashAccount?->id ? 'selected' : '' }}>
                                            {{ $a->code }} — {{ $a->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" name="lines[0][debit]" class="form-control form-control-sm ob-debit"
                                       value="{{ old('lines.0.debit', number_format($prefillAmt, 2, '.', '')) }}"
                                       step="0.01" min="0"></td>
                            <td><input type="number" name="lines[0][credit]" class="form-control form-control-sm ob-credit"
                                       value="{{ old('lines.0.credit', 0) }}"
                                       step="0.01" min="0"></td>
                            <td><input type="text" name="lines[0][line_description]" class="form-control form-control-sm"
                                       value="{{ old('lines.0.line_description', 'أرصدة افتتاحية') }}"></td>
                            <td></td>
                        </tr>
                        <tr data-row="1">
                            <td>
                                <select name="lines[1][account_id]" class="form-select form-select-sm" required>
                                    <option value="">اختر الحساب</option>
                                    @foreach($equityAccounts as $a)
                                        <option value="{{ $a->id }}"
                                            {{ $a->id == $capitalAccount?->id ? 'selected' : '' }}>
                                            {{ $a->code }} — {{ $a->name }}
                                        </option>
                                    @endforeach
                                    @foreach($assetAccounts as $a)
                                        <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" name="lines[1][debit]" class="form-control form-control-sm ob-debit"
                                       value="{{ old('lines.1.debit', 0) }}"
                                       step="0.01" min="0"></td>
                            <td><input type="number" name="lines[1][credit]" class="form-control form-control-sm ob-credit"
                                       value="{{ old('lines.1.credit', number_format($prefillAmt, 2, '.', '')) }}"
                                       step="0.01" min="0"></td>
                            <td><input type="text" name="lines[1][line_description]" class="form-control form-control-sm"
                                       value="{{ old('lines.1.line_description', 'رأس المال الافتتاحي') }}"></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="btnAddLine">
                    <i class="bi bi-plus-circle me-1"></i> إضافة سطر
                </button>

                {{-- Balance display --}}
                <div class="alert alert-light border" id="balanceStatus">
                    <div class="d-flex justify-content-between">
                        <span>إجمالي المدين: <strong id="totalDebit" class="font-monospace">0.00</strong></span>
                        <span>إجمالي الدائن: <strong id="totalCredit" class="font-monospace">0.00</strong></span>
                        <span id="balanceLabel" class="fw-bold">—</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-send-check me-1"></i> ترحيل القيد الافتتاحي
                </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
var rowCount = 1;

function recalc() {
    var d = 0, c = 0;
    document.querySelectorAll('.ob-debit').forEach(function(el) { d += parseFloat(el.value)||0; });
    document.querySelectorAll('.ob-credit').forEach(function(el) { c += parseFloat(el.value)||0; });
    document.getElementById('totalDebit').textContent = d.toFixed(2);
    document.getElementById('totalCredit').textContent = c.toFixed(2);
    var diff = Math.abs(d - c);
    var lbl = document.getElementById('balanceLabel');
    if (diff < 0.01) {
        lbl.textContent = '✓ متوازن'; lbl.className = 'fw-bold text-success';
    } else {
        lbl.textContent = 'فرق: ' + diff.toFixed(2); lbl.className = 'fw-bold text-danger';
    }
}

document.getElementById('obForm').addEventListener('input', recalc);
recalc();

document.getElementById('btnAddLine').addEventListener('click', function() {
    rowCount++;
    var tbody = document.getElementById('obBody');
    var allAccounts = `{!! collect($assetAccounts->concat($equityAccounts))->map(fn($a) => "<option value='{$a->id}'>{$a->code} — {$a->name}</option>")->implode('') !!}`;
    tbody.insertAdjacentHTML('beforeend',
        '<tr><td><select name="lines[' + rowCount + '][account_id]" class="form-select form-select-sm" required>' +
        '<option value="">اختر الحساب</option>' + allAccounts + '</select></td>' +
        '<td><input type="number" name="lines[' + rowCount + '][debit]"  class="form-control form-control-sm ob-debit"  value="0" step="0.01" min="0"></td>' +
        '<td><input type="number" name="lines[' + rowCount + '][credit]" class="form-control form-control-sm ob-credit" value="0" step="0.01" min="0"></td>' +
        '<td><input type="text"   name="lines[' + rowCount + '][line_description]" class="form-control form-control-sm" value="أرصدة افتتاحية"></td>' +
        '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'tr\').remove();recalc()"><i class="bi bi-trash"></i></button></td>' +
        '</tr>');
});
</script>
@endsection
