@php $cat = $fixedAssetCategory ?? null; @endphp
<div class="row mb-3">
    <div class="col-md-3">
        <label class="form-label">الكود <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
               value="{{ old('code', $cat?->code) }}" required maxlength="30">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-5">
        <label class="form-label">اسم الفئة <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $cat?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">طريقة الاستهلاك <span class="text-danger">*</span></label>
        <select name="depreciation_method" class="form-select" required>
            @foreach(['straight_line' => 'قسط ثابت', 'declining_balance' => 'قسط متناقص'] as $val => $label)
                <option value="{{ $val }}" {{ old('depreciation_method', $cat?->depreciation_method) === $val ? 'selected':'' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">العمر الإنتاجي (شهر) <span class="text-danger">*</span></label>
        <input type="number" name="useful_life_months" class="form-control"
               value="{{ old('useful_life_months', $cat?->useful_life_months ?? 60) }}" min="1" required>
    </div>
</div>
<div class="row mb-4">
    <div class="col-md-4">
        <label class="form-label">حساب الأصل <span class="text-danger">*</span></label>
        <select name="asset_account_id" class="form-select @error('asset_account_id') is-invalid @enderror" required>
            <option value="">اختر</option>
            @foreach($assetAccounts as $acc)
                <option value="{{ $acc->id }}" {{ old('asset_account_id', $cat?->asset_account_id) == $acc->id ? 'selected':'' }}>
                    {{ $acc->code }} — {{ $acc->name }}
                </option>
            @endforeach
        </select>
        @error('asset_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">حساب مجمع الاستهلاك <span class="text-danger">*</span></label>
        <select name="accumulated_dep_account_id" class="form-select @error('accumulated_dep_account_id') is-invalid @enderror" required>
            <option value="">اختر</option>
            @foreach($assetAccounts as $acc)
                <option value="{{ $acc->id }}" {{ old('accumulated_dep_account_id', $cat?->accumulated_dep_account_id) == $acc->id ? 'selected':'' }}>
                    {{ $acc->code }} — {{ $acc->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">حساب مصروف الاستهلاك <span class="text-danger">*</span></label>
        <select name="depreciation_expense_account_id" class="form-select @error('depreciation_expense_account_id') is-invalid @enderror" required>
            <option value="">اختر</option>
            @foreach($expenseAccounts as $acc)
                <option value="{{ $acc->id }}" {{ old('depreciation_expense_account_id', $cat?->depreciation_expense_account_id) == $acc->id ? 'selected':'' }}>
                    {{ $acc->code }} — {{ $acc->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>
