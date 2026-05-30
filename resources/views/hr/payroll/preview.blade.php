@extends('layouts.app')
@section('title', 'معاينة مسير الراتب')
@section('page-title', 'معاينة وتشغيل مسير الراتب')

@section('content')
<div class="container-fluid">

    {{-- Period selector --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">الشهر</label>
                    <select name="month" class="form-select">
                        @foreach(range(1,12) as $m)
                        <option value="{{ $m }}" {{ $month==$m ? 'selected' : '' }}>
                            {{ ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'][$m-1] }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">السنة</label>
                    <input type="number" name="year" class="form-control" value="{{ $year }}" min="2020">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-calculator"></i> احتساب
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if(!empty($items))
    {{-- Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center bg-light">
                <div class="card-body">
                    <div class="fs-4 fw-bold text-primary">{{ number_format($totalGross, 2) }}</div>
                    <div class="small text-muted">إجمالي الرواتب</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center bg-light">
                <div class="card-body">
                    <div class="fs-4 fw-bold text-danger">{{ number_format($totalDeductions, 2) }}</div>
                    <div class="small text-muted">إجمالي الخصومات</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center bg-success bg-opacity-10">
                <div class="card-body">
                    <div class="fs-4 fw-bold text-success">{{ number_format($totalNet, 2) }}</div>
                    <div class="small text-muted">الصافي للصرف</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail table --}}
    <div class="card mb-4">
        <div class="card-header fw-bold">تفاصيل الرواتب</div>
        <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">الموظف</th>
                        <th class="text-center">أيام حضور</th>
                        <th class="text-center">أيام غياب</th>
                        <th class="text-end">أساسي</th>
                        <th class="text-end">بدلات</th>
                        <th class="text-end">أوفرتايم</th>
                        <th class="text-end text-danger">خصومات</th>
                        <th class="text-end">إجمالي</th>
                        <th class="text-end fw-bold">صافي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr>
                        <td class="ps-3 fw-semibold">{{ $item['employee']->name }}</td>
                        <td class="text-center">{{ $item['days_worked'] }}</td>
                        <td class="text-center {{ $item['days_absent']>0 ? 'text-danger' : '' }}">{{ $item['days_absent'] }}</td>
                        <td class="text-end">{{ number_format($item['base_salary'], 2) }}</td>
                        <td class="text-end">{{ number_format($item['housing_allowance'] + $item['transport_allowance'] + $item['other_allowances'], 2) }}</td>
                        <td class="text-end">{{ number_format($item['overtime_pay'], 2) }}</td>
                        <td class="text-end text-danger">({{ number_format($item['total_deductions'], 2) }})</td>
                        <td class="text-end">{{ number_format($item['gross_pay'], 2) }}</td>
                        <td class="text-end fw-bold text-success">{{ number_format($item['net_pay'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="7" class="ps-3">الإجمالي</td>
                        <td class="text-end">{{ number_format($totalGross, 2) }}</td>
                        <td class="text-end text-success">{{ number_format($totalNet, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>
    </div>

    {{-- Save form --}}
    <div class="card">
        <div class="card-header fw-bold"><i class="bi bi-save"></i> حفظ المسير</div>
        <div class="card-body">
            <form method="POST" action="{{ route('hr.payroll.store') }}">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year"  value="{{ $year }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">تاريخ الصرف <span class="text-danger">*</span></label>
                        <input type="date" name="pay_date" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">ملاحظات</label>
                        <input type="text" name="notes" class="form-control" placeholder="اختياري">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"
                                onclick="return confirm('هل تريد حفظ مسير الراتب لهذه الفترة؟')">
                            <i class="bi bi-check-circle"></i> حفظ كمسودة
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @else
    <div class="alert alert-info">اختر الشهر والسنة واضغط احتساب لعرض مسير الراتب</div>
    @endif

</div>
@endsection
