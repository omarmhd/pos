{{-- resources/views/products/low-stock.blade.php --}}
@extends('layouts.app')

@section('page-title', 'منتجات أوشكت على النفاذ')

@section('content')
    <div class="card border-warning">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> منتجات أوشكت على النفاذ</h5>
            <a href="{{ route('products.index') }}" class="btn btn-dark btn-sm">
                <i class="bi bi-arrow-right"></i> رجوع للمنتجات
            </a>
        </div>
        <div class="card-body">
            @if($products->count() > 0)
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>تحذير!</strong> يوجد {{ $products->total() }} منتج أوشك على النفاذ ويحتاج إلى إعادة طلب.
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                        <tr>
                            <th>الصورة</th>
                            <th>اسم المنتج</th>
                            <th>الباركود</th>
                            <th>الفئة</th>
                            <th>الكمية الحالية</th>
                            <th>الحد الأدنى</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($products as $product)
                            <tr class="table-warning">
                                <td>
                                    @if($product->image)
                                        <img src="{{ asset('storage/' . $product->image) }}" width="50" height="50" class="rounded">
                                    @else
                                        <div class="bg-light rounded" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-box-seam"></i>
                                        </div>
                                    @endif
                                </td>
                                <td><strong>{{ $product->name }}</strong></td>
                                <td>{{ $product->barcode }}</td>
                                <td><span class="badge bg-info">{{ $product->category->name }}</span></td>
                                <td>
                                    <span class="badge bg-danger fs-6">{{ $product->quantity }}</span>
                                </td>
                                <td>{{ $product->min_quantity }}</td>
                                <td>
                                    @if($product->quantity == 0)
                                        <span class="badge bg-danger">نفذ من المخزن</span>
                                    @else
                                        <span class="badge bg-warning text-dark">قليل جداً</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('products.show', $product) }}" class="btn btn-sm btn-info btn-action">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-primary btn-action">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="{{ route('purchases.create') }}" class="btn btn-sm btn-success btn-action">
                                        <i class="bi bi-cart-plus"></i> طلب
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $products->links() }}

            @else
                <div class="text-center py-5">
                    <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 text-success">رائع! 🎉</h4>
                    <p class="text-muted">جميع المنتجات متوفرة بكميات كافية</p>
                    <a href="{{ route('products.index') }}" class="btn btn-primary mt-3">
                        <i class="bi bi-arrow-right"></i> العودة للمنتجات
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection



