@extends('layouts.app')
@section('page-title', 'معاينة مخصصات نهاية الخدمة')

@section('content')
@php
    $monthNames = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',
                   5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',
                   9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
    $periodLabel = ($monthNames[$month] ?? $month) . ' ' . $year;
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-calculator text-primary me-2"></i>
        مخصصات نهاية الخدمة — {{ $periodLabel }}
    </h4>
    <a href="{{ route('hr.eosb.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-right"></i> رجوع
    </a>
</div>

{{-- Period selector --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">السنة</label>
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">الشهر</label>
                <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($monthNames as $m => $name)
                        <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

{{-- Summary KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card primary">
            <h6>عدد الموظفين</h6>
            <h3>{{ $lines->count() }}</h3>
            <small>موظف نشط</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <h6>إجمالي المخصص</h6>
            <h3>{{ number_format($totalProvision, 2) }}</h3>
            <small>{{ $currency }} — هذا الشهر</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-info h-100">
            <div class="card-body py-3">
                <div class="small fw-bold text-info mb-1"><i class="bi bi-journal-text me-1"></i>القيد المحاسبي المتوقع</div>
                <div class="d-flex justify-content-between small">
                    <div>
                        <span class="text-danger">● مدين</span> مصروف نهاية خدمة (6300)
                    </div>
                    <strong>{{ number_format($totalProvision, 2) }} {{ $currency }}</strong>
                </div>
                <div class="d-flex justify-content-between small mt-1">
                    <div>
                        <span class="text-success">● دائن</span> مخصص نهاية خدمة (2200)
                    </div>
                    <strong>{{ number_format($totalProvision, 2) }} {{ $currency }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>

@if($alreadyPosted)
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i>
    مخصصات {{ $periodLabel }} مُرحَّلة بالفعل — لا يمكن الترحيل مرة أخرى.
</div>
@endif

{{-- Employee breakdown --}}
<div class="card mb-4">
    <div class="card-header bg-white">
        <strong>تفصيل المخصصات لكل موظف</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0 dt-table" style="width:100%"
                   data-title="مخصصات نهاية الخدمة — {{ $periodLabel }}">
                <thead class="table-light">
                    <tr>
                        <th>الموظف</th>
                        <th>المسمى الوظيفي</th>
                        <th class="text-center">مدة الخدمة</th>
                        <th class="text-end">الراتب الأساسي</th>
                        <th class="text-end">المخصص الشهري</th>
                        <th class="text-end">الإجمالي التراكمي</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($lines as $line)
                <tr>
                    <td>
                        <strong>{{ $line['employee']->name }}</strong>
                        <div class="text-muted small">{{ $line['employee']->employee_code }}</div>
                    </td>
                    <td class="text-muted small">{{ $line['employee']->job_title ?? '—' }}</td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border">
                            {{ floor($line['service_months'] / 12) }} سنة
                            @if($line['service_months'] % 12)
                                و {{ $line['service_months'] % 12 }} شهر
                            @endif
                        </span>
                    </td>
                    <td class="text-end font-monospace">{{ number_format($line['base_salary'], 2) }}</td>
                    <td class="text-end font-monospace fw-bold text-warning">
                        {{ number_format($line['provision'], 2) }}
                    </td>
                    <td class="text-end font-monospace text-muted">
                        {{ number_format($line['cumulative'], 2) }}
                    </td>
                </tr>
                @endforeach
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="4">الإجمالي ({{ $lines->count() }} موظف)</td>
                        <td class="text-end font-monospace text-warning">{{ number_format($totalProvision, 2) }} {{ $currency }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@can('hr.eosb.post')
@unless($alreadyPosted)
<form action="{{ route('hr.eosb.post') }}" method="POST"
      onsubmit="return confirm('ترحيل مخصصات نهاية الخدمة لـ {{ $periodLabel }}؟\nإجمالي: {{ number_format($totalProvision, 2) }} {{ $currency }}\nلا يمكن التراجع عن هذا الإجراء.')">
    @csrf
    <input type="hidden" name="year"  value="{{ $year }}">
    <input type="hidden" name="month" value="{{ $month }}">
    <button type="submit" class="btn btn-warning btn-lg">
        <i class="bi bi-send-check me-1"></i>
        ترحيل المخصصات وإنشاء القيد المحاسبي
    </button>
    <a href="{{ route('hr.eosb.index') }}" class="btn btn-outline-secondary ms-2">إلغاء</a>
</form>
@endunless
@endcan
@endsection
