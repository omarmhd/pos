@extends('layouts.app')
@section('page-title', 'تحويل لأمر بيع')

@section('content')
<div class="row"><div class="col-lg-8 mx-auto">
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="bi bi-arrow-right-circle text-success me-2"></i>
                تحويل العرض {{ $salesQuotation->quotation_number }} إلى أمر بيع
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-light border mb-4 small">
                العميل: <strong>{{ $salesQuotation->displayName() }}</strong> &nbsp;|&nbsp;
                إجمالي العرض: <strong>{{ number_format($salesQuotation->total_amount,2) }} {{ $currency }}</strong>
            </div>
            <form action="{{ route('sales-quotations.convert-to-order', $salesQuotation) }}" method="POST">
                @csrf
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">تاريخ الأمر <span class="text-danger">*</span></label>
                        <input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">تاريخ التسليم المتوقع</label>
                        <input type="date" name="expected_delivery_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">نوع البيع</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_credit" id="isCreditSO"
                                   value="1" {{ $salesQuotation->customer_id ? '' : 'disabled' }}>
                            <label class="form-check-label" for="isCreditSO">بيع آجل (على الحساب)</label>
                        </div>
                    </div>
                </div>
                @if($warehouses->count() > 1)
                <div class="mb-4">
                    <label class="form-label">المخزن المُسلِّم</label>
                    <select name="warehouse_id" class="form-select" style="max-width:300px">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}"
                                {{ $wh->id == $defaultWarehouseId ? 'selected':'' }}>
                                {{ $wh->name }} @if($wh->is_default) ⭐ @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                @else
                    <input type="hidden" name="warehouse_id" value="{{ $defaultWarehouseId }}">
                @endif
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2-circle me-1"></i> إنشاء أمر البيع
                </button>
                <a href="{{ route('sales-quotations.show', $salesQuotation) }}" class="btn btn-secondary">إلغاء</a>
            </form>
        </div>
    </div>
</div></div>
@endsection
