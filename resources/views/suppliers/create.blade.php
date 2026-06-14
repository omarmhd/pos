@extends('layouts.app')

@section('page-title', 'إضافة مورد جديد')

@section('content')
    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> إضافة مورد جديد</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('suppliers.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">اسم المورد <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الشركة</label>
                            <input type="text" name="company" class="form-control @error('company') is-invalid @enderror"
                                   value="{{ old('company') }}">
                            @error('company')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">رقم التسجيل الضريبي (TRN)</label>
                            <input type="text" name="tax_number" class="form-control @error('tax_number') is-invalid @enderror"
                                   value="{{ old('tax_number') }}">
                            @error('tax_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">قائمة أسعار الشراء</label>
                            <select name="purchase_price_list_id" class="form-select">
                                <option value="">— بدون —</option>
                                @foreach($purchasePriceLists as $pl)
                                    <option value="{{ $pl->id }}" {{ old('purchase_price_list_id') == $pl->id ? 'selected' : '' }}>
                                        {{ $pl->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">تُقترح أسعار هذه القائمة تلقائياً في فواتير شراء هذا المورد</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">رقم الهاتف <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone') }}" required>
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">العنوان</label>
                            <textarea name="address" class="form-control @error('address') is-invalid @enderror"
                                      rows="3">{{ old('address') }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> حفظ
                        </button>
                        <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> إلغاء
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
