@extends('layouts.app')
@section('page-title', 'قيد يومي جديد')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" rel="stylesheet">
<style>.select2-container{width:100%!important} .emp-account-wrap{display:none;margin-top:4px}</style>

@php
    $optAccounts  = $accounts->map(fn($a)  => "<option value='{$a->id}'>" . e($a->code.' — '.$a->name) . "</option>")->implode('');
    $optCustomers = $customers->map(fn($c) => "<option value='customer:{$c->id}'>" . e($c->name) . "</option>")->implode('');
    $optSuppliers = $suppliers->map(fn($s) => "<option value='supplier:{$s->id}'>" . e($s->name) . "</option>")->implode('');
    $optEmployees = $employees->map(fn($e) => "<option value='employee:{$e->id}'>" . e($e->name) . "</option>")->implode('');
    $selectionInner = "<option value=''>— اختر الحساب أو الجهة —</option>"
        . "<optgroup label='الحسابات الرئيسية'>{$optAccounts}</optgroup>"
        . "<optgroup label='العملاء'>{$optCustomers}</optgroup>"
        . "<optgroup label='الموردون'>{$optSuppliers}</optgroup>"
        . "<optgroup label='الموظفون'>{$optEmployees}</optgroup>";
    $empAccountInner = "<option value=''>— حساب الموظف —</option>"
        . $employeeAccounts->map(fn($a) => "<option value='{$a->id}'>" . e($a->code.' — '.$a->name) . "</option>")->implode('');
