@extends('layouts.app')
@section('page-title', 'موازنة جديدة')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-calculator text-primary me-1"></i>إنشاء موازنة تقديرية</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('budgets.store') }}" method="POST">
        @csrf

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label fw-semibold">اسم الموازنة <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', 'موازنة ' . date('Y')) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">السنة <span class="text-danger">*</span></label>
                <input type="number" name="year" class="form-control" value="{{ old('year', date('Y')) }}" min="2020" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">الفرع</label>
                <select name="branch_id" class="form-select">
                    <option value="">— كل الفروع (موحد) —</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected':'' }}>
                            [{{ $b->code }}] {{ $b->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="alert alert-light border small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            أدخل المبلغ السنوي لكل حساب — سيُوزَّع تلقائياً بالتساوي على 12 شهراً.
            يمكن تعديل التوزيع الشهري لاحقاً.
        </div>

        <table class="table table-sm" id="budgetLines">
            <thead class="table-light">
                <tr>
                    <th style="width:50%">الحساب</th>
                    <th style="width:30%">المبلغ السنوي</th>
                    <th style="width:15%">شهرياً (تقريباً)</th>
                    <th style="width:5%"></th>
                </tr>
            </thead>
            <tbody id="linesBody">
                <tr data-row="0">
                    <td>
                        <select name="lines[0][account_id]" class="form-select form-select-sm" required>
                            <option value="">اختر الحساب</option>
                            @foreach($accounts->groupBy('type') as $type => $group)
                                <optgroup label="{{ $type === 'revenue' ? 'إيرادات' : 'مصروفات' }}">
                                    @foreach($group as $a)
                                        <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="number" name="lines[0][annual]" class="form-control form-control-sm annual-input"
                               value="0" step="0.01" min="0" oninput="calcMonthly(this)"></td>
                    <td><span class="monthly-preview text-muted small">0.00</span></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        <button type="button" class="btn btn-outline-secondary btn-sm mb-4" id="addLine">
            <i class="bi bi-plus-circle me-1"></i>إضافة حساب
        </button>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-save me-1"></i>حفظ الموازنة
            </button>
            <a href="{{ route('budgets.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
var rowCount = 0;

function calcMonthly(input) {
    var v = parseFloat(input.value) || 0;
    input.closest('tr').querySelector('.monthly-preview').textContent = (v / 12).toFixed(2);
}

var allOptions = `{!! collect($accounts)->map(fn($a) => "<option value='{$a->id}'>{$a->code} — {$a->name}</option>")->implode('') !!}`;

document.getElementById('addLine').addEventListener('click', function() {
    rowCount++;
    var tbody = document.getElementById('linesBody');
    tbody.insertAdjacentHTML('beforeend',
        '<tr><td><select name="lines[' + rowCount + '][account_id]" class="form-select form-select-sm" required>' +
        '<option value="">اختر الحساب</option>' + allOptions + '</select></td>' +
        '<td><input type="number" name="lines[' + rowCount + '][annual]" class="form-control form-control-sm annual-input" ' +
        'value="0" step="0.01" min="0" oninput="calcMonthly(this)"></td>' +
        '<td><span class="monthly-preview text-muted small">0.00</span></td>' +
        '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'tr\').remove()"><i class="bi bi-trash"></i></button></td>' +
        '</tr>');
});
</script>
@endsection
