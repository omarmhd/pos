@extends('layouts.app')
@section('page-title', 'فئة أصول جديدة')
@section('content')
<div class="card">
    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-tags text-primary me-2"></i>إضافة فئة أصول ثابتة</h5></div>
    <div class="card-body">
        <form action="{{ route('fixed-asset-categories.store') }}" method="POST">
            @csrf
            @include('fixed-assets.categories._form')
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> حفظ</button>
            <a href="{{ route('fixed-asset-categories.index') }}" class="btn btn-secondary">إلغاء</a>
        </form>
    </div>
</div>
@endsection
