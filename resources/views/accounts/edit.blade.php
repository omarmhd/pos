@extends('layouts.app')
@section('page-title', 'تعديل الحساب: ' . $account->code)

@section('content')
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-pencil"></i> تعديل الحساب
                    <code class="ms-1">{{ $account->code }}</code>
                </h5>
                <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> العودة
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('accounts.update', $account->id) }}">
                    @csrf @method('PUT')
                    @include('accounts._form', ['account' => $account])
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary"><i class="bi bi-save"></i> حفظ التعديلات</button>
                        <a href="{{ route('accounts.index') }}" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
