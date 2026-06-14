@extends('layouts.app')

@section('page-title', 'قوائم أسعار الشراء')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-tags"></i> قوائم أسعار الشراء (فئات الموردين)</h5>
        @can('purchase_price_lists.manage')
        <a href="{{ route('purchase-price-lists.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> قائمة جديدة
        </a>
        @endcan
    </div>
    <div class="card-body">
        <div class="alert alert-info py-2 small">
            <i class="bi bi-info-circle"></i>
            اربط كل مورد بقائمة أسعار شراء من بطاقة المورد؛ عند إدخال فاتورة شراء لذلك المورد
            تُقترح التكلفة من قائمته تلقائياً.
        </div>
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="قوائم أسعار الشراء">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>الاسم</th>
                        <th>أصناف مسعّرة</th>
                        <th>موردون مرتبطون</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($priceLists as $pl)
                    <tr>
                        <td><strong>{{ $pl->code }}</strong> @if($pl->is_default)<span class="badge bg-primary">افتراضية</span>@endif</td>
                        <td>{{ $pl->name }}</td>
                        <td>{{ $pl->product_prices_count }}</td>
                        <td>{{ $pl->suppliers_count }}</td>
                        <td>
                            @if($pl->is_active)<span class="badge bg-success">نشطة</span>
                            @else<span class="badge bg-secondary">موقوفة</span>@endif
                        </td>
                        <td>
                            <a href="{{ route('purchase-price-lists.products', $pl) }}" class="btn btn-sm btn-info" title="تسعير الأصناف">
                                <i class="bi bi-currency-dollar"></i> الأسعار
                            </a>
                            @can('purchase_price_lists.manage')
                            <a href="{{ route('purchase-price-lists.edit', $pl) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('purchase-price-lists.destroy', $pl) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('حذف القائمة؟')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
