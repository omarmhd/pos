<div class="d-flex align-items-center gap-3 mb-3">
    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:52px;height:52px">
        <i class="bi bi-person text-muted fs-4"></i>
    </div>
    <div>
        <h5 class="mb-0">{{ $customer->name }}</h5>
        <div class="small text-muted">
            {{ $customer->phone ?? '—' }}
            · {!! $customer->is_active ? '<span class="badge bg-success">نشط</span>' : '<span class="badge bg-secondary">موقوف</span>' !!}
        </div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="border rounded p-2 text-center">
            <div class="small text-muted">الرصيد المستحق</div>
            <div class="fs-6 fw-bold {{ $outstanding > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($outstanding, 2) }} <small>{{ $currency }}</small></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded p-2 text-center">
            <div class="small text-muted">رصيد الإيداع</div>
            <div class="fs-6 fw-bold text-primary">{{ number_format($depositBalance, 2) }} <small>{{ $currency }}</small></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded p-2 text-center">
            <div class="small text-muted">حد الائتمان</div>
            <div class="fs-6 fw-bold">{{ (float)$customer->credit_limit > 0 ? number_format($customer->credit_limit, 2) : '—' }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded p-2 text-center">
            <div class="small text-muted">الائتمان المتاح</div>
            <div class="fs-6 fw-bold text-success">{{ number_format($availableCredit, 2) }} <small>{{ $currency }}</small></div>
        </div>
    </div>
</div>

<table class="table table-sm mb-0">
    <tr>
        <td class="text-muted">إجمالي الفواتير الآجلة</td>
        <td class="text-end fw-semibold">{{ number_format($totalBilled, 2) }} {{ $currency }}</td>
    </tr>
    <tr>
        <td class="text-muted">إجمالي المسدّد</td>
        <td class="text-end fw-semibold text-success">{{ number_format($totalPaid, 2) }} {{ $currency }}</td>
    </tr>
    <tr>
        <td class="text-muted">عدد فواتير البيع (كل الأنواع)</td>
        <td class="text-end">{{ number_format($salesAgg->c) }} · {{ number_format($salesAgg->t, 2) }} {{ $currency }}</td>
    </tr>
</table>
