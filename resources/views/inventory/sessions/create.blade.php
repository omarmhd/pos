@extends('layouts.app')
@section('page-title', 'جلسة جرد جديدة')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-6">
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clipboard-check"></i> جلسة جرد جديدة</span>
        <a href="{{ route('inventory.sessions.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('inventory.sessions.store') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label fw-bold">عنوان الجلسة <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control"
                       value="{{ old('title', 'جرد دوري — ' . now()->format('Y/m/d')) }}"
                       placeholder="مثال: جرد شهر يونيو 2026" required>
                @error('title') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            {{-- ✅ REQUIRED: Which warehouse are we physically counting? --}}
            <div class="mb-3">
                <label class="form-label fw-bold">
                    <i class="bi bi-archive text-success me-1"></i>
                    المخزن المُجرَّد <span class="text-danger">*</span>
                </label>
                <select name="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                    <option value="">— اختر المخزن الذي ستجرده —</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}"
                            {{ old('warehouse_id', $defaultWarehouseId) == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                            @if($wh->branch) ({{ $wh->branch->name }}) @endif
                            — [{{ ['floor'=>'معرض/رفوف','main'=>'مخزن رئيسي','returns'=>'مرتجعات'][$wh->type] ?? $wh->type }}]
                            @if($wh->is_default) ⭐ @endif
                        </option>
                    @endforeach
                </select>
                @error('warehouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text text-warning fw-semibold">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    الكمية المرجعية (كمية النظام) ستُحسَّب من هذا المخزن تحديداً — وليس من الإجمالي الكلي.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">تصفية حسب الفئة (اختياري)</label>
                <select name="category_id" class="form-select">
                    <option value="">— جميع المنتجات —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">إذا اخترت فئة، سيتم جرد منتجاتها فقط.</div>
            </div>

            <div class="mb-4">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"
                          placeholder="ملاحظات اختيارية...">{{ old('notes') }}</textarea>
            </div>

            <div class="alert alert-info small mb-3">
                <i class="bi bi-info-circle"></i>
                عند الإنشاء، سيتم تسجيل الكميات الحالية في النظام كـ<strong>«كمية نظام»</strong>.
                ثم تُدخل الكميات الفعلية من الجرد الميداني.
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-play-circle"></i> إنشاء جلسة الجرد
            </button>
        </form>
    </div>
</div>
</div>
</div>
@endsection
