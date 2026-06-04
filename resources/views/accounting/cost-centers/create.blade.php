@extends('layouts.app')
@section('page-title', 'مركز تكلفة جديد')
@section('content')
<div class="row justify-content-center"><div class="col-md-6">
<div class="card">
    <div class="card-header bg-white"><h5 class="mb-0">إنشاء مركز تكلفة</h5></div>
    <div class="card-body">
        <form action="{{ route('cost-centers.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label class="form-label fw-semibold">الكود <span class="text-danger">*</span></label>
            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                   value="{{ old('code') }}" required>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">الاسم <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">الفرع (اختياري)</label>
            <select name="branch_id" class="form-select">
                <option value="">— لجميع الفروع —</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected':'' }}>
                        [{{ $b->code }}] {{ $b->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="mb-4">
            <label class="form-label">ملاحظات</label>
            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>حفظ</button>
        <a href="{{ route('cost-centers.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        </form>
    </div>
</div>
</div></div>
@endsection
