@extends('layouts.app')
@section('page-title', $purchaseOrder->po_number)

@section('content')
<div class="row"><div class="col-lg-11 mx-auto">
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-file-earmark-text text-warning me-2"></i>
                {{ $purchaseOrder->po_number }}
                <span class="badge bg-{{ $purchaseOrder->statusColor() }} ms-2">
                    {{ $purchaseOrder->statusLabel() }}
                </span>
            </h5>
            <div class="d-flex gap-2">
                @if($purchaseOrder->status === 'draft')
                @can('purchase_orders.send')
                <form action="{{ route('purchase-orders.send', $purchaseOrder) }}" method="POST" class="d-inline">
                    @csrf
                    <button class="btn btn-primary btn-sm">
                        <i class="bi bi-send me-1"></i> إرسال للمورد
                    </button>
                </form>
                @endcan
                @endif

                @if($purchaseOrder->canConvertToInvoice())
                @can('purchase_orders.convert')
                <a href="{{ route('purchase-orders.convert-form', $purchaseOrder) }}" class="btn btn-success btn-sm">
                    <i class="bi bi-arrow-right-circle me-1"></i> تحويل لفاتورة شراء
                </a>
                @endcan
                @endif

                @if(in_array($purchaseOrder->status, ['draft','sent']))
                @can('purchase_orders.cancel')
                <form action="{{ route('purchase-orders.cancel', $purchaseOrder) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('إلغاء أمر الشراء؟')">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-x-circle me-1"></i> إلغاء
                    </button>
                </form>
                @endcan
                @endif

                <a href="{{ route('purchase-orders.pdf', $purchaseOrder) }}" target="_blank"
                   class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-printer me-1"></i> طباعة / عرض
                </a>
                <a href="{{ route('purchase-orders.pdf', ['purchaseOrder' => $purchaseOrder, 'download' => 1]) }}"
                   class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf me-1"></i> تنزيل PDF
                </a>

                <a href="{{ route('purchase-orders.index') }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> رجوع
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-5">
                    <h6 class="text-muted">معلومات الأمر</h6>
                    <table class="table table-sm table-borderless small">
                        <tr><th class="text-muted" width="45%">رقم الأمر:</th>
                            <td><strong>{{ $purchaseOrder->po_number }}</strong></td></tr>
                        <tr><th class="text-muted">تاريخ الأمر:</th>
                            <td>{{ $purchaseOrder->order_date->format('Y-m-d') }}</td></tr>
                        @if($purchaseOrder->expected_delivery_date)
                        <tr><th class="text-muted">تاريخ التسليم:</th>
                            <td>{{ $purchaseOrder->expected_delivery_date->format('Y-m-d') }}</td></tr>
                        @endif
                        @if($purchaseOrder->sent_at)
                        <tr><th class="text-muted">تاريخ الإرسال:</th>
                            <td>{{ $purchaseOrder->sent_at->format('Y-m-d') }}</td></tr>
                        @endif
                        <tr><th class="text-muted">المخزن:</th>
                            <td>{{ $purchaseOrder->warehouse?->name ?? '—' }}</td></tr>
                        <tr><th class="text-muted">الفرع:</th>
                            <td>{{ $purchaseOrder->branch?->name ?? '—' }}</td></tr>
                        <tr><th class="text-muted">المُنشئ:</th>
                            <td>{{ $purchaseOrder->user?->name }}</td></tr>
                    </table>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted">المورد</h6>
                    <table class="table table-sm table-borderless small">
                        <tr><th class="text-muted" width="40%">الاسم:</th>
                            <td><strong>{{ $purchaseOrder->supplier->name }}</strong></td></tr>
                        @if($purchaseOrder->supplier->company)
                        <tr><th class="text-muted">الشركة:</th>
                            <td>{{ $purchaseOrder->supplier->company }}</td></tr>
                        @endif
                        @if($purchaseOrder->supplier->phone)
                        <tr><th class="text-muted">الهاتف:</th>
                            <td>{{ $purchaseOrder->supplier->phone }}</td></tr>
                        @endif
                    </table>
                </div>
                <div class="col-md-3 text-center">
                    <div class="card border-0 bg-warning bg-opacity-10 p-3">
                        <div class="text-muted small">إجمالي الأمر</div>
                        <div class="fs-4 fw-bold">{{ number_format($purchaseOrder->total_amount, 2) }}</div>
                        <div class="text-muted small">{{ $currency }}</div>
                    </div>
                </div>
            </div>

            {{-- Items --}}
            <h6 class="text-muted mb-2">الأصناف المطلوبة</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                    <tr>
                        <th>#</th><th>الصنف</th>
                        <th class="text-center">الكمية المطلوبة</th>
                        <th class="text-center">المُستلَمة</th>
                        <th class="text-center">المتبقية</th>
                        <th class="text-end">سعر الوحدة</th>
                        <th class="text-end">المجموع</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($purchaseOrder->items as $i => $item)
                    <tr class="{{ $item->isFullyReceived() ? 'table-success' : '' }}">
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->product?->name }}</td>
                        <td class="text-center">{{ $item->quantity_ordered + 0 }}</td>
                        <td class="text-center text-success">{{ $item->quantity_received + 0 }}</td>
                        <td class="text-center {{ $item->remainingQuantity() > 0 ? 'text-warning fw-bold' : 'text-success' }}">
                            {{ $item->remainingQuantity() + 0 }}
                        </td>
                        <td class="text-end">{{ number_format($item->unit_price, 2) }} {{ $currency }}</td>
                        <td class="text-end"><strong>{{ number_format($item->total_price, 2) }} {{ $currency }}</strong></td>
                    </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="table-light">
                    <tr>
                        <td colspan="6" class="text-end fw-bold">الإجمالي:</td>
                        <td class="text-end fw-bold fs-6">{{ number_format($purchaseOrder->total_amount, 2) }} {{ $currency }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Linked Invoices --}}
            @if($purchaseOrder->invoices->count() > 0)
            <h6 class="text-muted mb-2">فواتير الشراء المرتبطة</h6>
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                <tr><th>رقم الفاتورة</th><th>التاريخ</th><th class="text-end">المبلغ</th><th class="text-center">الحالة</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($purchaseOrder->invoices as $inv)
                <tr>
                    <td><code>{{ $inv->invoice_number }}</code></td>
                    <td>{{ $inv->created_at->format('Y-m-d') }}</td>
                    <td class="text-end">{{ number_format($inv->total_amount, 2) }} {{ $currency }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ $inv->payment_status === 'paid' ? 'success' : ($inv->payment_status === 'partial' ? 'warning text-dark' : 'secondary') }}">
                            {{ ['paid'=>'مدفوع','partial'=>'جزئي','unpaid'=>'غير مدفوع'][$inv->payment_status] ?? $inv->payment_status }}
                        </span>
                    </td>
                    <td><a href="{{ route('purchases.show', $inv) }}" class="btn btn-sm btn-info btn-action"><i class="bi bi-eye"></i></a></td>
                </tr>
                @endforeach
                </tbody>
            </table>
            @endif

            @if($purchaseOrder->terms || $purchaseOrder->notes)
            <div class="row mt-3">
                @if($purchaseOrder->terms)
                <div class="col-md-6">
                    <div class="alert alert-light border small">
                        <strong><i class="bi bi-file-text me-1"></i>الشروط:</strong><br>
                        {{ $purchaseOrder->terms }}
                    </div>
                </div>
                @endif
                @if($purchaseOrder->notes)
                <div class="col-md-6">
                    <div class="alert alert-light border small">
                        <strong><i class="bi bi-sticky me-1"></i>ملاحظات:</strong><br>
                        {{ $purchaseOrder->notes }}
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>
</div></div>
@endsection
