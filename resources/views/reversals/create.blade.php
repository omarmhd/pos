@extends('layouts.app')
@section('title', 'إنشاء قيد عكسي')
@section('page-title', 'إنشاء قيد تصحيح عكسي')

@section('content')
<div class="col-lg-7 mx-auto">
    <div class="card">
        <div class="card-header bg-danger text-white">
            <i class="bi bi-arrow-counterclockwise"></i> قيد تصحيح جديد
        </div>
        <div class="card-body">

            {{-- Errors --}}
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Explanation --}}
            <div class="alert alert-warning small mb-4">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>تحذير:</strong> القيد العكسي لا يمكن التراجع عنه.
                سيُنشئ قيداً محاسبياً معاكساً ويُعدِّل المخزون تلقائياً.
                استخدمه فقط لتصحيح فاتورة مُرحَّلة تحتوي على خطأ.
            </div>

            <form method="POST" action="{{ route('reversals.store') }}"
                  onsubmit="return confirm('هل أنت متأكد من إنشاء هذا القيد العكسي؟ لا يمكن التراجع عنه.')">
                @csrf

                @if($original_type && $original_id)
                    {{-- Pre-filled from sale/purchase page --}}
                    <input type="hidden" name="original_type" value="{{ $original_type }}">
                    <input type="hidden" name="original_id" value="{{ $original_id }}">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">المستند الأصلي</label>
                        <div class="form-control bg-light text-muted">
                            {{ class_basename($original_type) }} #{{ $original_id }}
                        </div>
                    </div>
                @else
                    {{-- Manual entry --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">نوع المستند <span class="text-danger">*</span></label>
                        <select name="original_type" class="form-select" required>
                            <option value="">— اختر —</option>
                            <option value="App\Models\Sale">فاتورة مبيعات (Sale)</option>
                            <option value="App\Models\Purchase">فاتورة مشتريات (Purchase)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">رقم المستند (ID) <span class="text-danger">*</span></label>
                        <input type="number" name="original_id" class="form-control" required min="1"
                               placeholder="رقم الفاتورة في قاعدة البيانات">
                        <div class="form-text text-muted">يجب أن تكون الفاتورة مُرحَّلة (is_posted = true) ولم يسبق عكسها.</div>
                    </div>
                @endif

                <div class="mb-4">
                    <label class="form-label fw-semibold">سبب التصحيح</label>
                    <textarea name="reason" class="form-control" rows="3"
                              placeholder="اكتب سبب واضحاً لهذا التصحيح لحفظه في سجل المراجعة...">{{ old('reason') }}</textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-arrow-counterclockwise"></i> إنشاء القيد العكسي
                    </button>
                    <a href="{{ route('reversals.index') }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
