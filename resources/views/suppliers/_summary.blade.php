<div class="d-flex align-items-center gap-3 mb-3">
    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:52px;height:52px">
        <i class="bi bi-truck text-muted fs-4"></i>
    </div>
    <div>
        <h5 class="mb-0">{{ $supplier->name }}</h5>
        <div class="small text-muted">
            {{ $supplier->company ?? '' }} {{ $supplier->company ? '·' : '' }} {{ $supplier->phone ?? '—' }}
        </div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-4">
        <div class="border rounded p-2 text-center">
            <div class="small text-muted">الرصيد المستحق للمورّد</div>
            <div class="fs-6 fw-bold {{ $outstanding > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($outstanding, 2) }} <small>{{ $currency }}</small></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="border rounded p-2 text-center">
            <div class="small text-muted">إجمالي المشتريات</div>
            <div class="fs-6 fw-bold">{{ number_format($agg->t, 2) }} <small>{{ $currency }}</small></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="border rounded p-2 text-center">
            <div class="small text-muted">إجمالي المسدّد</div>
            <div class="fs-6 fw-bold text-success">{{ number_format($agg->p, 2) }} <small>{{ $currency }}</small></div>
        </div>
    </div>
</div>

<table class="table table-sm mb-0">
    <tr>
        <td class="text-muted">عدد فواتير الشراء</td>
        <td class="text-end fw-semibold">{{ number_format($agg->c) }} فاتورة</td>
    </tr>
    <tr>
        <td class="text-muted">البريد الإلكتروني</td>
        <td class="text-end">{{ $supplier->email ?? '—' }}</td>
    </tr>
    @if($supplier->tax_number)
    <tr>
        <td class="text-muted">الرقم الضريبي</td>
        <td class="text-end font-monospace">{{ $supplier->tax_number }}</td>
    </tr>
    @endif
</table>
