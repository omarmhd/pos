@extends('layouts.app')
@section('page-title', 'نقطة بيع جديدة')
@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-cash-register text-primary me-2"></i>إضافة نقطة بيع جديدة</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('pos-terminals.store') }}" method="POST">
            @csrf
            @include('settings.pos-terminals._form')
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> حفظ</button>
            <a href="{{ route('pos-terminals.index') }}" class="btn btn-secondary">إلغاء</a>
        </form>
    </div>
</div>
@endsection
