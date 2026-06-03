@extends('layouts.app')
@section('page-title', 'إغلاق الفترات المحاسبية')

@section('content')

{{-- Summary banner --}}
<div class="row g-3 mb-4">
    @php
        $openCount   = $periods->where('status','open')->count();
        $lockedCount = $periods->where('status','locked')->count();
        $currentPeriod = \App\Models\AccountingPeriod::forDate(now());
    @endphp
    <div class="col-md-3">
        <div class="card border-0 bg-success bg-opacity-10 text-center py-3">
            <div class="fs-3 fw-bold text-success">{{ $openCount }}</div>
            <div class="small text-muted">فترات مفتوحة</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-secondary bg-opacity-10 text-center py-3">
            <div class="fs-3 fw-bold text-secondary">{{ $lockedCount }}</div>
            <div class="small text-muted">فترات مقفلة</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 bg-primary bg-opacity-10 py-3 px-3">
            <div class="small text-muted">الفترة الحالية</div>
            <div class="fw-bold fs-5">{{ $currentPeriod->periodLabel() }}</div>
            <span class="badge {{ $currentPeriod->isLocked() ? 'bg-danger' : 'bg-success' }}">
                {{ $currentPeriod->isLocked() ? 'مقفلة' : 'مفتوحة' }}
            </span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-calendar-lock text-primary me-2"></i>إدارة الفترات المحاسبية
        </h5>
        <div class="alert-tip d-inline-block small mb-0 py-1 px-3 border rounded">
            <i class="bi bi-info-circle me-1"></i>
            الفترة المقفلة تمنع أي قيد بتاريخها — حتى قيود الاستهلاك والأجور
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th>الفترة</th>
                <th class="text-center">الحالة</th>
                <th class="text-center">عدد القيود</th>
                <th class="text-end">إجمالي المدين</th>
                <th>تاريخ الإغلاق</th>
                <th>المغلِق</th>
                <th class="text-center">الإجراء</th>
            </tr>
            </thead>
            <tbody>
            @foreach($periods as $period)
            <tr class="{{ $period->isLocked() ? 'table-secondary' : '' }}">
                <td class="fw-semibold">{{ $period->periodLabel() }}</td>
                <td class="text-center">
                    @if($period->isLocked())
                        <span class="badge bg-secondary">
                            <i class="bi bi-lock-fill me-1"></i>مقفلة
                        </span>
                    @else
                        <span class="badge bg-success">
                            <i class="bi bi-unlock-fill me-1"></i>مفتوحة
                        </span>
                    @endif
                </td>
                <td class="text-center">
                    {{ $period->entry_count > 0 ? number_format($period->entry_count) : '—' }}
                </td>
                <td class="text-end text-muted small">
                    {{ $period->total_debit > 0 ? number_format($period->total_debit, 2).' '.$currency : '—' }}
                </td>
                <td class="small text-muted">
                    {{ $period->locked_at?->format('Y-m-d') ?? '—' }}
                </td>
                <td class="small text-muted">
                    {{ $period->lockedBy?->name ?? '—' }}
                </td>
                <td class="text-center">
                    @can('accounting.periods.manage')
                    @if($period->isLocked())
                        {{-- Unlock button — only admin --}}
                        @if(auth()->user()->hasRole('admin'))
                        <form action="{{ route('accounting.periods.unlock') }}" method="POST" class="d-inline"
                              onsubmit="return confirm('فتح فترة {{ $period->periodLabel() }}؟ يسمح بإضافة قيود جديدة.')">
                            @csrf
                            <input type="hidden" name="year"  value="{{ $period->year }}">
                            <input type="hidden" name="month" value="{{ $period->month }}">
                            <button class="btn btn-sm btn-outline-success">
                                <i class="bi bi-unlock"></i> فتح
                            </button>
                        </form>
                        @else
                            <span class="text-muted small">للأدمن فقط</span>
                        @endif
                    @else
                        {{-- Lock button + optional notes --}}
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal"
                                data-bs-target="#lockModal"
                                data-year="{{ $period->year }}"
                                data-month="{{ $period->month }}"
                                data-label="{{ $period->periodLabel() }}">
                            <i class="bi bi-lock"></i> إغلاق
                        </button>
                    @endif
                    @endcan
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Lock Confirmation Modal --}}
@can('accounting.periods.manage')
<div class="modal fade" id="lockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-lock-fill text-warning me-2"></i>
                    إغلاق الفترة: <span id="lockModalLabel"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('accounting.periods.lock') }}" method="POST">
                @csrf
                <input type="hidden" name="year"  id="lockYear">
                <input type="hidden" name="month" id="lockMonth">
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        <strong>تحذير:</strong> بعد الإغلاق لن يقبل النظام أي قيد محاسبي بتاريخ هذه الفترة.
                        فتحها لاحقاً يتطلب صلاحية الأدمن.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ملاحظات الإغلاق (اختياري)</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="مثال: تمت مراجعة القيود، تم الإغلاق الشهري…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-lock-fill me-1"></i> تأكيد الإغلاق
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@endsection

@section('scripts')
<script>
const lockModal = document.getElementById('lockModal');
if (lockModal) {
    lockModal.addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget;
        document.getElementById('lockModalLabel').textContent = btn.dataset.label;
        document.getElementById('lockYear').value  = btn.dataset.year;
        document.getElementById('lockMonth').value = btn.dataset.month;
    });
}
</script>
@endsection
