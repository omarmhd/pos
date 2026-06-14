
{{-- resources/views/products/create.blade.php --}}
@extends('layouts.app')

@php $mode = $mode ?? 'item'; $isProduct = $mode === 'product'; @endphp
@section('page-title', $isProduct ? 'إضافة منتج مصنّع' : 'إضافة صنف')

@section('content')
    <div class="row">
        <div class="col-lg-11 mx-auto">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        @if($isProduct)
                            <i class="bi bi-diagram-3 text-primary"></i> إضافة منتج مصنّع (تامّ الصنع)
                        @else
                            <i class="bi bi-box text-success"></i> إضافة صنف (بضاعة / مادة خام)
                        @endif
                    </h5>
                    <div class="btn-group btn-group-sm">
                        <a href="{{ route('products.create', ['mode'=>'item']) }}" class="btn btn-outline-success {{ !$isProduct ? 'active' : '' }}">صنف</a>
                        <a href="{{ route('products.create', ['mode'=>'product']) }}" class="btn btn-outline-primary {{ $isProduct ? 'active' : '' }}">منتج مصنّع</a>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم المنتج <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">الباركود <span class="text-danger">*</span></label>
                                <input type="text" name="barcode" class="form-control @error('barcode') is-invalid @enderror"
                                       value="{{ old('barcode') }}" required>
                                @error('barcode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">الفئة <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                                    <option value="">اختر الفئة</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">الوحدة <span class="text-danger">*</span></label>
                                <select name="unit" class="form-select @error('unit') is-invalid @enderror" required>
                                    @foreach(\App\Enums\ProductUnit::cases() as $unit)
                                        <option value="{{ $unit->value }}"
                                            {{ old('unit', 'piece') === $unit->value ? 'selected' : '' }}>
                                            {{ $unit->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-2 d-flex align-items-end pb-1">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="allow_fractions" id="allow_fractions"
                                           value="1" {{ old('allow_fractions') ? 'checked' : '' }}>
                                    <label class="form-check-label small fw-semibold" for="allow_fractions">
                                        <i class="bi bi-speedometer2 text-info"></i> يُباع بالوزن / الكسر
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">سعر الشراء <span class="text-danger">*</span></label>
                                <input type="number" name="cost_price" class="form-control @error('cost_price') is-invalid @enderror"
                                       value="{{ old('cost_price') }}" step="0.01" min="0" required>
                                @error('cost_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">سعر البيع <span class="text-danger">*</span></label>
                                <input type="number" name="selling_price" class="form-control @error('selling_price') is-invalid @enderror"
                                       value="{{ old('selling_price') }}" step="0.01" min="0" required>
                                @error('selling_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">الكمية <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="form-control @error('quantity') is-invalid @enderror"
                                       value="{{ old('quantity', 0) }}" min="0" required>
                                @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">الحد الأدنى للكمية <span class="text-danger">*</span></label>
                                <input type="number" name="min_quantity" class="form-control @error('min_quantity') is-invalid @enderror"
                                       value="{{ old('min_quantity', 10) }}" min="0" required>
                                @error('min_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">تاريخ الصلاحية</label>
                                <input type="date" name="expiry_date" class="form-control @error('expiry_date') is-invalid @enderror"
                                       value="{{ old('expiry_date') }}" min="{{ date('Y-m-d') }}">
                                @error('expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">صورة المنتج</label>
                                <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" accept="image/*">
                                @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">الوصف</label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                          rows="3">{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            @include('products._form_extras', ['mode' => $mode])

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> حفظ
                                </button>
                                <a href="{{ route('products.index') }}" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> إلغاء
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
