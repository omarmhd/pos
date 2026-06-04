@extends('layouts.app')
@section('page-title', 'افتتاح وردية نقدية')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-success shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-unlock-fill me-2"></i>
                    افتتاح وردية نقدية جديدة
                </h5>
            </div>
            <div class="card-body">

                {{-- Context info --}}
                <div class="alert alert-light border mb-4">
                    <div class="row g-2 small">
                        <div class="col-6">
                            <span class="text-muted">الكاشير:</span>
                            <strong>{{ auth()->user()->name }}</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted">التاريخ:</span>
                            <strong>{{ now()->format('Y-m-d H:i') }}</strong>
                        </div>
                        @if(auth()->user()->branch)
                        <div class="col-6">
                            <span class="text-muted">الفرع:</span>
                            <strong>{{ auth()->user()->branch->name }}</strong>
                        </div>
                        @endif
                        @if(auth()->user()->posTerminal)
                        <div class="col-6">
                            <span class="text-muted">Terminal:</span>
                            <strong>{{ auth()->user()->posTerminal->name }}</strong>
                        </div>
                        @endif
                    </div>
                </div>

                <form action="{{ route('pos.shifts.store-open') }}" method="POST">
                    @csrf

                    <div class="mb-4">
                        <label class="form-label fw-bold fs-5">
                            <i class="bi bi-cash-coin text-success me-1"></i>
                            الرصيد الافتتاحي (النقدية في الدرج)
                        </label>
                        <div class="input-group input-group-lg">
                            <input type="number" name="opening_amount"
                                   class="form-control @error('opening_amount') is-invalid @enderror"
                                   value="{{ old('opening_amount', 0) }}"
                                   step="0.01" min="0" required autofocus>
                            <span class="input-group-text">{{ $currency }}</span>
                        </div>
                        @error('opening_amount')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        <div class="form-text">أدخل المبلغ النقدي الموجود في الدرج قبل البدء بالمبيعات</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">ملاحظات (اختياري)</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="أي ملاحظة حول بداية الوردية...">{{ old('notes') }}</textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-unlock-fill me-1"></i>
                            افتتاح الوردية والانتقال لنقطة البيع
                        </button>
                        <a href="{{ route('pos.index') }}" class="btn btn-outline-secondary">
                            تجاوز — البدء بدون وردية
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
