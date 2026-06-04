@extends('layouts.app')
@section('page-title', 'تنبيهات انتهاء صلاحية الدُّفعات')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-exclamation-triangle text-warning me-2"></i>
        تنبيهات انتهاء صلاحية الدُّفعات (Lot/Batch)
    </h4>
    <form method="GET" class="d-flex gap-2">
        <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="30"  {{ $days == 30  ? 'selected':'' }}>خلال 30 يوم</option>
            <option value="60"  {{ $days == 60  ? 'selected':'' }}>خلال 60 يوم</option>
            <option value="90"  {{ $days == 90  ? 'selected':'' }}>خلال 90 يوم</option>
        </select>
    </form>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 dt-table" style="width:100%"
                   data-title="تنبيهات الصلاحية">
                <thead class="table-dark">
                    <tr>
                        <th>المنتج</th>
                        <th>الفئة</th>
                        <th>المخزن</th>
                        <th>رقم الدُّفعة</th>
                        <th class="text-center">تاريخ الانتهاء</th>
                        <th class="text-center">الأيام المتبقية</th>
                        <th class="text-end">الكمية المستلمة</th>
                        <th class="text-end">التكلفة</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($lots as $lot)
                @php
                    $daysLeft = now()->diffInDays($lot->expiry_date, false);
                    $rowClass = $daysLeft <= 7 ? 'table-danger' : ($daysLeft <= 30 ? 'table-warning' : '');
                @endphp
                <tr class="{{ $rowClass }}">
                    <td>
                        <strong>{{ $lot->product?->name }}</strong>
                        <div class="text-muted small">{{ $lot->product?->barcode }}</div>
                    </td>
                    <td><span class="badge bg-info small">{{ $lot->product?->category?->name }}</span></td>
                    <td class="text-muted small">{{ $lot->warehouse?->name ?? '—' }}</td>
                    <td><code>{{ $lot->lot_number }}</code></td>
                    <td class="text-center fw-bold">{{ $lot->expiry_date->format('Y-m-d') }}</td>
                    <td class="text-center">
                        @if($daysLeft <= 0)
                            <span class="badge bg-danger">منتهي</span>
                        @elseif($daysLeft <= 7)
                            <span class="badge bg-danger">{{ $daysLeft }} أيام</span>
                        @elseif($daysLeft <= 30)
                            <span class="badge bg-warning text-dark">{{ $daysLeft }} يوم</span>
                        @else
                            <span class="badge bg-secondary">{{ $daysLeft }} يوم</span>
                        @endif
                    </td>
                    <td class="text-end">{{ number_format($lot->quantity, 2) }}</td>
                    <td class="text-end font-monospace small">{{ number_format($lot->cost, 2) }} {{ $currency }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-success py-5">
                    <i class="bi bi-check-circle fs-1 d-block mb-2"></i>
                    لا توجد دُفعات تنتهي صلاحيتها خلال {{ $days }} يوماً القادمة
                </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($lots->hasPages())
    <div class="card-footer">{{ $lots->links() }}</div>
    @endif
</div>
@endsection
