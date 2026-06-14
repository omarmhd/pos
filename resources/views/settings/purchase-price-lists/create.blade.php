@extends('layouts.app')

@section('page-title', 'قائمة أسعار شراء جديدة')

@section('content')
<div class="row">
    <div class="col-lg-7 mx-auto">
        <div class="card">
            <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-plus-circle"></i> قائمة أسعار شراء جديدة</h5></div>
            <div class="card-body">
                <form action="{{ route('purchase-price-lists.store') }}" method="POST">
                    @csrf
                    @include('settings.purchase-price-lists._form', ['purchasePriceList' => null])
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
