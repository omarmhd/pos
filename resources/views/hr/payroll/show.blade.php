@extends('layouts.app')
@section('title', 'مسير راتب')
@section('page-title', 'مسير راتب — ' . $payrollRun->periodLabel())

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">{{ $payrollRun->periodLabel() }}
                    <span class="badge {{ $payrollRun->status=='paid'?'bg-success':($payrollRun->status=='approved'?'bg-primary':'bg-secondary') }} ms-2">
                        {{ ['draft'=>'مسودة','approved'=>'معتمد','paid'=>'مصروف'][$payrollRun->status] ?? $payrollRun->status }}
                    </span>
                </h5>
                <div class="text-muted small">
                    المرجع: {{ $payrollRun->reference }} &nbsp;|&nbsp;
                    تاريخ الصرف: {{ $payrollRun->pay_date->format('Y/m/d') }} &nbsp;|&nbsp;
                    أنشئ بواسطة: {{ $payrollRun->createdBy?->name ?? '—' }}
                    @if($payrollRun->approvedBy)
                     &nbsp;|&nbsp; اعتمد بواسطة: {{ $payrollRun->approvedBy->name }}
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2">
                @if($payrollRun->status === 'draft')
                <form method="POST" action="{{ route('hr.payroll.approve', $payrollRun) }}"
                      onsubmit="return confirm('هل تريد اعتماد هذا المسير وترحيل القيد المحاسبي؟')">
                    @csrf @method('PATCH')
                    <button class="btn btn-success">
                        <i class="bi bi-check2-all"></i> اعتماد وترحيل القيد
                    </button>
                </form>
                @endif
                @if($payrollRun->journal_entry_id)
                <a href="{{ route('journal_entries.show', $payrollRun->journal_entry_id) }}" class="btn btn-outline-info btn-sm">
                    <i class="bi bi-journal-text"></i> القيد
                </a>
                @endif
                <a href="{{ route('hr.payroll.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> رجوع
                </a>
            </div>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center bg-light">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-primary">{{ number_format($payrollRun->total_gross, 2) }}</div>
                    <small class="text-muted">إجمالي الرواتب</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center bg-light">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-danger">{{ number_format($payrollRun->total_deductions, 2) }}</div>
                    <small class="text-muted">إجمالي الخصومات</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center bg-success bg-opacity-10">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-success">{{ number_format($payrollRun->total_net, 2) }}</div>
                    <small class="text-muted">الصافي المصروف</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Payslip table --}}
    <div class="card">
        <div class="card-header fw-bold"><i class="bi bi-table"></i> كشف الرواتب التفصيلي</div>
        <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">الموظف</th>
                        <th class="text-center">حضور</th>
                        <th class="text-center">غياب</th>
                        <th class="text-end">أساسي</th>
                        <th class="text-end">سكن</th>
                        <th class="text-end">مواصلات</th>
                        <th class="text-end">أخرى</th>
                        <th class="text-end">أوفرتايم</th>
                        <th class="text-end text-danger">خصم غياب</th>
                        <th class="text-end text-warning">قسط سلفة</th>
                        <th class="text-end">إجمالي</th>
                        <th class="text-end fw-bold">صافي</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payrollRun->items as $item)
                    <tr>
                        <td class="ps-3 fw-semibold">{{ $item->employee->name }}</td>
                        <td class="text-center">{{ $item->days_worked }}</td>
                        <td class="text-center {{ $item->days_absent>0?'text-danger':'' }}">{{ $item->days_absent }}</td>
                        <td class="text-end">{{ number_format($item->base_salary, 2) }}</td>
                        <td class="text-end">{{ number_format($item->housing_allowance, 2) }}</td>
                        <td class="text-end">{{ number_format($item->transport_allowance, 2) }}</td>
                        <td class="text-end">{{ number_format($item->other_allowances, 2) }}</td>
                        <td class="text-end">{{ number_format($item->overtime_pay, 2) }}</td>
                        <td class="text-end text-danger">({{ number_format($item->absence_deduction, 2) }})</td>
                        <td class="text-end text-warning">
                            @if($item->loan_deduction > 0)
                                ({{ number_format($item->loan_deduction, 2) }})
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($item->gross_pay, 2) }}</td>
                        <td class="text-end fw-bold text-success">{{ number_format($item->net_pay, 2) }}</td>
                        <td>
                            <a href="{{ route('hr.payroll.payslip', [$payrollRun, $item]) }}"
                               class="btn btn-outline-danger btn-sm" target="_blank" title="تنزيل قسيمة الراتب PDF">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="10" class="ps-3">الإجمالي</td>
                        <td class="text-end">{{ number_format($payrollRun->total_gross, 2) }}</td>
                        <td class="text-end text-success">{{ number_format($payrollRun->total_net, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>
    </div>

</div>
@endsection
