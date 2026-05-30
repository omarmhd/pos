@php $emp = $employee ?? null; @endphp

<div class="row g-3">
    {{-- Personal Info --}}
    <div class="col-12"><h6 class="text-muted border-bottom pb-1">البيانات الشخصية</h6></div>

    <div class="col-md-6">
        <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $emp?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">الرقم الوطني / الهوية</label>
        <input type="text" name="national_id" class="form-control @error('national_id') is-invalid @enderror"
               value="{{ old('national_id', $emp?->national_id) }}">
        @error('national_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">الهاتف</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone', $emp?->phone) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">البريد الإلكتروني</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $emp?->email) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">رقم الحساب البنكي</label>
        <input type="text" name="bank_account" class="form-control" value="{{ old('bank_account', $emp?->bank_account) }}">
    </div>

    <div class="col-12">
        <label class="form-label">العنوان</label>
        <textarea name="address" class="form-control" rows="2">{{ old('address', $emp?->address) }}</textarea>
    </div>

    {{-- Employment --}}
    <div class="col-12 mt-2"><h6 class="text-muted border-bottom pb-1">بيانات التوظيف</h6></div>

    <div class="col-md-4">
        <label class="form-label">القسم</label>
        <input type="text" name="department" class="form-control" value="{{ old('department', $emp?->department) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">المسمى الوظيفي</label>
        <input type="text" name="job_title" class="form-control" value="{{ old('job_title', $emp?->job_title) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">تاريخ التعيين <span class="text-danger">*</span></label>
        <input type="date" name="hire_date" class="form-control @error('hire_date') is-invalid @enderror"
               value="{{ old('hire_date', $emp?->hire_date?->toDateString()) }}" required>
        @error('hire_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @if($emp)
    <div class="col-md-4">
        <label class="form-label">تاريخ الإنهاء</label>
        <input type="date" name="termination_date" class="form-control"
               value="{{ old('termination_date', $emp->termination_date?->toDateString()) }}">
    </div>
    @endif

    <div class="col-md-4">
        <label class="form-label">نوع التوظيف</label>
        <select name="employment_type" class="form-select">
            @foreach(['full_time'=>'دوام كامل','part_time'=>'دوام جزئي','contract'=>'عقد'] as $v=>$l)
            <option value="{{ $v }}" {{ old('employment_type', $emp?->employment_type) == $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">طريقة احتساب الراتب</label>
        <select name="pay_type" class="form-select">
            @foreach(['monthly'=>'شهري','daily'=>'يومي','hourly'=>'بالساعة'] as $v=>$l)
            <option value="{{ $v }}" {{ old('pay_type', $emp?->pay_type ?? 'monthly') == $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
        </select>
    </div>

    {{-- Salary --}}
    <div class="col-12 mt-2"><h6 class="text-muted border-bottom pb-1">الراتب والبدلات</h6></div>

    <div class="col-md-3">
        <label class="form-label">الراتب الأساسي <span class="text-danger">*</span></label>
        <input type="number" name="base_salary" class="form-control @error('base_salary') is-invalid @enderror"
               value="{{ old('base_salary', $emp?->base_salary ?? 0) }}" min="0" step="0.01" required>
        @error('base_salary')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">بدل السكن</label>
        <input type="number" name="housing_allowance" class="form-control"
               value="{{ old('housing_allowance', $emp?->housing_allowance ?? 0) }}" min="0" step="0.01">
    </div>

    <div class="col-md-3">
        <label class="form-label">بدل المواصلات</label>
        <input type="number" name="transport_allowance" class="form-control"
               value="{{ old('transport_allowance', $emp?->transport_allowance ?? 0) }}" min="0" step="0.01">
    </div>

    <div class="col-md-3">
        <label class="form-label">بدلات أخرى</label>
        <input type="number" name="other_allowances" class="form-control"
               value="{{ old('other_allowances', $emp?->other_allowances ?? 0) }}" min="0" step="0.01">
    </div>

    @if($emp)
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="empActive"
                   {{ old('is_active', $emp->is_active) ? 'checked' : '' }}>
            <label class="form-check-label" for="empActive">موظف نشط</label>
        </div>
    </div>
    @endif

    <div class="col-12">
        <label class="form-label">ملاحظات</label>
        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $emp?->notes) }}</textarea>
    </div>
</div>
