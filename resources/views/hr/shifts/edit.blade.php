@extends('layouts.app')
@section('title', 'تعديل وردية')
@section('page-title', 'تعديل الوردية')

@section('content')
<div class="col-lg-6 mx-auto">
    <div class="card">
        <div class="card-header"><i class="bi bi-pencil"></i> تعديل: {{ $shift->name }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('hr.shifts.update', $shift) }}">
                @csrf @method('PUT')
                @include('hr.shifts._form')
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                    <a href="{{ route('hr.shifts.index') }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
