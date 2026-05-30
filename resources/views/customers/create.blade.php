@extends('layouts.app')
@section('title', 'إضافة عميل')
@section('page-title', 'إضافة عميل جديد')

@section('content')
<div class="col-lg-7 mx-auto">
    <div class="card">
        <div class="card-header"><i class="bi bi-person-plus"></i> بيانات العميل</div>
        <div class="card-body">
            <form method="POST" action="{{ route('customers.store') }}">
                @csrf
                @include('customers._form')
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">حفظ</button>
                    <a href="{{ route('customers.index') }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
