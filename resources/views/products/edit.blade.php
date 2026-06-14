@extends('layouts.app')

@section('page-title', 'تعديل المنتج')

@section('content')
    <div class="row">
        <div class="col-lg-11 mx-auto">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-pencil"></i> تعديل المنتج: {{ $product->name }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم المنتج <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $product->name) }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">الباركود <span class="text-danger">*</span></label>
                                <input type="text" name="barcode" class="form-control @error('barcode') is-invalid @enderror"
                                       value="{{ old('barcode', $product->barcode) }}" required>
                                @error('barcode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">الفئة <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                                    <option value="">اختر الفئة</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}"
                                            {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
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
                                            {{ old('unit', $product->unit instanceof \App\Enums\ProductUnit ? $product->unit->value : $product->unit) === $unit->value ? 'selected' : '' }}>
                                            {{ $unit->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-2 d-flex align-items-end pb-1">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="allow_fractions" id="allow_fractions"
                                           value="1" {{ old('allow_fractions', $product->allow_fractions) ? 'checked' : '' }}>
                                    <label class="form-check-label small fw-semibold" for="allow_fractions">
                                        <i class="bi bi-speedometer2 text-info"></i> يُباع بالوزن / الكسر
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">سعر الشراء <span class="text-danger">*</span></label>
                                <input type="number" name="cost_price" class="form-control @error('cost_price') is-invalid @enderror"
                                       value="{{ old('cost_price', $product->cost_price) }}" step="0.01" min="0" required>
                                @error('cost_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">سعر البيع <span class="text-danger">*</span></label>
                                <input type="number" name="selling_price" class="form-control @error('selling_price') is-invalid @enderror"
                                       value="{{ old('selling_price', $product->selling_price) }}" step="0.01" min="0" required>
                                @error('selling_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">الكمية</label>
                                <input type="number" name="quantity" class="form-control"
                                       value="{{ old('quantity', $product->quantity) }}" min="0" readonly>
                                <div class="form-text">الرصيد يُعدَّل عبر تسويات الجرد أو المشتريات (سلامة محاسبية)</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">الحد الأدنى للكمية <span class="text-danger">*</span></label>
                                <input type="number" name="min_quantity" class="form-control @error('min_quantity') is-invalid @enderror"
                                       value="{{ old('min_quantity', $product->min_quantity) }}" min="0" required>
                                @error('min_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">تاريخ الصلاحية</label>
                                <input type="date" name="expiry_date" class="form-control @error('expiry_date') is-invalid @enderror"
                                       value="{{ old('expiry_date', $product->expiry_date ? $product->expiry_date->format('Y-m-d') : '') }}"
                                       min="{{ date('Y-m-d') }}">
                                @error('expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">صورة المنتج</label>
                                <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" accept="image/*">
                                @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if($product->image)
                                    <small class="text-muted">الصورة الحالية موجودة</small>
                                @endif
                            </div>

                            <div class="col-12">
                                <label class="form-label">الوصف</label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                          rows="3">{{ old('description', $product->description) }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            @include('products._form_extras')

                            @if($product->image)
                                <div class="col-12">
                                    <label class="form-label">الصورة الحالية:</label>
                                    <div>
                                        <img src="{{ asset('storage/' . $product->image) }}" width="150" class="rounded">
                                    </div>
                                </div>
                            @endif

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> حفظ التعديلات
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
