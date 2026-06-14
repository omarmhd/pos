@extends('layouts.app')

@section('page-title', 'كشوف الإيرادات والمصروفات')

@section('content')
@if($lateCount > 0)
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>تنبيه:</strong> توجد {{ $lateCount }} فاتورة لم تدخل أي كشف وتاريخها يقع قبل آخر تاريخ قطع
    ({{ \Illuminate\Support\Carbon::parse($latestCutoff)->format('Y-m-d') }}) —
    أُدخلت بعد حفظ الكشوف. أنشئ كشفاً جديداً أو عدّل الكشف الأخير لإدراجها.
</div>
@endif

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-file-earmark-bar-graph text-primary"></i> كشوف الإيرادات والمصروفات</h5>
        @can('res.manage')
        <a href="{{ route('res.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> كشف جديد
        </a>
        @endcan
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%" data-title="كشوف الإيرادات والمصروفات">
                <thead>
                    <tr>
                        <th>الرقم</th>
                        <th>تاريخ القطع</th>
                        <th>البيان</th>
                        <th>المبلغ الإجمالي ({{ $currency }})</th>
                        <th>الضريبة للدفع</th>
                        <th>نسبة الربح %</th>
                        <th>الفواتير</th>
                        <th>المستخدم</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($statements as $s)
                    <tr>
                        <td><a href="{{ route('res.show', $s) }}">{{ $s->number }}</a></td>
                        <td>{{ $s->statement_date->format('Y-m-d') }}</td>
                        <td>{{ $s->description ?? '—' }}</td>
                        <td class="{{ $s->net_amount >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($s->net_amount, 2) }}
                        </td>
                        <td>{{ number_format($s->net_vat, 2) }}</td>
                        <td>{{ number_format($s->profit_percent, 2) }}%</td>
                        <td>{{ $s->sales_count + $s->purchases_count + $s->expense_invoices_count }}</td>
                        <td>{{ $s->user?->name ?? '—' }}</td>
                        <td>
                            <a href="{{ route('res.show', $s) }}" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                            @can('res.manage')
                            <a href="{{ route('res.edit', $s) }}" class="btn btn-sm btn-primary" title="تعديل"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('res.destroy', $s) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('حذف الكشف؟ ستُحرَّر فواتيره لتدخل في كشف لاحق.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
