@extends('layouts.app')
@section('title', 'إضافة وردية')
@section('page-title', 'إضافة وردية جديدة')

@section('content')
<div class="col-lg-6 mx-auto">
    <div class="card">
        <div class="card-header"><i class="bi bi-clock"></i> بيانات الوردية</div>
        <div class="card-body">
            <form method="POST" action="{{ route('hr.shifts.store') }}">
                @csrf
                @include('hr.shifts._form')
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">حفظ</button>
                    <a href="{{ route('hr.shifts.index') }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
