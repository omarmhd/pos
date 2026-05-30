@extends('layouts.app')
@section('title', 'تقرير الحضور الشهري')
@section('page-title', 'تقرير الحضور الشهري')

@section('content')
<div class="container-fluid">

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">الموظف</label>
                    <select name="employee_id" class="form-select" required>
                        <option value="">— اختر موظفاً —</option>
                        @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ $employeeId==$emp->id ? 'selected' : '' }}>{{ $emp->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
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
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-graph-up"></i> عرض
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if($records->count() > 0)
    {{-- Summary chips --}}
    <div class="row g-3 mb-4">
        @foreach(['present'=>['حاضر','success'],'absent'=>['غائب','danger'],'half_day'=>['نصف يوم','warning'],'leave'=>['إجازة','info'],'holiday'=>['إجازة رسمية','secondary']] as $k=>[$label,$color])
        <div class="col">
            <div class="card text-center border-0 bg-{{ $color }} bg-opacity-10">
                <div class="card-body py-2">
                    <div class="fw-bold fs-5 text-{{ $color }}">{{ $summary[$k] ?? 0 }}</div>
                    <div class="small">{{ $label }}</div>
                </div>
            </div>
        </div>
        @endforeach
        <div class="col">
            <div class="card text-center border-0 bg-light">
                <div class="card-body py-2">
                    <div class="fw-bold fs-5">{{ number_format($summary['total_hours'], 1) }}</div>
                    <div class="small">ساعات عمل</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center border-0 bg-light">
                <div class="card-body py-2">
                    <div class="fw-bold fs-5 text-warning">{{ number_format($summary['overtime_hours'], 1) }}</div>
                    <div class="small">أوفرتايم</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 small">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">التاريخ</th>
                        <th>الوردية</th>
                        <th>دخول</th>
                        <th>خروج</th>
                        <th class="text-center">ساعات</th>
                        <th class="text-center">أوفرتايم</th>
                        <th class="text-center">الحالة</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $r)
                    <tr>
                        <td class="ps-3">{{ $r->work_date->format('Y/m/d') }}</td>
                        <td class="text-muted">{{ $r->shift?->name ?? '—' }}</td>
                        <td>{{ $r->check_in ?? '—' }}</td>
                        <td>{{ $r->check_out ?? '—' }}</td>
                        <td class="text-center">{{ $r->hours_worked }}</td>
                        <td class="text-center text-warning">{{ $r->overtime_hours > 0 ? $r->overtime_hours : '—' }}</td>
                        <td class="text-center">
                            @php
                            $badges = ['present'=>'bg-success','absent'=>'bg-danger','half_day'=>'bg-warning','holiday'=>'bg-secondary','leave'=>'bg-info'];
                            $labels = ['present'=>'حاضر','absent'=>'غائب','half_day'=>'نصف يوم','holiday'=>'إجازة رسمية','leave'=>'إجازة'];
                            @endphp
                            <span class="badge {{ $badges[$r->status] ?? 'bg-secondary' }}">
                                {{ $labels[$r->status] ?? $r->status }}
                            </span>
                        </td>
                        <td class="text-muted">{{ $r->notes ?? '' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @elseif($employeeId)
    <div class="alert alert-info">لا توجد سجلات حضور لهذا الموظف في الفترة المحددة</div>
    @endif

</div>
@endsection
