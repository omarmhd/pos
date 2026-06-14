@extends('layouts.app')
@section('page-title', 'المنتجات')

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        {{ session('warning') }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-box-seam"></i> قائمة المنتجات</h5>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('products.low-stock') }}" class="btn btn-warning btn-sm">
                <i class="bi bi-exclamation-triangle"></i> منتجات أوشكت على النفاذ
            </a>
            @can('products.create')
            <a href="{{ route('products.import-template') }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-download"></i> تحميل قالب الاستيراد
            </a>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-upload"></i> استيراد CSV
            </button>
            <a href="{{ route('products.create', ['mode'=>'item']) }}" class="btn btn-success btn-sm">
                <i class="bi bi-box"></i> إضافة صنف
            </a>
            <a href="{{ route('products.create', ['mode'=>'product']) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-diagram-3"></i> إضافة منتج مصنّع
            </a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        {{-- فصل الأصناف عن المنتجات المصنّعة (IAS 2) --}}
        @php $c = $class ?? null; @endphp
        <ul class="nav nav-pills mb-3 small">
            <li class="nav-item"><a class="nav-link {{ !$c ? 'active' : '' }}" href="{{ route('products.index') }}">الكل</a></li>
            <li class="nav-item"><a class="nav-link {{ $c==='items' ? 'active' : '' }}" href="{{ route('products.index', ['class'=>'items']) }}"><i class="bi bi-box"></i> الأصناف (بضاعة/مواد خام)</a></li>
            <li class="nav-item"><a class="nav-link {{ $c==='manufactured' ? 'active' : '' }}" href="{{ route('products.index', ['class'=>'manufactured']) }}"><i class="bi bi-diagram-3"></i> المنتجات المصنّعة</a></li>
        </ul>
        <div class="table-responsive">
            <table id="products-table" class="table table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th></th>
                        <th>الاسم</th>
                        <th>الباركود</th>
                        <th>الفئة</th>
                        <th>سعر الشراء</th>
                        <th>سعر البيع</th>
                        <th>المخزون</th>
                        <th>الصلاحية</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

{{-- Import Modal --}}
@can('products.create')
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upload"></i> استيراد منتجات من CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('products.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle"></i>
                        قم بتحميل <a href="{{ route('products.import-template') }}" class="alert-link">قالب CSV</a>
                        أولاً، أدخل بيانات المنتجات، ثم ارفع الملف هنا.<br>
                        <strong>الباركود</strong> يُستخدم كمفتاح — إذا وُجد المنتج يتم تحديثه، وإلا يُضاف.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ملف CSV <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control @error('csv_file') is-invalid @enderror"
                               accept=".csv,.txt" required>
                        @error('csv_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-cloud-upload"></i> استيراد
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@endsection

@section('scripts')
<script>
$(function () {
    $('#products-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('products.index') }}',
            data: function (d) { d.class = '{{ $class ?? '' }}'; }
        },
        order: [[0, 'asc']],
        columns: [
            { data: 'image_html',    name: 'image',         orderable: false, searchable: false },
            { data: 'name',          name: 'name' },
            { data: 'barcode',       name: 'barcode' },
            { data: 'category_name', name: 'category_name', searchable: true },
            { data: 'cost_fmt',      name: 'cost_price',    orderable: true, searchable: false },
            { data: 'price_fmt',     name: 'selling_price', orderable: true, searchable: false },
            { data: 'stock_badge',   name: 'quantity',      orderable: true, searchable: false },
            { data: 'expiry_badge',  name: 'expiry_date',   orderable: true, searchable: false },
            { data: 'action',        name: 'action',        orderable: false, searchable: false },
        ]
    }));
});
</script>
@endsection
