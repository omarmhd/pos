@extends('layouts.app')

@section('page-title', 'فاتورة خدمات ' . $service_invoice->invoice_number)

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-lightning-charge text-primary"></i>
            فاتورة إيراد خدمات: {{ $service_invoice->invoice_number }}
        </h5>
        <a href="{{ route('service-invoices.index') }}" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> رجوع</a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><strong>التاريخ:</strong> {{ $service_invoice->invoice_date->format('Y-m-d') }}</div>
            <div class="col-md-4"><strong>العميل:</strong> {{ $service_invoice->partyName() }}</div>
            <div class="col-md-4"><strong>حساب الإيراد:</strong> {{ $service_invoice->serviceAccount?->name ?? '—' }}</div>
            <div class="col-md-4"><strong>الصافي:</strong> {{ number_format($service_invoice->netAmount(), 2) }} {{ $currency }}</div>
            <div class="col-md-4"><strong>ض.ق.م مستحقة:</strong> {{ number_format($service_invoice->tax_amount, 2) }} {{ $currency }}</div>
            <div class="col-md-4"><strong>الإجمالي:</strong> {{ number_format($service_invoice->total_amount, 2) }} {{ $currency }}</div>
            <div class="col-md-4"><strong>النوع:</strong> {!! $service_invoice->is_credit ? '<span class="badge bg-warning text-dark">آجل</span>' : '<span class="badge bg-success">نقدي</span>' !!}</div>
            <div class="col-md-4"><strong>القيد:</strong> {{ $service_invoice->journalEntry?->entry_number ?? ($service_invoice->is_posted ? 'مُرحَّلة' : 'غير مُرحَّلة') }}</div>
            <div class="col-md-4"><strong>الكشف:</strong>
                @if($service_invoice->res_statement_id)
                    <span class="badge bg-success">{{ $service_invoice->resStatement?->number ?? 'مُدرَج' }}</span>
                @else
                    <span class="badge bg-secondary">غير مُدرَج بعد</span>
                @endif
            </div>
            @if($service_invoice->description)
            <div class="col-12"><strong>بيان الخدمة:</strong> {{ $service_invoice->description }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
