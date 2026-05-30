@extends('layouts.app')
@section('title', 'إضافة موظف')
@section('page-title', 'إضافة موظف جديد')

@section('content')
<div class="col-lg-9 mx-auto">
    <div class="card">
        <div class="card-header"><i class="bi bi-person-plus"></i> بيانات الموظف الجديد</div>
        <div class="card-body">
            <form method="POST" action="{{ route('hr.employees.store') }}">
                @csrf
                @include('hr.employees._form')
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">حفظ الموظف</button>
                    <a href="{{ route('hr.employees.index') }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
