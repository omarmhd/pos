@extends('layouts.app')
@section('title', 'تعديل موظف')
@section('page-title', 'تعديل بيانات الموظف')

@section('content')
<div class="col-lg-9 mx-auto">
    <div class="card">
        <div class="card-header"><i class="bi bi-pencil"></i> تعديل: {{ $employee->name }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('hr.employees.update', $employee) }}">
                @csrf @method('PUT')
                @include('hr.employees._form')
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                    <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
