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
        <label class="form-label">حد الائتمان (0 = بلا حد)</label>
        <input type="number" name="credit_limit" class="form-control @error('credit_limit') is-invalid @enderror"
               value="{{ old('credit_limit', $customer?->credit_limit ?? 0) }}" min="0" step="0.01">
        @error('credit_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
