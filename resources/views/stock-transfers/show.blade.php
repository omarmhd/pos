@extends('layouts.app')
@section('page-title', $stockTransfer->transfer_number)

@section('content')
<div class="row"><div class="col-lg-10 mx-auto">
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-arrow-left-right text-warning me-2"></i>
                {{ $stockTransfer->transfer_number }}
                <span class="badge bg-{{ $stockTransfer->statusColor() }} ms-2">
                    {{ $stockTransfer->statusLabel() }}
                </span>
            </h5>
            <div class="d-flex gap-2">
                @if($stockTransfer->status === 'draft')
                @can('stock_transfers.complete')
                <form action="{{ route('stock-transfers.complete', $stockTransfer) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('تنفيذ التحويل؟ سيُنقل المخزون فوراً.')">
                    @csrf
                    <button class="btn btn-success btn-sm">
                        <i class="bi bi-check2-circle me-1"></i> تنفيذ التحويل
                    </button>
                </form>
                @endcan
                @can('stock_transfers.cancel')
                <form action="{{ route('stock-transfers.cancel', $stockTransfer) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('إلغاء؟')">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i> إلغاء</button>
                </form>
                @endcan
                @endif
                <a href="{{ route('stock-transfers.index') }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> رجوع
                </a>
            </div>
        </div>
        <div class="card-body">
            {{-- Direction Banner --}}
            <div class="d-flex align-items-center justify-content-center gap-3 mb-4 p-3 bg-light rounded">
                <div class="text-center">
                    <div class="badge bg-secondary fs-6 px-3 py-2">
                        <i class="bi bi-box-seam me-1"></i>
                        {{ $stockTransfer->fromWarehouse?->name }}
                    </div>
                    <div class="small text-muted mt-1">{{ $stockTransfer->fromWarehouse?->branch?->name }}</div>
                </div>
                <div class="fs-3 text-warning"><i class="bi bi-arrow-left"></i></div>
                <div class="text-center">
                    <div class="badge bg-success fs-6 px-3 py-2">
                        <i class="bi bi-shop me-1"></i>
                        {{ $stockTransfer->toWarehouse?->name }}
                    </div>
                    <div class="small text-muted mt-1">{{ $stockTransfer->toWarehouse?->branch?->name }}</div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-4">
                    <table class="table table-sm table-borderless small">
                        <tr><th class="text-muted" width="40%">التاريخ:</th>
                            <td>{{ $stockTransfer->transfer_date->format('Y-m-d') }}</td></tr>
                        <tr><th class="text-muted">المُنشئ:</th>
                            <td>{{ $stockTransfer->user?->name }}</td></tr>
                        @if($stockTransfer->completed_at)
                        <tr><th class="text-muted">تاريخ التنفيذ:</th>
                            <td>{{ $stockTransfer->completed_at->format('Y-m-d H:i') }}</td></tr>
                        @endif
                        @if($stockTransfer->notes)
                        <tr><th class="text-muted">ملاحظات:</th>
                            <td>{{ $stockTransfer->notes }}</td></tr>
                        @endif
                    </table>
                </div>
            </div>

            {{-- Items with before/after stock levels --}}
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                    <tr>
                        <th>#</th><th>الصنف</th>
                        <th class="text-center">الكمية المُحوَّلة</th>
                        <th class="text-center">رصيد المصدر الحالي</th>
                        <th class="text-center">رصيد الوجهة الحالي</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($stockTransfer->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->product?->name }}</td>
                        <td class="text-center fw-bold">{{ $item->quantity + 0 }}</td>
                        <td class="text-center {{ ($fromLevels[$item->product_id] ?? 0) <= 5 ? 'text-danger fw-bold' : 'text-muted' }}">
                            {{ number_format($fromLevels[$item->product_id] ?? 0, 2) }}
                        </td>
                        <td class="text-center text-success fw-bold">
                            {{ number_format($toLevels[$item->product_id] ?? 0, 2) }}
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @if($stockTransfer->status === 'draft')
            <div class="alert alert-warning small mt-3">
                <i class="bi bi-exclamation-triangle me-1"></i>
                التحويل في حالة <strong>مسودة</strong> — المخزون لم يُنقل بعد.
                اضغط "تنفيذ التحويل" لتطبيق التغيير فعلياً.
            </div>
            @endif
        </div>
    </div>
</div></div>
@endsection
