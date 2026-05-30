@extends('layouts.app')
@section('page-title', 'تفاصيل تعديل المخزون')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-box-seam"></i> تعديل مخزون — #{{ $adjustment->id }}</span>
        <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-sm">
            <tr><th width="35%">المنتج</th><td>{{ $adjustment->product->name }}</td></tr>
            <tr><th>الكمية قبل</th><td>{{ number_format($adjustment->quantity_before, 3) }}</td></tr>
            <tr><th>الكمية بعد</th><td>{{ number_format($adjustment->quantity_after, 3) }}</td></tr>
            <tr>
                <th>الفرق</th>
                <td class="{{ $adjustment->quantity_change < 0 ? 'text-danger' : 'text-success' }} fw-bold">
                    {{ $adjustment->quantity_change > 0 ? '+' : '' }}{{ number_format($adjustment->quantity_change, 3) }}
                </td>
            </tr>
            <tr><th>تكلفة الوحدة</th><td>{{ number_format($adjustment->cost_per_unit, 2) }} {{ $currency }}</td></tr>
            <tr>
                <th>القيمة المالية</th>
                <td class="{{ $adjustment->quantity_change < 0 ? 'text-danger' : 'text-success' }} fw-bold">
                    {{ number_format($adjustment->total_cost, 2) }} {{ $currency }}
                </td>
            </tr>
            <tr><th>السبب</th><td>{{ $adjustment->reasonLabel() }}</td></tr>
            <tr><th>الملاحظات</th><td>{{ $adjustment->notes ?? '—' }}</td></tr>
            <tr><th>المستخدم</th><td>{{ $adjustment->createdBy?->name ?? '—' }}</td></tr>
            <tr><th>التاريخ</th><td>{{ $adjustment->created_at->format('Y/m/d H:i') }}</td></tr>
            @if($adjustment->session)
            <tr>
                <th>جلسة الجرد</th>
                <td>
                    <a href="{{ route('inventory.sessions.show', $adjustment->inventory_session_id) }}">
                        {{ $adjustment->session->reference }}
                    </a>
                </td>
            </tr>
            @endif
        </table>

        @if($adjustment->journalEntry)
        <hr>
        <h6 class="fw-bold"><i class="bi bi-journal-text"></i> القيد المحاسبي</h6>
        <table class="table table-sm table-bordered">
            <thead class="table-light">
                <tr><th>الحساب</th><th class="text-end">مدين</th><th class="text-end">دائن</th></tr>
            </thead>
            <tbody>
                @foreach($adjustment->journalEntry->lines as $line)
                <tr>
                    <td>{{ $line->account->code }} — {{ $line->account->name }}</td>
                    <td class="text-end">{{ $line->debit  > 0 ? number_format($line->debit,  2) . ' ' . $currency : '—' }}</td>
                    <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) . ' ' . $currency : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
</div>
</div>
@endsection
