{{-- Shared form partial for branch create/edit --}}
<div class="row mb-3">
    <div class="col-md-3">
        <label class="form-label">كود الفرع <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
               value="{{ old('code', $branch->code ?? '') }}"
               placeholder="مثال: HQ / B01 / WH" maxlength="20" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-5">
        <label class="form-label">اسم الفرع <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $branch->name ?? '') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">نوع الفرع <span class="text-danger">*</span></label>
        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
            @foreach(\App\Models\Branch::$types as $val => $label)
                <option value="{{ $val }}" {{ old('type', $branch->type ?? 'retail') === $val ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label">العنوان</label>
        <input type="text" name="address" class="form-control"
               value="{{ old('address', $branch->address ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">الهاتف</label>
        <input type="text" name="phone" class="form-control"
               value="{{ old('phone', $branch->phone ?? '') }}">
    </div>
    <div class="col-md-3 d-flex align-items-end gap-3 pb-1">
        <div class="form-check form-switch">
            <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1"
                   {{ old('is_active', $branch->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">نشط</label>
        </div>
        <div class="form-check form-switch">
            <input type="checkbox" name="is_default" id="is_default" class="form-check-input" value="1"
                   {{ old('is_default', $branch->is_default ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_default">
                <i class="bi bi-star-fill text-warning"></i> افتراضي
            </label>
        </div>
    </div>
</div>

<div class="mb-4">
    <label class="form-label">ملاحظات</label>
    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $branch->notes ?? '') }}</textarea>
</div>
