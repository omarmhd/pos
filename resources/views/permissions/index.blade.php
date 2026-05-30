@extends('layouts.app')

@section('content')
<div class="container">
    <h1>إدارة صلاحية العكس</h1>

    <table class="table">
        <thead><tr><th>المستخدم</th><th>البريد</th><th>يمتلك صلاحية العكس</th><th>إجراء</th></tr></thead>
        <tbody>
        @foreach($users as $u)
            <tr>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->hasPermissionTo($permission->name) ? 'نعم' : 'لا' }}</td>
                <td>
                    <form method="post" action="{{ route('permissions.toggle', $u->id) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary">تبديل</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
