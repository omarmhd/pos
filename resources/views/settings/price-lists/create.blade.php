@extends('layouts.app')
@section('page-title', 'قائمة أسعار جديدة')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-tags text-info me-2"></i>إضافة قائمة أسعار جديدة</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('price-lists.store') }}" method="POST">
            @csrf
            @include('settings.price-lists._form')
            <button type="submit" class="btn btn-info text-white"><i class="bi bi-save me-1"></i> حفظ</button>
            <a href="{{ route('price-lists.index') }}" class="btn btn-secondary">إلغاء</a>
        </form>
    </div>
</div>
@endsection
