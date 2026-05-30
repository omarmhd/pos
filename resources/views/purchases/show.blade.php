@extends('layouts.app')
@section('page-title', 'تفاصيل فاتورة الشراء')

@section('content')
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-bag-plus"></i> فاتورة شراء: {{ $purchase->invoice_number }}</h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('purchases.pdf', $purchase) }}"
                       class="btn btn-outline-danger btn-sm" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> PDF
                    </a>
                    <a href="{{ route('purchases.index') }}" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-right"></i> رجوع
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>معلومات المورد:</h6>
                        <p class="mb-1"><strong>الاسم:</strong> {{ $purchase->supplier->name }}</p>
                        <p class="mb-1"><strong>الشركة:</strong> {{ $purchase->supplier->company ?? '-' }}</p>
                        <p class="mb-1"><strong>الهاتف:</strong> {{ $purchase->supplier->phone }}</p>
                        <p class="mb-0"><strong>البريد:</strong> {{ $purchase->supplier->email ?? '-' }}</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="mb-1"><strong>رقم الفاتورة:</strong> {{ $purchase->invoice_number }}</p>
                        <p class="mb-1"><strong>التاريخ:</strong> {{ $purchase->created_at->format('Y-m-d H:i') }}</p>
                        <p class="mb-1"><strong>المستخدم:</strong> {{ $purchase->user->name }}</p>
                        <p class="mb-0">
                            <strong>حالة الدفع:</strong>
                            @if($purchase->payment_status == 'paid')
                                <span class="badge bg-success">مدفوع</span>
                            @elseif($purchase->payment_status == 'partial')
                                <span class="badge bg-warning">جزئي</span>
                            @else
                                <span class="badge bg-danger">غير مدفوع</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>المنتج</th>
                            <th>الكمية</th>
                            <th>سعر الوحدة</th>
                            <th>المجموع</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($purchase->items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->product->name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->unit_price, 2) }} {{ $currency }}</td>
                            <td><strong>{{ number_format($item->total_price, 2) }} {{ $currency }}</strong></td>
                        </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end"><strong>الإجمالي:</strong></td>
                            <td><strong>{{ number_format($purchase->total_amount, 2) }} {{ $currency }}</strong></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end"><strong>المدفوع:</strong></td>
                            <td><strong>{{ number_format($purchase->paid_amount, 2) }} {{ $currency }}</strong></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end"><strong>المتبقي:</strong></td>
                            <td><strong class="text-danger">{{ number_format($purchase->remainingAmount(), 2) }} {{ $currency }}</strong></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>

                @if($purchase->notes)
                <div class="card bg-light">
                    <div class="card-body">
                        <h6><i class="bi bi-sticky"></i> ملاحظات:</h6>
                        <p class="mb-0">{{ $purchase->notes }}</p>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
