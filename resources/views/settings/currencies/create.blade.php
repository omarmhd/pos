@extends('layouts.app')

@section('page-title', 'عملة جديدة')

@section('content')
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-plus-circle"></i> عملة جديدة</h5></div>
            <div class="card-body">
                <form action="{{ route('currencies.store') }}" method="POST">
                    @csrf
                    @include('settings.currencies._form', ['currency' => null])
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
