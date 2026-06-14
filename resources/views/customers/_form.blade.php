@php $customer = $customer ?? null; @endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">الاسم <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $customer?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">الهاتف</label>
        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
               value="{{ old('phone', $customer?->phone) }}">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">البريد الإلكتروني</label>
        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $customer?->email) }}">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">الرقم الضريبي / مشتغل مرخص</label>
        <input type="text" name="tax_number" class="form-control @error('tax_number') is-invalid @enderror"
               value="{{ old('tax_number', $customer?->tax_number) }}" maxlength="50"
               placeholder="رقم المشتغل المرخص للعميل">
        @error('tax_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">حد الائتمان (0 = بلا حد)</label>
        <input type="number" name="credit_limit" class="form-control @error('credit_limit') is-invalid @enderror"
               value="{{ old('credit_limit', $customer?->credit_limit ?? 0) }}" min="0" step="0.01">
        @error('credit_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">
            <i class="bi bi-tags text-info me-1"></i>قائمة الأسعار
            <small class="text-muted">(جملة / تجزئة)</small>
        </label>
        <select name="price_list_id" class="form-select">
            <option value="">— القائمة الافتراضية للنظام —</option>
            @foreach(\App\Models\PriceList::where('is_active', true)->orderBy('name')->get() as $pl)
                <option value="{{ $pl->id }}"
                    {{ old('price_list_id', $customer?->price_list_id ?? '') == $pl->id ? 'selected' : '' }}>
                    {{ $pl->name }} ({{ $pl->typeLabel() }})
                </option>
            @endforeach
        </select>
        <div class="form-text">يُحدد سعر البيع في POS لهذا العميل</div>
    </div>

    <div class="col-12">
        <label class="form-label">العنوان</label>
        <textarea name="address" class="form-control" rows="2">{{ old('address', $customer?->address) }}</textarea>
    </div>

    <div class="col-12">
        <label class="form-label">ملاحظات</label>
        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $customer?->notes) }}</textarea>
    </div>

    @if($customer)
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                   {{ old('is_active', $customer->is_active) ? 'checked' : '' }}>
            <label class="form-check-label" for="isActive">حساب نشط</label>
        </div>
    </div>
    @endif
</div>
