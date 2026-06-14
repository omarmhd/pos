@extends('layouts.app')

@section('page-title', 'إقرار جمركي ' . $customs_declaration->declaration_number)

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-box-arrow-in-down text-primary"></i>
            الإقرار الجمركي: {{ $customs_declaration->declaration_number }}
        </h5>
        <a href="{{ route('customs-declarations.index') }}" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> رجوع</a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><strong>التاريخ:</strong> {{ $customs_declaration->declaration_date->format('Y-m-d') }}</div>
            <div class="col-md-4"><strong>رقم البيان الجمركي:</strong> {{ $customs_declaration->customs_ref ?? '—' }}</div>
            <div class="col-md-4"><strong>المورد:</strong> {{ $customs_declaration->supplier?->name ?? $customs_declaration->vendor_name ?? '—' }}</div>
            <div class="col-md-4"><strong>قيمة الواردات / الرسوم:</strong> {{ number_format($customs_declaration->total_amount, 2) }} {{ $currency }}</div>
            <div class="col-md-4"><strong>ض.ق.م الواردات (مدخلات):</strong> {{ number_format($customs_declaration->tax_amount, 2) }} {{ $currency }}</div>
            <div class="col-md-4"><strong>الكشف:</strong>
                @if($customs_declaration->res_statement_id)
                    <span class="badge bg-success">{{ $customs_declaration->resStatement?->number ?? 'مُدرَج' }}</span>
                @else
                    <span class="badge bg-secondary">غير مُدرَج بعد</span>
                @endif
            </div>
            @if($customs_declaration->notes)
            <div class="col-12"><strong>ملاحظات:</strong> {{ $customs_declaration->notes }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
