@extends('layouts.app')
@section('page-title', 'تعديل فرع')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-building-fill-gear text-primary me-2"></i>تعديل الفرع — {{ $branch->name }}</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('branches.update', $branch) }}" method="POST">
            @csrf @method('PUT')
            @include('settings.branches._form')
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> حفظ التعديلات
            </button>
            <a href="{{ route('branches.index') }}" class="btn btn-secondary">
                <i class="bi bi-x-circle"></i> إلغاء
            </a>
        </form>
    </div>
</div>
@endsection
