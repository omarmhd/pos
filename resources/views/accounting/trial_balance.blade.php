@extends('layouts.app')
@section('page-title', 'ميزان المراجعة')

@php
$typeMap = [
    'asset'     => ['أصل',        'primary'],
    'liability' => ['التزام',      'warning'],
    'equity'    => ['حقوق ملكية', 'info'],
    'revenue'   => ['إيراد',       'success'],
    'expense'   => ['مصروف',       'danger'],
];
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-table"></i> ميزان المراجعة</h4>
    <a href="{{ route('accounting.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-right"></i> لوحة المحاسبة
    </a>
</div>

{{-- Date Filter --}}
<div class="card mb-3 border-0 shadow-sm">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">حتى تاريخ</label>
                <input type="date" name="as_of" class="form-control form-control-sm"
                       value="{{ $asOf->toDateString() }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> عرض
                </button>
                <a href="{{ route('accounting.trial-balance') }}" class="btn btn-outline-secondary btn-sm">
                    اليوم
                </a>
            </div>
            <div class="col-auto ms-auto">
                @if($isBalanced)
                    <span class="badge bg-success fs-6 px-3 py-2">
                        <i class="bi bi-check-circle-fill me-1"></i> الميزان متوازن
                    </span>
                @else
                    <span class="badge bg-danger fs-6 px-3 py-2">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> الميزان غير متوازن
                    </span>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:100px">الكود</th>
                        <th>اسم الحساب</th>
                        <th style="width:110px">النوع</th>
                        <th style="width:150px" class="text-end">مدين</th>
                        <th style="width:150px" class="text-end">دائن</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                <tr @if($row->abnormal) class="table-warning" @endif>
                    <td><code>{{ $row->code }}</code></td>
                    <td>
                        {{ $row->name }}
                        @if($row->abnormal)
                            <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">رصيد عكسي</span>
                        @endif
                    </td>
                    <td>
                        @php [$label, $color] = $typeMap[$row->type] ?? [$row->type, 'secondary']; @endphp
                        <span class="badge bg-{{ $color }}">{{ $label }}</span>
                    </td>
                    <td class="text-end font-monospace">
                        @if($row->debit_col > 0)
                            {{ number_format($row->debit_col, 2) }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end font-monospace">
                        @if($row->credit_col > 0)
                            {{ number_format($row->credit_col, 2) }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                        لا توجد حسابات بحركات حتى التاريخ المحدد
                    </td>
                </tr>
                @endforelse
                </tbody>
                <tfoot class="table-dark fw-bold">
                    <tr>
                        <td colspan="3" class="text-end">الإجمالي</td>
                        <td class="text-end font-monospace">{{ number_format($totalDebitCol, 2) }}</td>
                        <td class="text-end font-monospace">{{ number_format($totalCreditCol, 2) }}</td>
                    </tr>
                    @if(!$isBalanced)
                    <tr class="table-danger">
                        <td colspan="3" class="text-end text-danger">الفرق (خطأ في القيود)</td>
                        <td colspan="2" class="text-end font-monospace text-danger">
                            {{ number_format(abs($totalDebitCol - $totalCreditCol), 2) }}
                        </td>
                    </tr>
                    @endif
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
