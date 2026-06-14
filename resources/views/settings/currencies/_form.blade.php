@php $c = $currency ?? null; @endphp
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">الكود <span class="text-danger">*</span></label>
         <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
             value="{{ old('code', is_object($c) ? $c->code : '') }}" maxlength="10" placeholder="USD" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">الاسم <span class="text-danger">*</span></label>
         <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
             value="{{ old('name', is_object($c) ? $c->name : '') }}" maxlength="50" placeholder="دولار أمريكي" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">الرمز <span class="text-danger">*</span></label>
         <input type="text" name="symbol" class="form-control @error('symbol') is-invalid @enderror"
             value="{{ old('symbol', is_object($c) ? $c->symbol : '') }}" maxlength="10" placeholder="$" required>
        @error('symbol')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">سعر الصرف <span class="text-danger">*</span></label>
         <input type="number" name="exchange_rate" class="form-control @error('exchange_rate') is-invalid @enderror"
             value="{{ old('exchange_rate', is_object($c) ? ($c->exchange_rate ?? 1) : 1) }}" step="0.000001" min="0.000001" required
             {{ (is_object($c) && ($c->is_base ?? false)) ? 'readonly' : '' }}>
        <div class="form-text">كم وحدة من العملة الأساسية يساوي 1 من هذه العملة</div>
        @error('exchange_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 d-flex align-items-end pb-1">
        <div class="form-check form-switch">
                 <input class="form-check-input" type="checkbox" name="is_base" id="is_base" value="1"
                     {{ old('is_base', is_object($c) ? ($c->is_base ?? false) : false) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_base">عملة أساسية</label>
        </div>
    </div>
    <div class="col-md-3 d-flex align-items-end pb-1">
        <div class="form-check form-switch">
                 <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                     {{ old('is_active', is_object($c) ? ($c->is_active ?? true) : true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">نشطة</label>
        </div>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> حفظ</button>
        <a href="{{ route('currencies.index') }}" class="btn btn-secondary"><i class="bi bi-x-circle"></i> إلغاء</a>
    </div>
</div>
