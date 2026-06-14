@extends('layouts.app')
@section('page-title', 'الأدوار والصلاحيات')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-shield-lock"></i> إدارة الأدوار والصلاحيات</h5>
        @can('roles.create')
        <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> إنشاء دور جديد
        </a>
        @endcan
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle dt-table" data-title="الأدوار">
                <thead class="table-light">
                    <tr>
                        <th>اسم الدور</th>
                        <th>الصلاحيات</th>
                        <th>المستخدمون</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($roles as $role)
                <tr>
                    <td>
                        <span class="badge fs-6
                            {{ $role->name === 'admin'          ? 'bg-danger'  :
                              ($role->name === 'manager'        ? 'bg-primary' :
                              ($role->name === 'branch_manager'  ? 'bg-primary' :
                              ($role->name === 'accountant'     ? 'bg-info'    :
                              ($role->name === 'cashier'        ? 'bg-success' : 'bg-secondary')))) }}">
                            {{ $role->name }}
                        </span>
                    </td>
                    <td>
                        @if($role->name === 'admin')
                            <span class="badge bg-danger">جميع الصلاحيات</span>
                        @else
                            <span class="badge bg-light text-dark border">{{ $role->permissions_count }}</span>
                            @if($role->permissions_count > 0)
                                <small class="text-muted ms-1">صلاحية</small>
                            @endif
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ $role->users_count }}</span>
                        @if($role->users_count > 0)
                            <small class="text-muted ms-1">مستخدم</small>
                        @endif
                    </td>
                    <td>
                        @can('roles.edit')
                        <a href="{{ route('roles.edit', $role) }}"
                           class="btn btn-sm btn-outline-primary btn-action" title="تعديل">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @endcan
                        @can('roles.delete')
                        @if($role->name !== 'admin')
                        <form action="{{ route('roles.destroy', $role) }}" method="POST"
                              class="d-inline"
                              onsubmit="return confirm('هل أنت متأكد من حذف الدور {{ $role->name }}؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger btn-action"
                                    title="حذف" {{ $role->users_count > 0 ? 'disabled' : '' }}>
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted">لا توجد أدوار</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Permission legend ─────────────────────────────────────────────────────── --}}
<div class="card mt-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-key"></i> دليل الصلاحيات المتاحة</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach(config('permission_groups') as $moduleKey => $module)
            <div class="col-md-4 col-lg-3">
                <div class="border rounded p-2">
                    <div class="fw-semibold mb-1 small">
                        <i class="bi {{ $module['icon'] }}"></i> {{ $module['label'] }}
                    </div>
                    @foreach($module['permissions'] as $key => $label)
                    <div class="d-flex align-items-center gap-1 small text-muted">
                        <code class="text-primary" style="font-size:0.7rem">{{ $key }}</code>
                        <span>— {{ $label }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
