@extends('layouts.app')
@section('page-title', $salesOrder->order_number)

@section('content')
<div class="row"><div class="col-lg-11 mx-auto">
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-bag-check text-success me-2"></i>
                {{ $salesOrder->order_number }}
                <span class="badge bg-{{ $salesOrder->statusColor() }} ms-2">{{ $salesOrder->statusLabel() }}</span>
            </h5>
            <div class="d-flex gap-2">
                @if($salesOrder->status === 'draft')
                @can('sales_orders.confirm')
                <form action="{{ route('sales-orders.confirm', $salesOrder) }}" method="POST" class="d-inline">
                    @csrf
                    <button class="btn btn-primary btn-sm"><i class="bi bi-check2-circle me-1"></i> تأكيد</button>
                </form>
                @endcan
                @endif

                @if($salesOrder->canConvertToInvoice())
                @can('sales_orders.convert')
                <a href="{{ route('sales-orders.convert-form', $salesOrder) }}" class="btn btn-success btn-sm">
                    <i class="bi bi-arrow-right-circle me-1"></i> تحويل لفاتورة بيع
                </a>
                @endcan
                @endif

                @if(in_array($salesOrder->status, ['draft','confirmed']))
                @can('sales_orders.cancel')
                <form action="{{ route('sales-orders.cancel', $salesOrder) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('إلغاء أمر البيع؟')">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i> إلغاء</button>
                </form>
                @endcan
                @endif

                <a href="{{ route('sales-orders.index') }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> رجوع
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-4">
                    <h6 class="text-muted">معلومات الأمر</h6>
                    <table class="table table-sm table-borderless small">
                        <tr><th class="text-muted" width="45%">رقم الأمر:</th>
                            <td><strong>{{ $salesOrder->order_number }}</strong></td></tr>
                        <tr><th class="text-muted">تاريخ الأمر:</th>
                            <td>{{ $salesOrder->order_date->format('Y-m-d') }}</td></tr>
                        @if($salesOrder->expected_delivery_date)
                        <tr><th class="text-muted">تاريخ التسليم:</th>
                            <td>{{ $salesOrder->expected_delivery_date->format('Y-m-d') }}</td></tr>
                        @endif
                        <tr><th class="text-muted">نوع البيع:</th>
                            <td>{{ $salesOrder->is_credit ? 'آجل (على الحساب)' : 'نقدي' }}</td></tr>
                        @if($salesOrder->quotation)
                        <tr><th class="text-muted">من عرض سعر:</th>
                            <td><a href="{{ route('sales-quotations.show', $salesOrder->quotation) }}" class="text-info">{{ $salesOrder->quotation->quotation_number }}</a></td></tr>
                        @endif
                        <tr><th class="text-muted">المخزن:</th>
                            <td>{{ $salesOrder->warehouse?->name ?? '—' }}</td></tr>
                    </table>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted">العميل</h6>
                    <table class="table table-sm table-borderless small">
                        <tr><th class="text-muted" width="40%">الاسم:</th>
                            <td><strong>{{ $salesOrder->customer?->name ?? '—' }}</strong></td></tr>
                        @if($salesOrder->customer?->phone)
                        <tr><th class="text-muted">الهاتف:</th>
                            <td>{{ $salesOrder->customer->phone }}</td></tr>
                        @endif
                    </table>
                </div>
                <div class="col-md-4 text-center">
                    <div class="card border-0 bg-success bg-opacity-10 p-3">
                        <div class="text-muted small">إجمالي الأمر</div>
                        <div class="fs-4 fw-bold">{{ number_format($salesOrder->total_amount,2) }}</div>
                        <div class="text-muted small">{{ $currency }}</div>
                    </div>
                </div>
            </div>

            {{-- Items --}}
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                    <tr><th>#</th><th>الصنف</th>
                        <th class="text-center">مطلوب</th>
                        <th class="text-center">مُسلَّم</th>
                        <th class="text-center">متبقي</th>
                        <th class="text-end">سعر الوحدة</th>
                        <th class="text-end">المجموع</th></tr>
                    </thead>
                    <tbody>
                    @foreach($salesOrder->items as $i => $item)
                    <tr class="{{ $item->isFullyDelivered() ? 'table-success':'' }}">
                        <td>{{ $i+1 }}</td>
                        <td>{{ $item->product?->name }}</td>
                        <td class="text-center">{{ $item->quantity_ordered + 0 }}</td>
                        <td class="text-center text-success">{{ $item->quantity_delivered + 0 }}</td>
                        <td class="text-center {{ $item->remainingQuantity() > 0 ? 'text-warning fw-bold':'' }}">
                            {{ $item->remainingQuantity() + 0 }}</td>
                        <td class="text-end">{{ number_format($item->unit_price,2) }} {{ $currency }}</td>
                        <td class="text-end fw-bold">{{ number_format($item->total_price,2) }} {{ $currency }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="table-light">
                    <tr><td colspan="6" class="text-end fw-bold">الإجمالي:</td>
                        <td class="text-end fw-bold fs-6">{{ number_format($salesOrder->total_amount,2) }} {{ $currency }}</td></tr>
                    </tfoot>
                </table>
            </div>

            {{-- Linked Invoices --}}
            @if($salesOrder->invoices->count() > 0)
            <h6 class="text-muted mb-2">فواتير البيع المرتبطة</h6>
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                <tr><th>رقم الفاتورة</th><th>التاريخ</th><th class="text-end">المبلغ</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($salesOrder->invoices as $inv)
                <tr>
                    <td><code>{{ $inv->invoice_number }}</code></td>
                    <td>{{ $inv->created_at->format('Y-m-d') }}</td>
                    <td class="text-end">{{ number_format($inv->total_amount,2) }} {{ $currency }}</td>
                    <td><a href="{{ route('sales.show', $inv) }}" class="btn btn-sm btn-info btn-action"><i class="bi bi-eye"></i></a></td>
                </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div></div>
@endsection
