@extends('layouts.app')
@section('title', 'الموظفون')
@section('page-title', 'إدارة الموظفين')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-people"></i> قائمة الموظفين</h5>
        <a href="{{ route('hr.employees.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> إضافة موظف
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>الكود</th>
                        <th>الاسم</th>
                        <th>القسم / الوظيفة</th>
                        <th>نوع التوظيف</th>
                        <th>الراتب الإجمالي</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employees as $emp)
                    @php
                        $typeLabel = ['full_time'=>'دوام كامل','part_time'=>'دوام جزئي','contract'=>'عقد'][$emp->employment_type] ?? $emp->employment_type;
                        $payLabel  = ['monthly'=>'شهري','daily'=>'يومي','hourly'=>'بالساعة'][$emp->pay_type] ?? $emp->pay_type;
                    @endphp
                    <tr>
                        <td class="text-muted small">{{ $emp->employee_code }}</td>
                        <td class="fw-semibold">{{ $emp->name }}</td>
                        <td class="small">
                            {{ $emp->department ?? '—' }}
                            @if($emp->job_title)
                                <br><span class="text-muted">{{ $emp->job_title }}</span>
                            @endif
                        </td>
                        <td class="small">{{ $typeLabel }} / {{ $payLabel }}</td>
                        <td>{{ number_format($emp->grossMonthlySalary(), 2) }} ج.م</td>
                        <td>
                            @if($emp->is_active)
                                <span class="badge bg-success">نشط</span>
                            @else
                                <span class="badge bg-secondary">غير نشط</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('hr.employees.show', $emp) }}" class="btn btn-sm btn-info btn-action">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('hr.employees.edit', $emp) }}" class="btn btn-sm btn-primary btn-action">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