@endphp
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-journal-plus"></i> إنشاء قيد يومي يدوي</h5>
                <a href="{{ route('journal_entries.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> العودة
                </a>
            </div>
            <div class="card-body">

                @if($errors->has('lines'))
                <div class="alert alert-danger py-2 small">
                    <i class="bi bi-exclamation-triangle"></i> {{ $errors->first('lines') }}
                </div>
                @endif

                <form action="{{ route('journal_entries.store') }}" method="POST" id="jeForm">
                    @csrf

                    {{-- Branch selector (SAP: Company Code is mandatory on every GL document) --}}
                    @if(!($branchLocked ?? false))
                    <div class="alert alert-light border mb-3 py-2">
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">
                                <label class="form-label mb-0 fw-semibold small">
                                    <i class="bi bi-building-fill-check text-primary me-1"></i>الفرع (Company Code)
                                </label>
                            </div>
                            <div class="col-md-4">
                                <select name="branch_id" class="form-select form-select-sm">
                                    <option value="">— فرع المستخدم الافتراضي —</option>
                                    @foreach($branches ?? [] as $b)
                                        <option value="{{ $b->id }}" {{ old('branch_id', $branchId ?? '') == $b->id ? 'selected':'' }}>
                                            [{{ $b->code }}] {{ $b->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">يحدد الفرع الذي سيظهر فيه هذا القيد في الدفتر والميزانية</small>
                            </div>
                        </div>
                    </div>
                    @else
                    <input type="hidden" name="branch_id" value="{{ auth()->user()?->branch_id }}">
                    @endif

                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                تاريخ القيد <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="entry_date"
                                   class="form-control @error('entry_date') is-invalid @enderror"
                                   value="{{ old('entry_date', date('Y-m-d')) }}" required>
                            @error('entry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">رقم المرجع</label>
                            <input type="text" name="reference"
                                   class="form-control @error('reference') is-invalid @enderror"
                                   placeholder="رقم الوثيقة / الفاتورة…"
                                   value="{{ old('reference') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                وصف القيد <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="description"
                                   class="form-control @error('description') is-invalid @enderror"
                                   placeholder="وصف واضح للقيد المحاسبي…"
                                   value="{{ old('description') }}" required>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Balance indicator --}}
                    <div class="alert py-2 small d-flex justify-content-between align-items-center" id="balanceAlert">
                        <span>
                            <strong>إجمالي المدين:</strong>
                            <span id="totalDebit" class="font-monospace fw-bold">0.00</span>
                            &nbsp;&nbsp;
                            <strong>إجمالي الدائن:</strong>
                            <span id="totalCredit" class="font-monospace fw-bold">0.00</span>
                        </span>
                        <span id="balanceStatus" class="badge bg-secondary">—</span>
                    </div>

                    {{-- Lines table --}}
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="linesTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:35%">الحساب <span class="text-danger">*</span></th>
                                    <th style="width:18%">مدين</th>
                                    <th style="width:18%">دائن</th>
                                    <th>بيان السطر</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="linesBody">
                                @php $oldLines = old('lines', [['account_id'=>'','debit'=>'','credit'=>'','line_description'=>''],['account_id'=>'','debit'=>'','credit'=>'','line_description'=>'']]) @endphp
                                @foreach($oldLines as $i => $line)
                                <tr class="line-row">
                                    <td>
                                        <select name="lines[{{ $i }}][selection]"
                                                class="form-select form-select-sm party-select"
                                                data-selected="{{ old("lines.{$i}.selection") }}" required>
                                            {!! $selectionInner !!}
                                        </select>
                                        <div class="emp-account-wrap">
                                            <select name="lines[{{ $i }}][employee_account_id]"
                                                    class="form-select form-select-sm emp-account-select"
                                                    data-selected="{{ old("lines.{$i}.employee_account_id") }}">
                                                {!! $empAccountInner !!}
                                            </select>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" name="lines[{{ $i }}][debit]"
                                               class="form-control form-control-sm amount-input debit-input"
                                               value="{{ old("lines.{$i}.debit", '0') }}"
                                               min="0" step="0.01" placeholder="0.00">
                                    </td>
                                    <td>
                                        <input type="number" name="lines[{{ $i }}][credit]"
                                               class="form-control form-control-sm amount-input credit-input"
                                               value="{{ old("lines.{$i}.credit", '0') }}"
                                               min="0" step="0.01" placeholder="0.00">
                                    </td>
                                    <td>
                                        <input type="text" name="lines[{{ $i }}][line_description]"
                                               class="form-control form-control-sm"
                                               value="{{ old("lines.{$i}.line_description") }}"
                                               placeholder="وصف اختياري…">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-line py-0"
                                                title="حذف السطر">
                                            <i class="bi bi-dash-circle"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <button type="button" id="addLine" class="btn btn-outline-secondary btn-sm mb-4">
                        <i class="bi bi-plus-circle"></i> إضافة سطر
                    </button>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-save"></i> حفظ القيد
                        </button>
                        <a href="{{ route('journal_entries.index') }}" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const selectionInner  = `{!! str_replace('`', '', $selectionInner) !!}`;
const empAccountInner = `{!! str_replace('`', '', $empAccountInner) !!}`;

// تهيئة مربّع البحث المجمّع + إظهار حساب الموظف عند اختيار موظف
function initPartySelect(row) {
    const sel = row.querySelector('.party-select');
    if (sel && window.jQuery && jQuery.fn.select2) {
        const $sel = jQuery(sel);
        if (!$sel.hasClass('select2-hidden-accessible')) {
            $sel.select2({ theme: 'bootstrap-5', dir: 'rtl', placeholder: '— اختر الحساب أو الجهة —' });
            if (sel.dataset.selected) { $sel.val(sel.dataset.selected).trigger('change'); }
            $sel.on('change', () => toggleEmpAccount(row));
        }
    }
    const emp = row.querySelector('.emp-account-select');
    if (emp && emp.dataset.selected) emp.value = emp.dataset.selected;
    toggleEmpAccount(row);
}

function toggleEmpAccount(row) {
    const sel  = row.querySelector('.party-select');
    const wrap = row.querySelector('.emp-account-wrap');
    if (!sel || !wrap) return;
    wrap.style.display = (sel.value || '').indexOf('employee:') === 0 ? 'block' : 'none';
}

function reindex() {
    document.querySelectorAll('#linesBody .line-row').forEach((row, i) => {
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/lines\[\d+\]/, `lines[${i}]`);
        });
    });
}

