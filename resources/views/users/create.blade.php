@extends('layouts.app')
@section('page-title', 'إضافة مستخدم جديد')

@section('content')
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> إضافة مستخدم جديد</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('users.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">الاسم <span class="text-danger">*</span></label>
                        <input type="text" name="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                        <input type="email" name="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                        <input type="password" name="password"
                               class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">تأكيد كلمة المرور <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>

                    {{-- ── Roles ── --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            الأدوار <span class="text-danger">*</span>
                        </label>
                        @error('roles')
                            <div class="alert alert-danger py-1 px-2 small mb-2">{{ $message }}</div>
                        @enderror
                        <div class="border rounded p-3 bg-light">
                            <div class="row g-2">
                            @foreach($roles as $role)
                            @php
                                $badgeClass = match($role->name) {
                                    'admin'      => 'bg-danger',
                                    'manager'    => 'bg-primary',
                                    'accountant' => 'bg-info',
                                    'cashier'    => 'bg-success',
                                    default      => 'bg-secondary',
                                };
                            @endphp
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="roles[]"
                                           value="{{ $role->name }}"
                                           id="role_{{ $role->id }}"
                                           {{ in_array($role->name, old('roles', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="role_{{ $role->id }}">
                                        <span class="badge {{ $badgeClass }}">{{ $role->name }}</span>
                                        <small class="text-muted d-block" style="font-size:0.72rem">
                                            {{ $role->permissions()->count() }} صلاحية
                                        </small>
                                    </label>
                                </div>
                            </div>
                            @endforeach
                            </div>
                        </div>
                        <small class="text-muted">يمكن تعيين أكثر من دور للمستخدم</small>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="is_active" id="isActive"
                                   {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActive">الحساب نشط</label>
                        </div>
                    </div>

                    {{-- Organizational Assignment (Branch / Warehouse / POS Terminal) --}}
                    @php $user = new \App\Models\User(); @endphp
                    @include('users._org_assignment')

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> حفظ
                        </button>
                        <a href="{{ route('users.index') }}" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
