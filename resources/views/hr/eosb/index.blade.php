@extends('layouts.app')
@section('page-title', 'مخصصات نهاية الخدمة')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-person-badge text-warning me-2"></i>مخصصات نهاية الخدمة (EOSB)
    </h4>
    @can('hr.eosb.post')
    <a href="{{ route('hr.eosb.preview', ['year' => now()->year, 'month' => now()->month]) }}"
       class="btn btn-primary btn-sm">
        <i class="bi bi-calculator me-1"></i>
        معاينة وترحيل {{ now()->locale('ar')->isoFormat('MMMM YYYY') }}
    </a>
    @endcan
</div>

{{-- Period selector --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            @include('components.branch-filter')
            <div class="col-auto">
                <label class="form-label small mb-1">السنة</label>
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}" {{ request('year', now()->year) == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto">
                <a href="{{ route('hr.eosb.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>الفترة</th>
                        <th class="text-center">عدد الموظفين</th>
                        <th class="text-end">إجمالي المخصص</th>
                        <th>القيد المحاسبي</th>
                        <th>تاريخ الترحيل</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td>
                            @php
                                $months = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',
                                           5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',
                                           9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
                            @endphp
                            <strong>{{ $months[$run->period_month] ?? $run->period_month }} {{ $run->period_year }}</strong>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary">{{ $run->employee_count }} موظف</span>
                        </td>
                        <td class="text-end font-monospace fw-bold text-warning">
                            {{ number_format($run->total_provision, 2) }} {{ $currency }}
                        </td>
                        <td>
                            @if($run->journal_entry_id)
                                <a href="{{ route('journal_entries.show', $run->journal_entry_id) }}"
                                   class="badge bg-success text-decoration-none">
                                    <i class="bi bi-journal-check me-1"></i>عرض القيد
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-muted small">
                            {{ \Carbon\Carbon::parse($run->posted_at)->format('Y-m-d H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            لم يتم ترحيل أي مخصصات بعد
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($runs->hasPages())
        <div class="card-footer">{{ $runs->links() }}</div>
    @endif
</div>

@if($alreadyPosted)
<div class="alert alert-info mt-3">
    <i class="bi bi-check-circle me-1"></i>
    مخصصات شهر {{ now()->locale('ar')->isoFormat('MMMM YYYY') }} مُرحَّلة بالفعل.
</div>
@endif
@endsection
