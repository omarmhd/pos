@extends('layouts.app')
@section('page-title', 'تعديل مخزن')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-archive-fill text-success me-2"></i>تعديل المخزن — {{ $warehouse->name }}</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('warehouses.update', $warehouse) }}" method="POST">
            @csrf @method('PUT')
            @include('settings.warehouses._form')
            <button type="submit" class="btn btn-success">
                <i class="bi bi-save me-1"></i> حفظ التعديلات
            </button>
            <a href="{{ route('warehouses.index') }}" class="btn btn-secondary">
                <i class="bi bi-x-circle"></i> إلغاء
            </a>
        </form>
    </div>
</div>
@endsection
