@extends('layouts.app')
@section('page-title', 'تحويل بيني بين فرعين')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-arrow-left-right text-primary me-2"></i>
            تسجيل تحويل نقدي بيني
        </h5>
    </div>
    <div class="card-body">

        <div class="alert alert-light border mb-4 small">
            <strong>القيد المحاسبي التلقائي:</strong><br>
            <span class="text-danger">● فرع المرسل:</span> DR مستحق من الفرع الوجهة (1700.XX) / CR صندوق المرسل<br>
            <span class="text-success">● فرع الوجهة:</span> DR صندوق الوجهة / CR مستحق للفرع المرسل (2700.XX)
        </div>

        <form action="{{ route('inter-branch.store') }}" method="POST">
        @csrf

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    <i class="bi bi-box-arrow-right text-danger me-1"></i>
                    من فرع (المرسل) <span class="text-danger">*</span>
                </label>
                <select name="from_branch_id" class="form-select @error('from_branch_id') is-invalid @enderror" required>
                    <option value="">اختر الفرع المرسل</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ old('from_branch_id') == $b->id ? 'selected':'' }}>
                            [{{ $b->code }}] {{ $b->name }}
                        </option>
                    @endforeach
                </select>
                @error('from_branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    <i class="bi bi-box-arrow-in-right text-success me-1"></i>
                    إلى فرع (الوجهة) <span class="text-danger">*</span>
                </label>
                <select name="to_branch_id" class="form-select @error('to_branch_id') is-invalid @enderror" required>
                    <option value="">اختر الفرع الوجهة</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ old('to_branch_id') == $b->id ? 'selected':'' }}>
                            [{{ $b->code }}] {{ $b->name }}
                        </option>
                    @endforeach
                </select>
                @error('to_branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-5">
                <label class="form-label fw-semibold">المبلغ <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                           value="{{ old('amount') }}" step="0.01" min="0.01" required>
                    <span class="input-group-text">{{ $currency }}</span>
                </div>
                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">تاريخ التحويل <span class="text-danger">*</span></label>
                <input type="date" name="transfer_date" class="form-control"
                       value="{{ old('transfer_date', date('Y-m-d')) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">المرجع</label>
                <input type="text" name="reference" class="form-control"
                       value="{{ old('reference') }}" placeholder="IBT-001">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">وصف / سبب التحويل</label>
            <textarea name="description" class="form-control" rows="2"
                      placeholder="سبب التحويل، مرجع القرار...">{{ old('description') }}</textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-send-check me-1"></i> تسجيل التحويل وإنشاء القيدين
            </button>
            <a href="{{ route('inter-branch.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection
