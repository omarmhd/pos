@extends('layouts.app')
@section('title', 'سجل الحضور اليومي')
@section('page-title', 'سجل الحضور اليومي')

@section('content')
<div class="container-fluid">

    {{-- Date picker --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">التاريخ</label>
                    <input type="date" name="date" class="form-control" value="{{ $date->toDateString() }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-calendar-check"></i> عرض
                    </button>
                    <a href="{{ route('hr.attendance.monthly') }}" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-calendar-month"></i> شهري
                    </a>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('hr.attendance.save-daily') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-people"></i> حضور {{ $date->format('Y/m/d') }} — {{ $employees->count() }} موظف</span>
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-save"></i> حفظ السجل
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle small">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3" style="width:200px">الموظف</th>
                            <th style="width:130px">الوردية</th>
                            <th style="width:110px">وقت الدخول</th>
                            <th style="width:110px">وقت الخروج</th>
                            <th style="width:140px">الحالة</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($employees as $emp)
                        @php $rec = $existing[$emp->id] ?? null; @endphp
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $emp->name }}</td>
                            <td>
                                <select name="records[{{ $emp->id }}][shift_id]" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    @foreach($shifts as $shift)
                                    <option value="{{ $shift->id }}"
                                            data-hours="{{ $shift->hours }}"
                                            {{ ($rec?->shift_id == $shift->id) ? 'selected' : '' }}>
                                        {{ $shift->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="records[{{ $emp->id }}][shift_hours]" value="8" class="shift-hours-input">
                            </td>
                            <td>
                                <input type="time" name="records[{{ $emp->id }}][check_in]"
                                       class="form-control form-control-sm"
                                       value="{{ $rec?->check_in ?? '' }}">
                            </td>
                            <td>
                                <input type="time" name="records[{{ $emp->id }}][check_out]"
                                       class="form-control form-control-sm"
                                       value="{{ $rec?->check_out ?? '' }}">
                            </td>
                            <td>
                                <select name="records[{{ $emp->id }}][status]" class="form-select form-select-sm">
                                    @foreach(['present'=>'حاضر','absent'=>'غائب','half_day'=>'نصف يوم','holiday'=>'إجازة رسمية','leave'=>'إجازة'] as $v=>$l)
                                    <option value="{{ $v }}" {{ ($rec?->status ?? 'present') == $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" name="records[{{ $emp->id }}][notes]"
                                       class="form-control form-control-sm"
                                       value="{{ $rec?->notes ?? '' }}"
                                       placeholder="ملاحظة...">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-save"></i> حفظ سجل الحضور
                </button>
            </div>
        </div>
    </form>

</div>
@endsection

@section('scripts')
<script>
    // Sync shift hours to hidden input when shift changes
    document.querySelectorAll('select[name^="records["]').forEach(function(sel) {
        if (!sel.name.includes('[shift_id]')) return;
        sel.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const hours = opt.dataset.hours || 8;
            const row = this.closest('tr');
            const hoursInput = row.querySelector('.shift-hours-input');
            if (hoursInput) hoursInput.value = hours;
        });
    });
</script>
@endsection
