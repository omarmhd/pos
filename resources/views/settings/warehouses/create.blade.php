@extends('layouts.app')
@section('page-title', 'مخزن جديد')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-archive text-success me-2"></i>إضافة مخزن جديد</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('warehouses.store') }}" method="POST">
            @csrf
            @include('settings.warehouses._form')
            <button type="submit" class="btn btn-success">
                <i class="bi bi-save me-1"></i> حفظ المخزن
            </button>
            <a href="{{ route('warehouses.index') }}" class="btn btn-secondary">
                <i class="bi bi-x-circle"></i> إلغاء
            </a>
        </form>
    </div>
</div>
@endsection
