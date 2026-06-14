@extends('layouts.app')

@section('page-title', 'تعديل كشف ' . $re_statement->number)

@section('content')
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<form action="{{ route('res.update', $re_statement) }}" method="POST">
    @csrf @method('PUT')

    <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-pencil"></i> تعديل الكشف: {{ $re_statement->number }}
                <small class="text-muted">— تاريخ القطع: {{ $re_statement->statement_date->format('Y-m-d') }}</small>
            </h5>
            <a href="{{ route('res.show', $re_statement) }}" class="btn btn-secondary btn-sm">رجوع</a>
        </div>
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">بيان التقرير</label>
                    <input type="text" name="description" class="form-control"
                           value="{{ old('description', $re_statement->description) }}" maxlength="255">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success"
                            onclick="return confirm('سيُعاد تعيين الأعضاء واحتساب الإجماليات. متابعة؟')">
                        <i class="bi bi-save"></i> حفظ التعديلات
                    </button>
                </div>
            </div>
            <div class="form-text mt-2">
                الأسطر الصفراء: فواتير محجوزة أو أُدخلت متأخرة بعد حفظ الكشف وتاريخها ضمن مداه — حدّدها لإدراجها.
            </div>
        </div>
    </div>

    @php
        $memberIds = [
            'sales'            => $re_statement->sales()->pluck('id')->all(),
            'sale_returns'     => $re_statement->saleReturns()->pluck('id')->all(),
            'purchases'        => $re_statement->purchases()->pluck('id')->all(),
            'purchase_returns' => $re_statement->purchaseReturns()->pluck('id')->all(),
            'expense_invoices' => $re_statement->expenseInvoices()->pluck('id')->all(),
        ];
    @endphp

    @include('res._candidates', ['candidates' => $candidates, 'currency' => $currency, 'memberIds' => $memberIds])
</form>
@endsection
