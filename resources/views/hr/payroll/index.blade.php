@extends('layouts.app')
@section('title', 'مسيرات الرواتب')
@section('page-title', 'مسيرات الرواتب')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex gap-2">
            <a href="{{ route('hr.payroll.preview') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> تشغيل مسير راتب جديد
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">المرجع</th>
                        <th>الفترة</th>
                        <th>تاريخ الصرف</th>
                        <th class="text-end">إجمالي الرواتب</th>
                        <th class="text-end">الخصومات</th>
                        <th class="text-end">الصافي</th>
                        <th class="text-center">الحالة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                    <tr>
                        <td class="ps-3 text-muted small">{{ $run->reference }}</td>
                        <td class="fw-semibold">{{ $run->periodLabel() }}</td>
                        <td>{{ $run->pay_date->format('Y/m/d') }}</td>
                        <td class="text-end">{{ number_format($run->total_gross, 2) }}</td>
                        <td class="text-end text-danger">{{ number_format($run->total_deductions, 2) }}</td>
                        <td class="text-end fw-bold text-success">{{ number_format($run->total_net, 2) }}</td>
                        <td class="text-center">
                            @php $s = $run->status; @endphp
                            <span class="badge {{ $s=='paid'?'bg-success':($s=='approved'?'bg-primary':'bg-secondary') }}">
                                {{ ['draft'=>'مسودة','approved'=>'معتمد','paid'=>'مصروف'][$s] ?? $s }}
                            </span>
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('hr.payroll.show', $run) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">لا توجد مسيرات رواتب</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($runs->hasPages())
        <div class="card-footer">{{ $runs->links() }}</div>
        @endif
    </div>

</div>
@endsection
