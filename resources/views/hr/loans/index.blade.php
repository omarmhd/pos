@extends('layouts.app')
@section('page-title', 'سلف الموظفين')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-cash-coin text-warning me-2"></i>سلف الموظفين</h5>
        @can('hr.manage_loans')
        <a href="{{ route('hr.loans.create') }}" class="btn btn-warning btn-sm">
            <i class="bi bi-plus-circle me-1"></i> سلفة جديدة
        </a>
        @endcan
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="سلف الموظفين">
                <thead class="table-light">
                <tr>
                    <th>الموظف</th>
                    <th>التاريخ</th>
                    <th class="text-end">المبلغ</th>
                    <th class="text-end">القسط/شهر</th>
                    <th class="text-end">المتبقي</th>
                    <th class="text-center">الأقساط</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center">الإجراءات</th>
                </tr>
                </thead>
                <tbody>
                @foreach($loans as $loan)
                <tr>
                    <td>
                        <strong>{{ $loan->employee?->name }}</strong>
                        <br><small class="text-muted">{{ $loan->employee?->employee_code }}</small>
                    </td>
                    <td>{{ $loan->loan_date->format('Y-m-d') }}</td>
                    <td class="text-end">{{ number_format($loan->amount, 2) }} {{ $currency }}</td>
                    <td class="text-end">{{ number_format($loan->monthly_installment, 2) }} {{ $currency }}</td>
                    <td class="text-end fw-bold {{ $loan->remaining_balance > 0 ? 'text-danger' : 'text-success' }}">
                        {{ number_format($loan->remaining_balance, 2) }} {{ $currency }}
                    </td>
                    <td class="text-center">
                        {{ $loan->installments_paid }} / {{ $loan->installments_total }}
                        <div class="progress mt-1" style="height:4px;">
                            <div class="progress-bar bg-warning"
                                 style="width: {{ $loan->installments_total > 0 ? ($loan->installments_paid / $loan->installments_total * 100) : 0 }}%"></div>
                        </div>
                    </td>
                    <td class="text-center">
                        @if($loan->status === 'active')
                            <span class="badge bg-warning text-dark">نشطة</span>
                        @elseif($loan->status === 'settled')
                            <span class="badge bg-success">مُسدَّدة</span>
                        @else
                            <span class="badge bg-secondary">ملغاة</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <a href="{{ route('hr.loans.show', $loan) }}" class="btn btn-sm btn-info btn-action">
                            <i class="bi bi-eye"></i>
                        </a>
                        @if($loan->status === 'active')
                        @can('hr.manage_loans')
                        <form action="{{ route('hr.loans.cancel', $loan) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('إلغاء هذه السلفة؟')">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-secondary btn-action"><i class="bi bi-x-circle"></i></button>
                        </form>
                        @endcan
                        @endif
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
