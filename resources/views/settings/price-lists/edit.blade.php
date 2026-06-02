@extends('layouts.app')
@section('page-title', 'تعديل قائمة أسعار')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-tags-fill text-info me-2"></i>تعديل القائمة — {{ $priceList->name }}</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('price-lists.update', $priceList) }}" method="POST">
            @csrf @method('PUT')
            @include('settings.price-lists._form')
            <button type="submit" class="btn btn-info text-white"><i class="bi bi-save me-1"></i> حفظ</button>
            <a href="{{ route('price-lists.index') }}" class="btn btn-secondary">إلغاء</a>
        </form>
    </div>
</div>
@endsection
