@extends('layouts.app')

@section('page-title', 'تفاصيل المنتج')

@section('content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-box-seam"></i> تفاصيل المنتج</h5>
                    <div>
                        <a href="{{ route('products.edit', $product) }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-pencil"></i> تعديل
                        </a>
                        <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-right"></i> رجوع
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            @if($product->image)
                                <img src="{{ asset('storage/' . $product->image) }}" class="img-fluid rounded" alt="{{ $product->name }}">
                            @else
                                <div class="bg-light rounded p-5">
                                    <i class="bi bi-box-seam" style="font-size: 5rem; color: #ccc;"></i>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-8">
                            <h3>{{ $product->name }}</h3>
                            <p class="text-muted">{{ $product->description ?? 'لا يوجد وصف' }}</p>

                            <table class="table table-bordered">
                                <tr>
                                    <th style="width: 40%">الباركود:</th>
                                    <td><strong>{{ $product->barcode }}</strong></td>
                                </tr>
                                <tr>
                                    <th>الفئة:</th>
                                    <td><span class="badge bg-info">{{ $product->category->name }}</span></td>
                                </tr>
                                <tr>
                                    <th>الوحدة:</th>
                                    <td>
                                        {{ $product->unit instanceof \App\Enums\ProductUnit ? $product->unit->label() : $product->unit }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>سعر الشراء:</th>
                                    <td>{{ number_format($product->cost_price, 2) }} {{ $currency }}</td>
                                </tr>
                                <tr>
                                    <th>سعر البيع:</th>
                                    <td class="text-success"><strong>{{ number_format($product->selling_price, 2) }} {{ $currency }}</strong></td>
                                </tr>
                                <tr>
                                    <th>هامش الربح:</th>
                                    <td>{{ number_format($product->selling_price - $product->cost_price, 2) }} {{ $currency }}
                                        ({{ number_format((($product->selling_price - $product->cost_price) / $product->cost_price) * 100, 1) }}%)
                                    </td>
                                </tr>
                                <tr>
                                    <th>الكمية المتاحة:</th>
                                    <td>
                                        @if($product->isLowStock())
                                            <span class="badge bg-danger fs-6">{{ $product->quantity }}</span>
                                            <small class="text-danger">(قليل!)</small>
                                        @else
                                            <span class="badge bg-success fs-6">{{ $product->quantity }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>الحد الأدنى:</th>
                                    <td>{{ $product->min_quantity }}</td>
                                </tr>
                                <tr>
                                    <th>تاريخ الصلاحية:</th>
                                    <td>
                                        @if($product->expiry_date)
                                            @if($product->isExpired())
                                                <span class="badge bg-danger">{{ $product->expiry_date->format('Y-m-d') }} (منتهي)</span>
                                            @elseif($product->isExpiringSoon())
                                                <span class="badge bg-warning text-dark">{{ $product->expiry_date->format('Y-m-d') }} (قريب)</span>
                                            @else
                                                <span class="badge bg-success">{{ $product->expiry_date->format('Y-m-d') }}</span>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>تاريخ الإضافة:</th>
                                    <td>{{ $product->created_at->format('Y-m-d H:i') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
