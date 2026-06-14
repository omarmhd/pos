@extends('layouts.app')

@section('page-title', 'فواتير إيراد الخدمات')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-lightning-charge text-primary"></i> فواتير إيراد الخدمات (IFRS 15)</h5>
        @can('services.manage')
        <a href="{{ route('service-invoices.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> فاتورة خدمات جديدة
        </a>
        @endcan
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="فواتير إيراد الخدمات">
                <thead>
                    <tr>
                        <th>الرقم</th>
                        <th>التاريخ</th>
                        <th>العميل</th>
                        <th>حساب الإيراد</th>
                        <th>الإجمالي ({{ $currency }})</th>
                        <th>الضريبة</th>
                        <th>النوع</th>
                        <th>الكشف</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $inv)
                    <tr>
                        <td><a href="{{ route('service-invoices.show', $inv) }}">{{ $inv->invoice_number }}</a></td>
                        <td>{{ $inv->invoice_date->format('Y-m-d') }}</td>
                        <td>{{ $inv->partyName() }}</td>
                        <td>{{ $inv->serviceAccount?->name ?? '—' }}</td>
                        <td>{{ number_format($inv->total_amount, 2) }}</td>
                        <td>{{ number_format($inv->tax_amount, 2) }}</td>
                        <td>{!! $inv->is_credit ? '<span class="badge bg-warning text-dark">آجل</span>' : '<span class="badge bg-success">نقدي</span>' !!}</td>
                        <td>
                            @if($inv->res_statement_id)
                                <span class="badge bg-success">مُدرَج</span>
                            @else
                                <span class="badge bg-secondary">غير مُدرَج</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
