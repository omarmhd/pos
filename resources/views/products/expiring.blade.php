{{-- resources/views/products/expiring.blade.php --}}
@extends('layouts.app')

@section('page-title', 'منتجات قاربت صلاحيتها على الانتهاء')

@section('content')
    <div class="card border-danger">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-calendar-x"></i> منتجات قاربت صلاحيتها على الانتهاء</h5>
            <a href="{{ route('products.index') }}" class="btn btn-light btn-sm">
                <i class="bi bi-arrow-right"></i> رجوع للمنتجات
            </a>
        </div>
        <div class="card-body">
            @if($products->count() > 0)
                <div class="alert alert-danger">
                    <i class="bi bi-calendar-x"></i>
                    <strong>تحذير!</strong> يوجد {{ $products->total() }} منتج ستنتهي صلاحيته خلال 30 يوماً.
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                        <tr>
                            <th>الصورة</th>
                            <th>اسم المنتج</th>
                            <th>الباركود</th>
                            <th>الفئة</th>
                            <th>تاريخ الصلاحية</th>
                            <th>الأيام المتبقية</th>
                            <th>الكمية</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($products as $product)
                            @php
                                $daysLeft = $product->expiry_date->diffInDays(now());
                                $isExpired = $product->expiry_date->isPast();
                                $urgency = $daysLeft <= 7 ? 'danger' : ($daysLeft <= 15 ? 'warning' : 'info');
                            @endphp
                            <tr class="table-{{ $isExpired ? 'danger' : $urgency }}">
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
                                <td><strong>{{ $product->expiry_date->format('Y-m-d') }}</strong></td>
                                <td>
                                    @if($isExpired)
                                        <span class="badge bg-danger">منتهي</span>
                                    @else
                                        <span class="badge bg-{{ $urgency }}">{{ $daysLeft }} يوم</span>
                                    @endif
                                </td>
                                <td>{{ $product->quantity }}</td>
                                <td>
                                    @if($isExpired)
                                        <span class="badge bg-danger">منتهي الصلاحية</span>
                                    @elseif($daysLeft <= 7)
                                        <span class="badge bg-danger">عاجل جداً!</span>
                                    @elseif($daysLeft <= 15)
                                        <span class="badge bg-warning text-dark">عاجل</span>
                                    @else
                                        <span class="badge bg-info">قريب</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('products.show', $product) }}" class="btn btn-sm btn-info btn-action">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-primary btn-action">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $products->links() }}

                <div class="mt-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6><i class="bi bi-info-circle"></i> ملاحظات:</h6>
                            <ul class="mb-0">
                                <li><span class="badge bg-danger">منتهي</span> - انتهت صلاحية المنتج، يجب إزالته</li>
                                <li><span class="badge bg-danger">عاجل جداً</span> - أقل من 7 أيام، تصريف عاجل</li>
                                <li><span class="badge bg-warning text-dark">عاجل</span> - أقل من 15 يوم، عمل خصم</li>
                                <li><span class="badge bg-info">قريب</span> - أقل من 30 يوم، مراقبة</li>
                            </ul>
                        </div>
                    </div>
                </div>

            @else
                <div class="text-center py-5">
                    <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 text-success">ممتاز! 🎉</h4>
                    <p class="text-muted">لا توجد منتجات قاربت صلاحيتها على الانتهاء</p>
                    <a href="{{ route('products.index') }}" class="btn btn-primary mt-3">
                        <i class="bi bi-arrow-right"></i> العودة للمنتجات
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection
