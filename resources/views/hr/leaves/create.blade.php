@extends('layouts.app')
@section('page-title', 'طلب إجازة جديد')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-6">
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-calendar-plus text-success me-1"></i> طلب إجازة جديد</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('hr.leaves.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label class="form-label fw-semibold">الموظف <span class="text-danger">*</span></label>
            <select name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                <option value="">اختر موظفاً</option>
                @foreach($employees as $e)
                    <option value="{{ $e->id }}" {{ old('employee_id') == $e->id ? 'selected' : '' }}>
                        {{ $e->employee_code }} — {{ $e->name }}
                    </option>
                @endforeach
            </select>
            @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">نوع الإجازة <span class="text-danger">*</span></label>
            <select name="leave_type" class="form-select @error('leave_type') is-invalid @enderror" required>
                @foreach($types as $k => $v)
                    <option value="{{ $k }}" {{ old('leave_type') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
            @error('leave_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="row g-3 mb-3">
            <div class="col-6">
                <label class="form-label fw-semibold">من تاريخ <span class="text-danger">*</span></label>
                <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror"
                       value="{{ old('start_date') }}" required id="startDate">
                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6">
                <label class="form-label fw-semibold">إلى تاريخ <span class="text-danger">*</span></label>
                <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror"
                       value="{{ old('end_date') }}" required id="endDate">
                @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="alert alert-light border mb-3 small" id="daysCalc" style="display:none">
            <i class="bi bi-calculator me-1"></i>
            أيام العمل المحسوبة: <strong id="daysCount">—</strong>
            <span class="text-muted">(يستثني الجمعة)</span>
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold">ملاحظات</label>
            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success px-4"><i class="bi bi-send me-1"></i> تقديم الطلب</button>
            <a href="{{ route('hr.leaves.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection

@section('scripts')
<script>
function calcDays() {
    var s = document.getElementById('startDate').value;
    var e = document.getElementById('endDate').value;
    if (!s || !e || s > e) { document.getElementById('daysCalc').style.display='none'; return; }
    var start = new Date(s), end = new Date(e), days = 0;
    var cur = new Date(start);
    while (cur <= end) {
        if (cur.getDay() !== 5) days++;  // 5 = Friday
        cur.setDate(cur.getDate() + 1);
    }
    document.getElementById('daysCount').textContent = days;
    document.getElementById('daysCalc').style.display = '';
}
document.getElementById('startDate').addEventListener('change', calcDays);
document.getElementById('endDate').addEventListener('change', calcDays);
</script>
@endsection
