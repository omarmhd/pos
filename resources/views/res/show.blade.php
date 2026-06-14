@extends('layouts.app')

@section('page-title', 'كشف ' . $re_statement->number)

@section('content')
@if($lateCount > 0)
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    توجد {{ $lateCount }} فاتورة غير مدرجة وتاريخها ضمن مدى هذا الكشف (أُدخلت متأخرة أو محجوزة) —
    @can('res.manage')<a href="{{ route('res.edit', $re_statement) }}">عدّل الكشف لإدراجها</a>@endcan
</div>
@endif

<div class="card mb-3 d-print-none">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-file-earmark-bar-graph text-primary"></i>
            كشف الإيرادات والمصروفات: {{ $re_statement->number }}
        </h5>
        <div>
            <a href="{{ route('res.print', $re_statement) }}" target="_blank" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> طباعة PDF
            </a>
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer"></i> طباعة الصفحة</button>
            @can('res.manage')
            <a href="{{ route('res.edit', $re_statement) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> تعديل</a>
            @endcan
            <a href="{{ route('res.index') }}" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> رجوع</a>
        </div>
    </div>
</div>

{{-- ── ملخص الكشف (كما في شكل الأصيل) ── --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3"><strong>الرقم:</strong> {{ $re_statement->number }}</div>
            <div class="col-md-3"><strong>تاريخ القطع:</strong> {{ $re_statement->statement_date->format('Y-m-d') }}</div>
            <div class="col-md-4"><strong>البيان:</strong> {{ $re_statement->description ?? '—' }}</div>
            <div class="col-md-2"><strong>المستخدم:</strong> {{ $re_statement->user?->name ?? '—' }}</div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-success h-100">
                    <div class="card-header bg-success bg-opacity-10 py-2"><strong>الإيرادات</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tr><td>المبيعات (صافي)</td>
                                <td class="text-end">{{ number_format($re_statement->sales_amount, 2) }}</td>
                                <td class="text-end text-muted small">ضريبة: {{ number_format($re_statement->sales_tax, 2) }}</td></tr>
                            @if($re_statement->services_amount > 0)
                            <tr class="text-muted small"><td>↳ منها إيرادات الخدمات (Service Revenue)</td>
                                <td class="text-end">{{ number_format($re_statement->services_amount, 2) }}</td>
                                <td class="text-end">ضريبة: {{ number_format($re_statement->services_tax, 2) }}</td></tr>
                            @endif
                            <tr><td>(−) مردودات المبيعات</td>
                                <td class="text-end">{{ number_format($re_statement->sales_returns_amount, 2) }}</td><td></td></tr>
                            @if($re_statement->credit_notes_amount > 0)
                            <tr class="text-muted small"><td>↳ منها إشعارات دائنة (Credit Notes)</td>
                                <td class="text-end">{{ number_format($re_statement->credit_notes_amount, 2) }}</td><td></td></tr>
                            @endif
                            <tr class="table-light fw-bold"><td>مجموع الإيرادات</td>
                                <td class="text-end">{{ number_format($re_statement->sales_amount - $re_statement->sales_returns_amount, 2) }}</td><td></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-danger h-100">
                    <div class="card-header bg-danger bg-opacity-10 py-2"><strong>المصروفات</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tr><td>المشتريات (صافي)</td>
                                <td class="text-end">{{ number_format($re_statement->purchases_amount, 2) }}</td>
                                <td class="text-end text-muted small">ضريبة: {{ number_format($re_statement->purchases_tax, 2) }}</td></tr>
                            <tr><td>(−) مردودات المشتريات</td>
                                <td class="text-end">{{ number_format($re_statement->purchase_returns_amount, 2) }}</td><td></td></tr>
                            @if($re_statement->debit_notes_amount > 0)
                            <tr class="text-muted small"><td>↳ منها إشعارات مدينة (Debit Notes)</td>
                                <td class="text-end">{{ number_format($re_statement->debit_notes_amount, 2) }}</td><td></td></tr>
                            @endif
                            <tr><td>المصاريف التشغيلية (صافي)</td>
                                <td class="text-end">{{ number_format($re_statement->expenses_amount, 2) }}</td>
                                <td class="text-end text-muted small">ضريبة: {{ number_format($re_statement->expenses_tax, 2) }}</td></tr>
                            <tr class="table-light fw-bold"><td>مجموع المصروفات</td>
                                <td class="text-end">{{ number_format($re_statement->purchases_amount - $re_statement->purchase_returns_amount + $re_statement->expenses_amount, 2) }}</td><td></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if($re_statement->assets_amount > 0 || $re_statement->assets_tax > 0 || $re_statement->customs_amount > 0 || $re_statement->customs_tax > 0)
        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <div class="card border-primary h-100">
                    <div class="card-header bg-primary bg-opacity-10 py-2"><strong>الأصول الرأسمالية (Capital Assets)</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tr><td>تكلفة الأصول</td>
                                <td class="text-end">{{ number_format($re_statement->assets_amount, 2) }}</td>
                                <td class="text-end text-muted small">ض. مدخلات: {{ number_format($re_statement->assets_tax, 2) }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-secondary h-100">
                    <div class="card-header bg-secondary bg-opacity-10 py-2"><strong>الإقرارات الجمركية (Import VAT)</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tr><td>قيمة الواردات / الرسوم</td>
                                <td class="text-end">{{ number_format($re_statement->customs_amount, 2) }}</td>
                                <td class="text-end text-muted small">ض. مدخلات: {{ number_format($re_statement->customs_tax, 2) }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <div class="alert {{ $re_statement->net_amount >= 0 ? 'alert-success' : 'alert-danger' }} mb-0 text-center">
                    <div class="small">المبلغ الإجمالي</div>
                    <div class="fs-4 fw-bold">{{ number_format($re_statement->net_amount, 2) }} {{ $currency }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-warning mb-0 text-center">
                    <div class="small">الضريبة المضافة للدفع (مخرجات − مدخلات)</div>
                    <div class="fs-4 fw-bold">{{ number_format($re_statement->net_vat, 2) }} {{ $currency }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-info mb-0 text-center">
                    <div class="small">نسبة أرباح المتاجرة</div>
                    <div class="fs-4 fw-bold">{{ number_format($re_statement->profit_percent, 2) }}%</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Drill-down: فواتير كل بند ── --}}
@php
    $sections = [
        ['label' => 'فواتير المبيعات',       'docs' => $re_statement->sales,               'number' => 'invoice_number',     'date' => 'created_at',       'party' => fn($d) => $d->customer?->name ?? 'نقدي',          'amount' => fn($d) => $d->total_amount, 'tax' => fn($d) => $d->tax],
        ['label' => 'مردودات المبيعات',      'docs' => $re_statement->saleReturns,         'number' => 'return_number',      'date' => 'return_date',      'party' => fn($d) => $d->refundMethodLabel(),                'amount' => fn($d) => $d->total_amount, 'tax' => fn($d) => 0],
        ['label' => 'فواتير المشتريات',      'docs' => $re_statement->purchases,           'number' => 'invoice_number',     'date' => 'created_at',       'party' => fn($d) => $d->supplier?->name ?? '—',             'amount' => fn($d) => $d->total_amount, 'tax' => fn($d) => $d->tax_amount],
        ['label' => 'مردودات المشتريات',     'docs' => $re_statement->purchaseReturns,     'number' => 'return_number',      'date' => 'return_date',      'party' => fn($d) => $d->refundMethodLabel(),                'amount' => fn($d) => $d->total_amount, 'tax' => fn($d) => 0],
        ['label' => 'فواتير المصاريف',       'docs' => $re_statement->expenseInvoices,     'number' => 'invoice_number',     'date' => 'invoice_date',     'party' => fn($d) => $d->vendor_name,                        'amount' => fn($d) => $d->total_amount, 'tax' => fn($d) => $d->tax_amount],
        ['label' => 'الأصول الرأسمالية',     'docs' => $re_statement->fixedAssets,         'number' => 'asset_code',         'date' => 'purchase_date',    'party' => fn($d) => $d->name,                               'amount' => fn($d) => $d->purchase_cost, 'tax' => fn($d) => $d->tax_amount],
        ['label' => 'الإقرارات الجمركية',    'docs' => $re_statement->customsDeclarations, 'number' => 'declaration_number', 'date' => 'declaration_date', 'party' => fn($d) => $d->supplier?->name ?? $d->vendor_name, 'amount' => fn($d) => $d->total_amount, 'tax' => fn($d) => $d->tax_amount],
        ['label' => 'فواتير إيراد الخدمات',  'docs' => $re_statement->serviceInvoices,     'number' => 'invoice_number',     'date' => 'invoice_date',     'party' => fn($d) => $d->customer?->name ?? $d->customer_name, 'amount' => fn($d) => $d->total_amount, 'tax' => fn($d) => $d->tax_amount],
    ];
@endphp

@foreach($sections as $sec)
@if($sec['docs']->count())
<div class="card mb-3">
    <div class="card-header bg-light py-2"><strong>{{ $sec['label'] }} ({{ $sec['docs']->count() }})</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th>الرقم</th><th>التاريخ</th><th>الطرف</th>
                        <th class="text-end">المبلغ ({{ $currency }})</th><th class="text-end">الضريبة</th></tr>
                </thead>
                <tbody>
                    @foreach($sec['docs'] as $doc)
                    <tr>
                        <td class="font-monospace">{{ $doc->{$sec['number']} }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($doc->{$sec['date']})->format('Y-m-d') }}</td>
                        <td>{{ $sec['party']($doc) }}</td>
                        <td class="text-end">{{ number_format($sec['amount']($doc), 2) }}</td>
                        <td class="text-end">{{ number_format($sec['tax']($doc), 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endforeach
@endsection
