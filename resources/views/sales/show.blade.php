{{-- resources/views/sales/show.blade.php --}}
@extends('layouts.app')

@section('page-title', 'تفاصيل الفاتورة')

@section('content')
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-receipt"></i> فاتورة: {{ $sale->invoice_number }}</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('pos.receipt', $sale) }}" class="btn btn-primary btn-sm" target="_blank">
                            <i class="bi bi-printer"></i> طباعة
                        </a>
                        <a href="{{ route('sales.pdf', $sale) }}" class="btn btn-danger btn-sm" target="_blank">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </a>
                        @if(auth()->user()->hasRole('admin') && $sale->is_posted && !$sale->is_reversed)
                        <a href="{{ route('reversals.create', ['original_type' => \App\Models\Sale::class, 'original_id' => $sale->id]) }}"
                           class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-arrow-counterclockwise"></i> قيد عكسي
                        </a>
                        @endif
                        @if($sale->is_reversed)
                        <span class="badge bg-warning text-dark align-self-center">مُعكوسة</span>
                        @endif
                        <a href="{{ route('sales.index') }}" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-right"></i> رجوع
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>معلومات الفاتورة:</h6>
                            <p class="mb-1"><strong>رقم الفاتورة:</strong> {{ $sale->invoice_number }}</p>
                            <p class="mb-1"><strong>التاريخ:</strong> {{ $sale->created_at->format('Y-m-d H:i:s') }}</p>
                            <p class="mb-1"><strong>الكاشير:</strong> {{ $sale->user->name }}</p>
                            <p class="mb-0">
                                <strong>طريقة الدفع:</strong>
                                @if($sale->payment_method == 'cash')
                                    <span class="badge bg-success">نقدي</span>
                                @elseif($sale->payment_method == 'card')
                                    <span class="badge bg-primary">بطاقة</span>
                                @else
                                    <span class="badge bg-info">محفظة إلكترونية</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="text-muted mb-3">ملخص الفاتورة</h6>
                                    <p class="mb-2">
                                        <span>المجموع الفرعي:</span>
                                        <strong>{{ number_format($sale->subtotal, 2) }} {{ $currency }}</strong>
                                    </p>
                                    @if($sale->discount > 0)
                                        <p class="mb-2 text-danger">
                                            <span>الخصم:</span>
                                            <strong>- {{ number_format($sale->discount, 2) }} {{ $currency }}</strong>
                                        </p>
                                    @endif
                                    <hr>
                                    <p class="mb-0">
                                        <span class="fs-5">الإجمالي:</span>
                                        <strong class="fs-4 text-success">{{ number_format($sale->total_amount, 2) }} {{ $currency }}</strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="mb-3">المنتجات:</h6>
                    <div class="table-responsive">
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
                            @foreach($sale->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item->product->name }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ number_format($item->unit_price, 2) }} {{ $currency }}</td>
                                    <td><strong>{{ number_format($item->total_price, 2) }} {{ $currency }}</strong></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6 offset-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>المدفوع:</span>
                                        <strong>{{ number_format($sale->paid_amount, 2) }} {{ $currency }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>الباقي:</span>
                                        <strong class="text-success">{{ number_format($sale->change_amount, 2) }} {{ $currency }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
