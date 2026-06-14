@extends('layouts.app')

@section('page-title', 'تعديل قائمة أسعار شراء')

@section('content')
<div class="row">
    <div class="col-lg-7 mx-auto">
        <div class="card">
            <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-pencil"></i> تعديل: {{ $purchasePriceList->name }}</h5></div>
            <div class="card-body">
                <form action="{{ route('purchase-price-lists.update', $purchasePriceList) }}" method="POST">
                    @csrf @method('PUT')
                    @include('settings.purchase-price-lists._form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
