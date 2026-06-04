@extends('layouts.app')
@section('page-title', 'إدارة الإجازات')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-calendar-check text-success me-2"></i>الإجازات</h4>
    @can('hr.leaves.create')
    <a href="{{ route('hr.leaves.create') }}" class="btn btn-success btn-sm">
        <i class="bi bi-plus-circle me-1"></i> طلب إجازة جديد
    </a>
    @endcan
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">الحالة</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">الكل</option>
                    @foreach(\App\Models\EmployeeLeave::STATUSES as $k => $v)
                        <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $v['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">السنة</label>
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    @for($y = now()->year; $y >= now()->year - 2; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 dt-table" style="width:100%"
                   data-title="الإجازات">
                <thead class="table-dark">
                    <tr>
                        <th>الموظف</th>
                        <th>نوع الإجازة</th>
                        <th>من</th>
                        <th>إلى</th>
                        <th class="text-center">أيام عمل</th>
                        <th class="text-center">الحالة</th>
                        <th>اعتمد بواسطة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($leaves as $leave)
                <tr>
                    <td>
                        <strong>{{ $leave->employee?->name }}</strong>
                        <div class="text-muted small">{{ $leave->employee?->employee_code }}</div>
                    </td>
                    <td><span class="badge bg-info small">{{ $leave->typeLabel() }}</span></td>
                    <td>{{ $leave->start_date->format('Y-m-d') }}</td>
                    <td>{{ $leave->end_date->format('Y-m-d') }}</td>
                    <td class="text-center">
                        <span class="badge bg-secondary">{{ $leave->working_days }} يوم</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-{{ $leave->statusColor() }}">{{ $leave->statusLabel() }}</span>
                    </td>
                    <td class="text-muted small">{{ $leave->approvedBy?->name ?? '—' }}</td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('hr.leaves.show', $leave) }}"
                               class="btn btn-sm btn-info btn-action"><i class="bi bi-eye"></i></a>
                            @can('hr.leaves.manage')
                            @if($leave->status === 'pending')
                                <form action="{{ route('hr.leaves.approve', $leave) }}" method="POST" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-success btn-action" title="اعتماد">
                                        <i class="bi bi-check2"></i>
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-outline-danger btn-action"
                                        title="رفض"
                                        onclick="rejectLeave({{ $leave->id }})">
                                    <i class="bi bi-x"></i>
                                </button>
                            @endif
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-5">
                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>لا توجد طلبات إجازة
                </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($leaves->hasPages())
    <div class="card-footer">{{ $leaves->links() }}</div>
    @endif
</div>

{{-- Reject modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title">رفض طلب الإجازة</h6></div>
            <form id="rejectForm" method="POST">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <textarea name="rejection_reason" class="form-control" rows="3"
                              placeholder="سبب الرفض..." required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger btn-sm">رفض</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function rejectLeave(id) {
    document.getElementById('rejectForm').action = '/hr/leaves/' + id + '/reject';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>
@endsection
