@extends('layouts.app')

@section('page-title', 'تعديل عملة')

@section('content')
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-pencil"></i> تعديل العملة: {{ $currency->name }}</h5></div>
            <div class="card-body">
                <form action="{{ route('currencies.update', $currency) }}" method="POST">
                    @csrf @method('PUT')
                    @include('settings.currencies._form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
