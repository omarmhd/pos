@extends('layouts.app')
@section('title', 'ملف العميل')
@section('page-title', 'ملف العميل')

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h4 class="mb-1">{{ $customer->name }}</h4>
                    <div class="text-muted small">
                        @if($customer->phone) <i class="bi bi-telephone"></i> {{ $customer->phone }} &nbsp; @endif
                        @if($customer->email) <i class="bi bi-envelope"></i> {{ $customer->email }} @endif
                    </div>
                    @if($customer->address)
                    <div class="text-muted small mt-1"><i class="bi bi-geo-alt"></i> {{ $customer->address }}</div>
                    @endif
                </div>
                <div class="d-flex gap-2">
                    @if($outstanding > 0)
                    <a href="{{ route('customer-payments.create', $customer) }}" class="btn btn-success">
                        <i class="bi bi-cash-coin"></i> تسجيل دفعة
                    </a>
                    @endif
                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-pencil"></i> تعديل
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Balance Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center border-0 bg-light">
                <div class="card-body">
                    <div class="fs-4 fw-bold text-primary">{{ number_format($totalBilled, 2) }} {{ $currency }}</div>
                    <div class="small text-muted">إجمالي الفواتير الآجلة</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 bg-light">
                <div class="card-body">
                    <div class="fs-4 fw-bold text-success">{{ number_format($totalPaid, 2) }} {{ $currency }}</div>
                    <div class="small text-muted">إجمالي المدفوعات</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 {{ $outstanding > 0 ? 'bg-danger bg-opacity-10' : 'bg-success bg-opacity-10' }}">
                <div class="card-body">
                    <div class="fs-4 fw-bold {{ $outstanding > 0 ? 'text-danger' : 'text-success' }}">
                        {{ number_format($outstanding, 2) }} {{ $currency }}
                    </div>
                    <div class="small text-muted">الرصيد المستحق</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 bg-light">
                <div class="card-body">
                    <div class="fs-4 fw-bold text-info">
                        {{ $customer->credit_limit > 0 ? number_format($availableCredit, 2) : '—' }}
                    </div>
                    <div class="small text-muted">
                        رصيد متاح
                        @if($customer->credit_limit > 0)
                        (حد: {{ number_format($customer->credit_limit, 2) }} {{ $currency }})
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        {{-- Credit Invoices --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-receipt"></i> الفواتير الآجلة</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th class="text-end">المبلغ</th>
                                <th class="text-end">المستحق</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($creditSales as $sale)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $sale->invoice_number }}</td>
                                <td class="text-muted">{{ $sale->created_at->format('Y/m/d') }}</td>
                                <td class="text-end">{{ number_format($sale->total_amount, 2) }} {{ $currency }}</td>
                                <td class="text-end fw-bold {{ $sale->outstanding > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($sale->outstanding, 2) }} {{ $currency }}
                                    @if($sale->outstanding == 0)
                                        <i class="bi bi-check-circle-fill text-success ms-1"></i>
                                    @endif
                                </td>
                                <td class="pe-2">
                                    @if($sale->outstanding > 0)
                                    <a href="{{ route('customer-payments.create', [$customer, 'sale_id' => $sale->id]) }}"
                                       class="btn btn-xs btn-outline-success btn-sm py-0 px-1">
                                        <i class="bi bi-cash"></i>
                                    </a>
                                    @endif
                                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-primary py-0 px-1">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">لا توجد فواتير آجلة</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Payments History --}}
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-cash-coin"></i> سجل المدفوعات</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">التاريخ</th>
                                <th>الفاتورة</th>
                                <th class="text-end">المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $payment)
                            <tr>
                                <td class="ps-3 text-muted">{{ $payment->received_at->format('Y/m/d') }}</td>
                                <td class="small">{{ $payment->sale?->invoice_number ?? '—' }}</td>
                                <td class="text-end fw-bold text-success">{{ number_format($payment->amount, 2) }} {{ $currency }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">لا توجد مدفوعات</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>{{-- end row --}}

</div>
@endsection
