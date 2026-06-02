@extends('layouts.app')
@section('page-title', 'قوائم الأسعار')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-tags-fill text-info me-2"></i>قوائم الأسعار (جملة / تجزئة)</h5>
        @can('price_lists.manage')
        <a href="{{ route('price-lists.create') }}" class="btn btn-info btn-sm text-white">
            <i class="bi bi-plus-circle me-1"></i> قائمة جديدة
        </a>
        @endcan
    </div>
    <div class="card-body">

        <div class="alert alert-light border mb-3 small">
            <i class="bi bi-info-circle me-1"></i>
            <strong>كيف يعمل التسعير:</strong>
            سعر العميل (إذا له قائمة محددة) ← سعر الفرع (قائمة الفرع) ← القائمة الافتراضية ← سعر المنتج الأساسي.
            الفرع الواحد يخدم عملاء جملة وتجزئة في آنٍ — القرار على مستوى العميل.
        </div>

        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%">
                <thead class="table-light">
                <tr>
                    <th>الكود</th>
                    <th>القائمة</th>
                    <th>النوع</th>
                    <th class="text-center">أصناف مخصصة</th>
                    <th class="text-center">عملاء مرتبطون</th>
                    <th class="text-center">فروع مرتبطة</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center">افتراضي</th>
                    <th class="text-center">إجراءات</th>
                </tr>
                </thead>
                <tbody>
                @foreach($priceLists as $pl)
                <tr>
                    <td><code>{{ $pl->code }}</code></td>
                    <td class="fw-semibold">{{ $pl->name }}</td>
                    <td>
                        <span class="badge bg-{{ match($pl->type) {
                            'retail'    => 'primary',
                            'wholesale' => 'warning text-dark',
                            'vip'       => 'purple',
                            'staff'     => 'secondary',
                            default     => 'light text-dark',
                        } }}">{{ $pl->typeLabel() }}</span>
                    </td>
                    <td class="text-center">
                        <a href="{{ route('price-lists.products', $pl) }}" class="badge bg-info text-dark">
                            {{ $pl->product_prices_count }} صنف
                        </a>
                    </td>
                    <td class="text-center text-muted">{{ $pl->customers_count }}</td>
                    <td class="text-center text-muted">{{ $pl->branches_count }}</td>
                    <td class="text-center">
                        @if($pl->is_active)
                            <span class="badge bg-success">نشطة</span>
                        @else
                            <span class="badge bg-secondary">موقوفة</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($pl->is_default)
                            <i class="bi bi-star-fill text-warning" title="القائمة الافتراضية"></i>
                        @endif
                    </td>
                    <td class="text-center">
                        <a href="{{ route('price-lists.products', $pl) }}"
                           class="btn btn-sm btn-info btn-action" title="تحديد أسعار الأصناف">
                            <i class="bi bi-currency-dollar"></i>
                        </a>
                        @can('price_lists.manage')
                        <a href="{{ route('price-lists.edit', $pl) }}" class="btn btn-sm btn-primary btn-action">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @if(!$pl->is_default)
                        <form action="{{ route('price-lists.destroy', $pl) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('حذف القائمة؟')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger btn-action"><i class="bi bi-trash"></i></button>
                        </form>
                        @endif
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
