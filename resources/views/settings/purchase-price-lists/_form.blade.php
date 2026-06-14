@php $pl = $purchasePriceList ?? null; @endphp
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">الكود <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
               value="{{ old('code', $pl->code ?? '') }}" maxlength="20" placeholder="PPL-1" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-8">
        <label class="form-label">الاسم <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $pl->name ?? '') }}" maxlength="100" placeholder="موردو الجملة" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label">الوصف</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', $pl->description ?? '') }}</textarea>
    </div>
    <div class="col-md-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_default" id="ppl_default" value="1"
                   {{ old('is_default', $pl->is_default ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="ppl_default">افتراضية</label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="ppl_active" value="1"
                   {{ old('is_active', $pl->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="ppl_active">نشطة</label>
        </div>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> حفظ</button>
        <a href="{{ route('purchase-price-lists.index') }}" class="btn btn-secondary"><i class="bi bi-x-circle"></i> إلغاء</a>
    </div>
</div>
