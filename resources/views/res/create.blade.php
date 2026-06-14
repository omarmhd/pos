@extends('layouts.app')

@section('page-title', 'كشف إيرادات ومصروفات جديد')

@section('content')
<div class="card mb-3">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-file-earmark-plus text-primary"></i> كشف إيرادات ومصروفات جديد</h5>
    </div>
    <div class="card-body">
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        {{-- الخطوة 1: اختيار تاريخ القطع (معاينة) --}}
        <form action="{{ route('res.create') }}" method="GET" class="row g-2 align-items-end mb-0">
            <div class="col-md-3">
                <label class="form-label fw-semibold">تاريخ القطع <span class="text-danger">*</span></label>
                <input type="date" name="statement_date" class="form-control"
                       value="{{ $statementDate ?? date('Y-m-d') }}" required>
                <div class="form-text">تُدرج كل الفواتير حتى هذا التاريخ التي لم تدخل أي كشف سابق</div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search"></i> معاينة الفواتير
                </button>
            </div>
        </form>
    </div>
</div>

@if($candidates !== null)
<form action="{{ route('res.store') }}" method="POST">
    @csrf
    <input type="hidden" name="statement_date" value="{{ $statementDate }}">

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">بيان التقرير</label>
                    <input type="text" name="description" class="form-control"
                           value="{{ old('description', 'تقرير حتى ' . $statementDate) }}" maxlength="255">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success"
                            onclick="return confirm('سيتم تسجيل الكشف وتعيين الفواتير المحددة له نهائياً. متابعة؟')">
                        <i class="bi bi-save"></i> تسجيل الكشف
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('res._candidates', ['candidates' => $candidates, 'currency' => $currency])
</form>
@endif
@endsection
