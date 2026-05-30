{{--
  Shared form fields for create and edit.
  $account = null for create, Account instance for edit.
  $parents  = collection of header accounts (passed from controller).
--}}

<div class="mb-3">
    <label class="form-label fw-semibold">كود الحساب <span class="text-danger">*</span></label>
    <input type="text" name="code"
           class="form-control font-monospace @error('code') is-invalid @enderror"
           value="{{ old('code', $account?->code) }}"
           placeholder="مثال: 1010" required>
    <small class="text-muted">أرقام فريدة تحدد موقع الحساب في الشجرة (مثل 1000، 1010، 6100…)</small>
    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">اسم الحساب <span class="text-danger">*</span></label>
    <input type="text" name="name"
           class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $account?->name) }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">نوع الحساب <span class="text-danger">*</span></label>
        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
            @foreach(['asset'=>'أصل','liability'=>'التزام','equity'=>'حقوق ملكية','revenue'=>'إيراد','expense'=>'مصروف'] as $val => $lbl)
            <option value="{{ $val }}" {{ old('type', $account?->type) === $val ? 'selected' : '' }}>
                {{ $lbl }}
            </option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">التصنيف الفرعي</label>
        <select name="sub_type" class="form-select">
            <option value="">— بدون تصنيف —</option>
            <option value="current_asset"       {{ old('sub_type', $account?->sub_type) === 'current_asset'       ? 'selected' : '' }}>أصل متداول</option>
            <option value="fixed_asset"         {{ old('sub_type', $account?->sub_type) === 'fixed_asset'         ? 'selected' : '' }}>أصل ثابت</option>
            <option value="current_liability"   {{ old('sub_type', $account?->sub_type) === 'current_liability'   ? 'selected' : '' }}>التزام متداول</option>
            <option value="long_term_liability" {{ old('sub_type', $account?->sub_type) === 'long_term_liability' ? 'selected' : '' }}>التزام طويل الأجل</option>
        </select>
        <small class="text-muted">يستخدم لتصنيف الحساب في الميزانية العمومية</small>
    </div>
</div>

<div class="mb-3 mt-3">
    <label class="form-label fw-semibold">الحساب الأب (مجموعة)</label>
    <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
        <option value="">— حساب رئيسي (لا يوجد أب) —</option>
        @foreach($parents as $p)
        <option value="{{ $p->id }}"
                {{ old('parent_id', $account?->parent_id) == $p->id ? 'selected' : '' }}>
            {{ $p->code }} — {{ $p->name }}
        </option>
        @endforeach
    </select>
    <small class="text-muted">اتركه فارغاً إذا كان الحساب في أعلى مستوى الشجرة</small>
    @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">ملاحظات</label>
    <textarea name="notes" class="form-control" rows="2"
              placeholder="وصف الغرض من هذا الحساب…">{{ old('notes', $account?->notes) }}</textarea>
</div>

<div class="row g-3">
    <div class="col-auto">
        <div class="form-check mt-1">
            <input class="form-check-input" type="checkbox" name="is_header" id="isHeader" value="1"
                   {{ old('is_header', $account?->is_header) ? 'checked' : '' }}>
            <label class="form-check-label" for="isHeader">
                حساب إجمالي (رأسي)
            </label>
            <small class="d-block text-muted" style="font-size:.72rem">
                الحسابات الرأسية لا تقبل قيوداً مباشرة — تستخدم كمجموعات للحسابات الفرعية
            </small>
        </div>
    </div>
</div>
