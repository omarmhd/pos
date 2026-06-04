@extends('layouts.app')
@section('page-title', 'إقفال الوردية النقدية')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-warning shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="bi bi-lock-fill me-2"></i>
                    إقفال الوردية النقدية #{{ $shift->id }}
                </h5>
            </div>
            <div class="card-body">

                {{-- Shift summary --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card bg-light border-0 h-100">
                            <div class="card-body py-2">
                                <div class="small text-muted mb-1">معلومات الوردية</div>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted">الكاشير</td><td><strong>{{ $shift->user?->name }}</strong></td></tr>
                                    <tr><td class="text-muted">الافتتاح</td><td>{{ $shift->opened_at->format('H:i') }}</td></tr>
                                    <tr><td class="text-muted">المدة</td><td>{{ $shift->durationLabel() }}</td></tr>
                                    @if($shift->posTerminal)
                                    <tr><td class="text-muted">Terminal</td><td>{{ $shift->posTerminal->name }}</td></tr>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light border-0 h-100">
                            <div class="card-body py-2">
                                <div class="small text-muted mb-1">ملخص النقدية</div>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr>
                                        <td class="text-muted">رصيد افتتاحي</td>
                                        <td class="text-end font-monospace">{{ number_format($shift->opening_amount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">+ مبيعات نقدية</td>
                                        <td class="text-end font-monospace text-success">{{ number_format($shift->cash_sales, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">− مرتجعات نقدية</td>
                                        <td class="text-end font-monospace text-danger">{{ number_format($shift->cash_returns, 2) }}</td>
                                    </tr>
                                    <tr class="border-top fw-bold">
                                        <td>المبلغ المتوقع</td>
                                        <td class="text-end font-monospace fs-6">{{ number_format($shift->expected_amount, 2) }} {{ $currency }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <form action="{{ route('pos.shifts.store-close', $shift) }}" method="POST">
                    @csrf

                    {{-- Physical cash count --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold fs-5">
                            <i class="bi bi-cash-stack text-warning me-1"></i>
                            عدّ النقدية الفعلية في الدرج
                        </label>
                        <div class="input-group input-group-lg">
                            <input type="number" name="closing_amount" id="closingAmount"
                                   class="form-control @error('closing_amount') is-invalid @enderror"
                                   value="{{ old('closing_amount') }}"
                                   step="0.01" min="0" required autofocus>
                            <span class="input-group-text">{{ $currency }}</span>
                        </div>
                        @error('closing_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    {{-- Live variance display --}}
                    <div class="alert mb-4" id="varianceAlert" style="display:none">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>الفرق النقدي:</span>
                            <span id="varianceDisplay" class="fw-bold fs-5 font-monospace"></span>
                        </div>
                    </div>

                    {{-- Variance reason --}}
                    <div class="mb-4" id="reasonBlock" style="display:none">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-chat-text me-1"></i>
                            سبب الفرق <span class="text-danger">*</span>
                        </label>
                        <textarea name="variance_reason"
                                  class="form-control"
                                  rows="2"
                                  placeholder="اشرح سبب الفرق النقدي...">{{ old('variance_reason') }}</textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning btn-lg text-dark fw-bold">
                            <i class="bi bi-lock-fill me-1"></i>
                            إقفال الوردية
                        </button>
                        <a href="{{ route('pos.shifts.show', $shift) }}" class="btn btn-outline-secondary">
                            إلغاء — العودة
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
var expected = {{ (float)$shift->expected_amount }};
var currency = '{{ $currency }}';

$('#closingAmount').on('input', function() {
    var closing  = parseFloat($(this).val()) || 0;
    var variance = closing - expected;
    var absV     = Math.abs(variance);

    if (isNaN(closing) || $(this).val() === '') {
        $('#varianceAlert').hide();
        $('#reasonBlock').hide();
        return;
    }

    $('#varianceAlert').show();

    if (absV < 0.01) {
        $('#varianceAlert').removeClass('alert-danger alert-info alert-warning').addClass('alert-success');
        $('#varianceDisplay').html('<i class="bi bi-check-circle me-1"></i> لا فروق — متطابق تماماً');
        $('#reasonBlock').hide();
    } else if (variance < 0) {
        $('#varianceAlert').removeClass('alert-success alert-info alert-warning').addClass('alert-danger');
        $('#varianceDisplay').html('نقص ' + absV.toFixed(2) + ' ' + currency);
        $('#reasonBlock').show();
    } else {
        $('#varianceAlert').removeClass('alert-success alert-danger alert-warning').addClass('alert-info');
        $('#varianceDisplay').html('زيادة ' + absV.toFixed(2) + ' ' + currency);
        $('#reasonBlock').show();
    }
});
</script>
@endsection
