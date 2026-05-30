{{-- resources/views/users/index.blade.php --}}
@extends('layouts.app')

@section('page-title', 'إدارة المستخدمين')

@section('content')
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-people"></i> قائمة المستخدمين</h5>
            <a href="{{ route('users.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> إضافة مستخدم جديد
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover dt-table" style="width:100%">
                    <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>البريد الإلكتروني</th>
                        <th>الصلاحية</th>
                        <th>الحالة</th>
                        <th>تاريخ التسجيل</th>
                        <th>إجراءات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>
                                <strong>{{ $user->name }}</strong>
                                @if($user->id === auth()->id())
                                    <span class="badge bg-info">أنت</span>
                                @endif
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                    @php $r = $user->getRoleNames()->first() ?? $user->role; @endphp
                                    @if($user->hasRole('admin'))
                                        <span class="badge bg-danger">مدير</span>
                                    @elseif($user->hasRole('manager'))
                                        <span class="badge bg-warning">مشرف</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $r }}</span>
                                    @endif
                            </td>
                            <td>
                                @if($user->is_active)
                                    <span class="badge bg-success">نشط</span>
                                @else
                                    <span class="badge bg-secondary">معطل</span>
                                @endif
                            </td>
                            <td>{{ $user->created_at->format('Y-m-d') }}</td>
                            <td>
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-primary btn-action">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if($user->id !== auth()->id())
                                    <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger btn-action"
                                                onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
