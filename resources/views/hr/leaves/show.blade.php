@extends('layouts.app')
@section('page-title', 'تفاصيل الإجازة')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between">
        <h5 class="mb-0"><i class="bi bi-calendar-check text-success me-1"></i>تفاصيل الإجازة</h5>
        <span class="badge bg-{{ $leave->statusColor() }} fs-6">{{ $leave->statusLabel() }}</span>
    </div>
    <div class="card-body">
        <table class="table table-borderless">
            <tr><th width="35%">الموظف</th><td><strong>{{ $leave->employee?->name }}</strong></td></tr>
            <tr><th>نوع الإجازة</th><td><span class="badge bg-info">{{ $leave->typeLabel() }}</span></td></tr>
            <tr><th>من</th><td>{{ $leave->start_date->format('Y-m-d') }}</td></tr>
            <tr><th>إلى</th><td>{{ $leave->end_date->format('Y-m-d') }}</td></tr>
            <tr><th>أيام العمل</th><td><strong>{{ $leave->working_days }} يوم</strong></td></tr>
            <tr><th>الراتب</th><td>{{ $leave->isPaid() ? '<span class="badge bg-success">مدفوعة</span>' : '<span class="badge bg-danger">بدون راتب</span>' }}</td></tr>
            @if($leave->notes)
            <tr><th>ملاحظات</th><td>{{ $leave->notes }}</td></tr>
            @endif
            @if($leave->status !== 'pending')
            <tr><th>اعتمد بواسطة</th><td>{{ $leave->approvedBy?->name }}</td></tr>
            <tr><th>تاريخ القرار</th><td>{{ $leave->approved_at?->format('Y-m-d H:i') }}</td></tr>
            @endif
            @if($leave->rejection_reason)
            <tr><th>سبب الرفض</th><td class="text-danger">{{ $leave->rejection_reason }}</td></tr>
            @endif
        </table>

        {{-- Leave balance --}}
        <div class="alert alert-light border mt-3">
            <strong>رصيد {{ $leave->typeLabel() }} — {{ $leave->start_date->year }}</strong>
            <div class="row g-2 mt-1 text-center">
                <div class="col-4">
                    <div class="fw-bold text-primary">{{ $balance->entitled_days }}</div>
                    <div class="small text-muted">الإجمالي المستحق</div>
                </div>
                <div class="col-4">
                    <div class="fw-bold text-danger">{{ $balance->used_days }}</div>
                    <div class="small text-muted">المستهلك</div>
                </div>
                <div class="col-4">
                    <div class="fw-bold text-success">{{ $balance->balance_days }}</div>
                    <div class="small text-muted">الرصيد المتبقي</div>
                </div>
            </div>
        </div>

        @can('hr.leaves.manage')
        @if($leave->status === 'pending')
        <div class="d-flex gap-2 mt-3">
            <form action="{{ route('hr.leaves.approve', $leave) }}" method="POST">
                @csrf @method('PATCH')
                <button class="btn btn-success">
                    <i class="bi bi-check2-circle me-1"></i> اعتماد الإجازة
                </button>
            </form>
        </div>
        @endif
        @endcan

        <a href="{{ route('hr.leaves.index') }}" class="btn btn-outline-secondary mt-3">
            <i class="bi bi-arrow-right me-1"></i> رجوع
        </a>
    </div>
</div>
</div>
</div>
@endsection
