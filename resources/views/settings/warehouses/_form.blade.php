{{-- Shared form partial for warehouse create/edit --}}
<div class="row mb-3">
    <div class="col-md-3">
        <label class="form-label">كود المخزن <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
               value="{{ old('code', $warehouse->code ?? '') }}"
               placeholder="مثال: WH-01" maxlength="20" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-5">
        <label class="form-label">اسم المخزن <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $warehouse->name ?? '') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">الفرع
            <small class="text-muted">(اختياري — يمكن ترك المخزن مستقلاً)</small>
        </label>
        <select name="branch_id" class="form-select">
            <option value="">— مستقل / بدون فرع —</option>
            @foreach($branches as $b)
                <option value="{{ $b->id }}"
                    {{ old('branch_id', $warehouse->branch_id ?? '') == $b->id ? 'selected' : '' }}>
                    [{{ $b->code }}] {{ $b->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <label class="form-label">ملاحظات</label>
        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $warehouse->notes ?? '') }}</textarea>
    </div>
    <div class="col-md-4 d-flex align-items-center gap-4 mt-3">
        <div class="form-check form-switch">
            <input type="checkbox" name="is_active" id="wh_active" class="form-check-input" value="1"
                   {{ old('is_active', $warehouse->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="wh_active">نشط</label>
        </div>
        <div class="form-check form-switch">
            <input type="checkbox" name="is_default" id="wh_default" class="form-check-input" value="1"
                   {{ old('is_default', $warehouse->is_default ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="wh_default">
                <i class="bi bi-star-fill text-warning"></i> افتراضي للنظام
            </label>
        </div>
    </div>
</div>
