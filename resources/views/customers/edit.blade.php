@extends('layouts.app')
@section('title', 'تعديل عميل')
@section('page-title', 'تعديل بيانات العميل')

@section('content')
<div class="col-lg-7 mx-auto">
    <div class="card">
        <div class="card-header"><i class="bi bi-pencil"></i> تعديل: {{ $customer->name }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('customers.update', $customer) }}">
                @csrf @method('PUT')
                @include('customers._form')
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
