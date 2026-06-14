@extends('layouts.app')

@section('page-title', 'تسعير أصناف القائمة')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-currency-dollar"></i> أسعار الشراء — {{ $purchasePriceList->name }}</h5>
        <a href="{{ route('purchase-price-lists.index') }}" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>
    <div class="card-body">
        <form action="{{ route('purchase-price-lists.products', $purchasePriceList) }}" method="POST">
            @csrf
            <div class="table-responsive">
                <table class="table table-hover dt-table" style="width:100%" data-title="أسعار الشراء — {{ $purchasePriceList->name }}">
                    <thead>
                        <tr>
                            <th>الصنف</th>
                            <th>الباركود</th>
                            <th>التكلفة الحالية (AVCO)</th>
                            <th style="width:180px">سعر الشراء في القائمة ({{ $currency }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $p)
                        <tr>
                            <td>{{ $p->name }}</td>
                            <td class="font-monospace">{{ $p->barcode }}</td>
                            <td>{{ number_format($p->cost_price, 2) }}</td>
                            <td>
                                <input type="number" name="prices[{{ $p->id }}]" class="form-control form-control-sm"
                                       value="{{ $p->list_cost }}" step="0.01" min="0" placeholder="—">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @can('purchase_price_lists.manage')
            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save"></i> حفظ الأسعار</button>
            @endcan
        </form>
    </div>
</div>
@endsection
