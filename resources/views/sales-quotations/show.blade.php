@extends('layouts.app')
@section('page-title', $salesQuotation->quotation_number)

@section('content')
<div class="row"><div class="col-lg-11 mx-auto">
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-file-earmark-richtext text-info me-2"></i>
                {{ $salesQuotation->quotation_number }}
                <span class="badge bg-{{ $salesQuotation->statusColor() }} ms-2">
                    {{ $salesQuotation->statusLabel() }}
                </span>
                @if($salesQuotation->isExpired())
                    <span class="badge bg-warning ms-1">منتهي الصلاحية</span>
                @endif
            </h5>
            <div class="d-flex gap-2">
                @if($salesQuotation->status === 'draft')
                @can('quotations.send')
                <form action="{{ route('sales-quotations.send', $salesQuotation) }}" method="POST" class="d-inline">
                    @csrf
                    <button class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i> إرسال للعميل</button>
                </form>
                @endcan
                @endif

                @if($salesQuotation->canConvert())
                @can('quotations.convert')
                <a href="{{ route('sales-quotations.convert-to-order-form', $salesQuotation) }}"
                   class="btn btn-success btn-sm">
                    <i class="bi bi-arrow-right-circle me-1"></i> تحويل لأمر بيع
                </a>
                @endcan
                @endif

                @if(in_array($salesQuotation->status, ['draft','sent']))
                @can('quotations.cancel')
                <form action="{{ route('sales-quotations.reject', $salesQuotation) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('تسجيل رفض العرض؟')">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i> رفض</button>
                </form>
                @endcan
                @endif

                <a href="{{ route('sales-quotations.index') }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> رجوع
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-5">
                    <h6 class="text-muted">معلومات العرض</h6>
                    <table class="table table-sm table-borderless small">
                        <tr><th class="text-muted" width="45%">رقم العرض:</th>
                            <td><strong>{{ $salesQuotation->quotation_number }}</strong></td></tr>
                        <tr><th class="text-muted">تاريخ العرض:</th>
                            <td>{{ $salesQuotation->quotation_date->format('Y-m-d') }}</td></tr>
                        @if($salesQuotation->valid_until)
                        <tr><th class="text-muted">صالح حتى:</th>
                            <td class="{{ $salesQuotation->isExpired() ? 'text-danger fw-bold':'' }}">
                                {{ $salesQuotation->valid_until->format('Y-m-d') }}
                            </td></tr>
                        @endif
                        @if($salesQuotation->priceList)
                        <tr><th class="text-muted">قائمة الأسعار:</th>
                            <td>{{ $salesQuotation->priceList->name }}</td></tr>
                        @endif
                        <tr><th class="text-muted">المُنشئ:</th>
                            <td>{{ $salesQuotation->user?->name }}</td></tr>
                        @if($salesQuotation->branch)
                        <tr><th class="text-muted">الفرع:</th>
                            <td><span class="badge bg-primary">{{ $salesQuotation->branch->name }}</span></td></tr>
                        @endif
                    </table>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted">العميل</h6>
                    <table class="table table-sm table-borderless small">
                        <tr><th class="text-muted" width="40%">الاسم:</th>
                            <td><strong>{{ $salesQuotation->displayName() }}</strong></td></tr>
                        @if($salesQuotation->customer?->phone)
                        <tr><th class="text-muted">الهاتف:</th>
                            <td>{{ $salesQuotation->customer->phone }}</td></tr>
                        @endif
                    </table>
                </div>
                <div class="col-md-3 text-center">
                    <div class="card border-0 bg-info bg-opacity-10 p-3">
                        <div class="text-muted small">إجمالي العرض</div>
                        <div class="fs-4 fw-bold">{{ number_format($salesQuotation->total_amount, 2) }}</div>
                        <div class="text-muted small">{{ $currency }}</div>
                        @if($salesQuotation->discount_amount > 0)
                        <div class="text-success small">خصم: {{ number_format($salesQuotation->discount_amount, 2) }} {{ $currency }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Items --}}
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                    <tr><th>#</th><th>الصنف</th><th class="text-center">الكمية</th>
                        <th class="text-end">سعر الوحدة</th><th class="text-center">خصم%</th>
                        <th class="text-end">المجموع</th></tr>
                    </thead>
                    <tbody>
                    @foreach($salesQuotation->items as $i => $item)
                    <tr>
                        <td>{{ $i+1 }}</td>
                        <td>{{ $item->product?->name }}</td>
                        <td class="text-center">{{ $item->quantity + 0 }}</td>
                        <td class="text-end">{{ number_format($item->unit_price,2) }} {{ $currency }}</td>
                        <td class="text-center">{{ $item->discount_percent > 0 ? $item->discount_percent.'%' : '—' }}</td>
                        <td class="text-end fw-bold">{{ number_format($item->total_price,2) }} {{ $currency }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="table-light">
                    <tr><td colspan="5" class="text-end fw-bold">الإجمالي:</td>
                        <td class="text-end fw-bold fs-6">{{ number_format($salesQuotation->total_amount,2) }} {{ $currency }}</td></tr>
                    </tfoot>
                </table>
            </div>

            {{-- Linked Sales Orders --}}
            @if($salesQuotation->salesOrders->count() > 0)
            <h6 class="text-muted mb-2">أوامر البيع المرتبطة</h6>
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                <tr><th>رقم الأمر</th><th>التاريخ</th><th class="text-center">الحالة</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($salesQuotation->salesOrders as $so)
                <tr>
                    <td><code>{{ $so->order_number }}</code></td>
                    <td>{{ $so->order_date->format('Y-m-d') }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ $so->statusColor() }}">{{ $so->statusLabel() }}</span>
                    </td>
                    <td><a href="{{ route('sales-orders.show', $so) }}" class="btn btn-sm btn-info btn-action"><i class="bi bi-eye"></i></a></td>
                </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div></div>
@endsection