function recalc() {
    let dr = 0, cr = 0;
    document.querySelectorAll('.debit-input').forEach(i => dr += parseFloat(i.value) || 0);
    document.querySelectorAll('.credit-input').forEach(i => cr += parseFloat(i.value) || 0);
    document.getElementById('totalDebit').textContent  = dr.toFixed(2);
    document.getElementById('totalCredit').textContent = cr.toFixed(2);
    const diff = Math.abs(dr - cr);
    const alert = document.getElementById('balanceAlert');
    const badge = document.getElementById('balanceStatus');
    if (dr === 0 && cr === 0) {
        alert.className = 'alert alert-secondary py-2 small d-flex justify-content-between align-items-center';
        badge.className = 'badge bg-secondary'; badge.textContent = '—';
    } else if (diff < 0.005) {
        alert.className = 'alert alert-success py-2 small d-flex justify-content-between align-items-center';
        badge.className = 'badge bg-success'; badge.textContent = 'متوازن ✓';
    } else {
        alert.className = 'alert alert-danger py-2 small d-flex justify-content-between align-items-center';
        badge.className = 'badge bg-danger';
        badge.textContent = 'فرق: ' + diff.toFixed(2);
    }
}

document.getElementById('addLine').addEventListener('click', () => {
    const idx = document.querySelectorAll('#linesBody .line-row').length;
    const tr = document.createElement('tr');
    tr.className = 'line-row';
    tr.innerHTML = `
        <td>
            <select name="lines[${idx}][selection]" class="form-select form-select-sm party-select" data-selected="" required>${selectionInner}</select>
            <div class="emp-account-wrap"><select name="lines[${idx}][employee_account_id]" class="form-select form-select-sm emp-account-select" data-selected="">${empAccountInner}</select></div>
        </td>
        <td><input type="number" name="lines[${idx}][debit]"
                   class="form-control form-control-sm amount-input debit-input"
                   value="0" min="0" step="0.01" placeholder="0.00"></td>
        <td><input type="number" name="lines[${idx}][credit]"
                   class="form-control form-control-sm amount-input credit-input"
                   value="0" min="0" step="0.01" placeholder="0.00"></td>
        <td><input type="text" name="lines[${idx}][line_description]"
                   class="form-control form-control-sm" placeholder="وصف اختياري…"></td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm remove-line py-0">
                <i class="bi bi-dash-circle"></i>
            </button>
        </td>`;
    document.getElementById('linesBody').appendChild(tr);
    attachListeners(tr);
    recalc();
});

document.getElementById('linesBody').addEventListener('click', e => {
    const btn = e.target.closest('.remove-line');
    if (!btn) return;
    const rows = document.querySelectorAll('#linesBody .line-row');
    if (rows.length <= 2) { alert('يجب أن يحتوي القيد على سطرين على الأقل.'); return; }
    btn.closest('tr').remove();
    reindex();
    recalc();
});

function attachListeners(row) {
    row.querySelectorAll('.amount-input').forEach(i => i.addEventListener('input', recalc));
    initPartySelect(row);
}
document.querySelectorAll('#linesBody .line-row').forEach(attachListeners);

// Prevent submitting an unbalanced entry
document.getElementById('jeForm').addEventListener('submit', e => {
    let dr = 0, cr = 0;
    document.querySelectorAll('.debit-input').forEach(i => dr += parseFloat(i.value) || 0);
    document.querySelectorAll('.credit-input').forEach(i => cr += parseFloat(i.value) || 0);
    if (Math.abs(dr - cr) > 0.005 || dr === 0) {
        e.preventDefault();
        alert('القيد غير متوازن أو فارغ. يرجى مراجعة مبالغ المدين والدائن.');
    }
});

recalc();
</script>
@endsection
@endsection
