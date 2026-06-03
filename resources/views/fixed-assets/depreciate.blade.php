@extends('layouts.app')
@section('page-title', 'استهلاك — ' . $fixedAsset->name)

@section('content')
<div class="row"><div class="col-lg-6 mx-auto">
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="bi bi-calendar-minus text-warning me-2"></i>
                ترحيل قيد استهلاك — <strong>{{ $fixedAsset->asset_code }}</strong>
            </h5>
        </div>
        <div class="card-body">

            <div class="alert alert-light border mb-3 small">
                <div class="row text-center">
                    <div class="col-4"><div class="text-muted">القيمة الدفترية</div>
                        <strong>{{ number_format($fixedAsset->net_book_value,2) }} {{ $currency }}</strong></div>
                    <div class="col-4"><div class="text-muted">القسط الشهري</div>
                        <strong class="text-warning">{{ number_format($fixedAsset->nextPeriodDepreciation(),2) }} {{ $currency }}</strong></div>
                    <div class="col-4"><div class="text-muted">الأشهر المتبقية</div>
                        <strong>{{ $fixedAsset->remainingMonths() }}</strong></div>
                </div>
            </div>

            <div class="alert alert-light border-start border-warning border-3 mb-4 small">
                <strong>القيد:</strong><br>
                مدين: مصاريف الاستهلاك (6400) &nbsp; دائن: مجمع الاستهلاك (1600)
            </div>

            <form action="{{ route('fixed-assets.depreciate', $fixedAsset) }}" method="POST">
                @csrf
                <div class="row mb-4">
                    <div class="col-6">
                        <label class="form-label">السنة <span class="text-danger">*</span></label>
                        <input type="number" name="period_year" class="form-control"
                               value="{{ old('period_year', date('Y')) }}" min="2000" max="2100" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">الشهر <span class="text-danger">*</span></label>
                        <select name="period_month" class="form-select" required>
                            @foreach(['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'] as $i => $m)
                                <option value="{{ $i+1 }}" {{ date('n') == $i+1 ? 'selected':'' }}>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-play-fill me-1"></i> ترحيل قيد الاستهلاك
                </button>
                <a href="{{ route('fixed-assets.show', $fixedAsset) }}" class="btn btn-secondary">إلغاء</a>
            </form>
        </div>
    </div>
</div></div>
@endsection
