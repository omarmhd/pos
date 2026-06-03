@php $terminal = $posTerminal ?? null; @endphp
<div class="row mb-3">
    <div class="col-md-3">
        <label class="form-label">كود نقطة البيع <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
               value="{{ old('code', $terminal?->code) }}" placeholder="POS-01" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-5">
        <label class="form-label">الاسم <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $terminal?->name) }}"
               placeholder="كاشير 1 — معرض الطابق الأرضي" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">الفرع <span class="text-danger">*</span></label>
        <select name="branch_id" class="form-select" required>
            <option value="">اختر الفرع</option>
            @foreach($branches as $b)
                <option value="{{ $b->id }}" {{ old('branch_id', $terminal?->branch_id) == $b->id ? 'selected' : '' }}>
                    {{ $b->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <label class="form-label">
            <i class="bi bi-archive text-success me-1"></i>
            المخزن المُستخدَم للخصم <span class="text-danger">*</span>
            <small class="text-muted">(من أين تُخصَّم الكمية عند البيع؟)</small>
        </label>
        <select name="warehouse_id" class="form-select" required>
            <option value="">اختر المخزن</option>
            @foreach($warehouses as $wh)
                <option value="{{ $wh->id }}"
                    data-type="{{ $wh->type }}"
                    {{ old('warehouse_id', $terminal?->warehouse_id) == $wh->id ? 'selected' : '' }}>
                    {{ $wh->name }}
                    @if($wh->branch) ({{ $wh->branch->name }}) @endif
                    — [{{ ['floor'=>'معرض/رف','main'=>'مخزن رئيسي','returns'=>'مرتجعات','transit'=>'عبور'][$wh->type] ?? $wh->type }}]
                </option>
            @endforeach
        </select>
        <div class="form-text">
            <span class="text-success">✓ للمعارض والرفوف</span>: اختر مخزن نوع "floor/معرض" &nbsp;|&nbsp;
            <span class="text-primary">✓ للجملة</span>: اختر مخزن نوع "main/رئيسي"
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label">قائمة أسعار خاصة</label>
        <select name="price_list_id" class="form-select">
            <option value="">— سعر العميل أو الافتراضي —</option>
            @foreach($priceLists as $pl)
                <option value="{{ $pl->id }}" {{ old('price_list_id', $terminal?->price_list_id) == $pl->id ? 'selected' : '' }}>
                    {{ $pl->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 d-flex align-items-center mt-3">
        <div class="form-check form-switch">
            <input type="checkbox" name="is_active" id="pt_active" class="form-check-input" value="1"
                   {{ old('is_active', $terminal?->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="pt_active">نشطة</label>
        </div>
    </div>
</div>

<div class="mb-4">
    <label class="form-label">ملاحظات</label>
    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $terminal?->notes) }}</textarea>
</div>
