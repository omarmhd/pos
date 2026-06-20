@extends('layouts.app')
@section('title', 'ملف الموظف')
@section('page-title', 'ملف الموظف')

@section('content')
<div class="container-fluid">

    {{-- Header card --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h4 class="mb-1">
                        {{ $employee->name }}
                        @if(!$employee->is_active)<span class="badge bg-secondary ms-2">غير نشط</span>@endif
                    </h4>
                    <div class="text-muted small">
                        <span class="badge bg-light text-dark me-2">{{ $employee->employee_code }}</span>
                        {{ $employee->job_title ?? '' }} @if($employee->department) — {{ $employee->department }} @endif
                    </div>
                    <div class="text-muted small mt-1">
                        @if($employee->phone)<i class="bi bi-telephone"></i> {{ $employee->phone }} &nbsp;@endif
                        @if($employee->email)<i class="bi bi-envelope"></i> {{ $employee->email }}@endif
                    </div>
                </div>
                <div class="d-flex gap-2">
                    @can('ledger.view')
                    <a href="{{ route('accounting.ledger.party', ['employee', $employee->id]) }}" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-journal-bookmark"></i> كشف الأستاذ المساعد
                    </a>
                    @endcan
                    <a href="{{ route('hr.attendance.daily', ['date' => today()->toDateString()]) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-calendar-check"></i> سجل الحضور
                    </a>
                    <a href="{{ route('hr.employees.edit', $employee) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-pencil"></i> تعديل
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Salary details --}}
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header fw-bold"><i class="bi bi-cash-stack"></i> تفاصيل الراتب</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><td class="text-muted">الراتب الأساسي</td><td class="text-end fw-semibold">{{ number_format($employee->base_salary, 2) }}</td></tr>
                        <tr><td class="text-muted">بدل السكن</td><td class="text-end">{{ number_format($employee->housing_allowance, 2) }}</td></tr>
                        <tr><td class="text-muted">بدل المواصلات</td><td class="text-end">{{ number_format($employee->transport_allowance, 2) }}</td></tr>
                        <tr><td class="text-muted">بدلات أخرى</td><td class="text-end">{{ number_format($employee->other_allowances, 2) }}</td></tr>
                        <tr class="border-top fw-bold"><td>الإجمالي الشهري</td><td class="text-end text-success">{{ number_format($employee->grossMonthlySalary(), 2) }}</td></tr>
                        <tr><td class="text-muted small">طريقة الاحتساب</td>
                            <td class="text-end small">{{ ['monthly'=>'شهري','daily'=>'يومي','hourly'=>'بالساعة'][$employee->pay_type] }}</td></tr>
                        <tr><td class="text-muted small">المعدل اليومي</td><td class="text-end small">{{ number_format($employee->dailyRate(), 2) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Recent payroll --}}
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header fw-bold"><i class="bi bi-receipt"></i> آخر مسيرات الراتب</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">الفترة</th>
                                <th class="text-end">الإجمالي</th>
                                <th class="text-end">الخصومات</th>
                                <th class="text-end">الصافي</th>
                                <th class="text-center">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPayroll as $item)
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('hr.payroll.show', $item->payrollRun) }}" class="text-decoration-none">
                                        {{ $item->payrollRun->periodLabel() }}
                                    </a>
                                </td>
                                <td class="text-end">{{ number_format($item->gross_pay, 2) }}</td>
                                <td class="text-end text-danger">{{ number_format($item->total_deductions, 2) }}</td>
                                <td class="text-end fw-bold text-success">{{ number_format($item->net_pay, 2) }}</td>
                                <td class="text-center">
                                    @php $s = $item->payrollRun->status; @endphp
                                    <span class="badge {{ $s=='paid'?'bg-success':($s=='approved'?'bg-primary':'bg-secondary') }}">
                                        {{ ['draft'=>'مسودة','approved'=>'معتمد','paid'=>'مصروف'][$s] ?? $s }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">لا توجد مسيرات راتب</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
