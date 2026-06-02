{{-- Shared form partial for price list create/edit --}}
<div class="row mb-3">
    <div class="col-md-3">
        <label class="form-label">الكود <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
               value="{{ old('code', $priceList->code ?? '') }}"
               placeholder="RETAIL / WHOLESALE / VIP" maxlength="20" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-5">
        <label class="form-label">اسم القائمة <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $priceList->name ?? '') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">نوع القائمة <span class="text-danger">*</span></label>
        <select name="type" class="form-select" required>
            @foreach(\App\Models\PriceList::$types as $val => $label)
                <option value="{{ $val }}"
                    {{ old('type', $priceList->type ?? 'retail') === $val ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">الوصف</label>
    <textarea name="description" class="form-control" rows="2"
              placeholder="مثال: سعر الجملة للموزعين بحد أدنى 10 قطع">{{ old('description', $priceList->description ?? '') }}</textarea>
</div>

<div class="d-flex gap-4 mb-4">
    <div class="form-check form-switch">
        <input type="checkbox" name="is_active" id="pl_active" class="form-check-input" value="1"
               {{ old('is_active', $priceList->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="pl_active">نشطة</label>
    </div>
    <div class="form-check form-switch">
        <input type="checkbox" name="is_default" id="pl_default" class="form-check-input" value="1"
               {{ old('is_default', $priceList->is_default ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="pl_default">
            <i class="bi bi-star-fill text-warning"></i> القائمة الافتراضية للنظام
        </label>
    </div>
</div>
