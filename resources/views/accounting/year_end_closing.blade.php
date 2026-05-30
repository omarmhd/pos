@extends('layouts.app')

@section('page-title', 'إقفال نهاية السنة')

@section('content')

<div class="row justify-content-center">
<div class="col-lg-8">

    {{-- Year selector --}}
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-calendar-check"></i> إقفال السنة المالية</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">اختر السنة</label>
                    <input type="number" name="year" class="form-control"
                           value="{{ $year }}" min="2000" max="{{ now()->year }}">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-primary w-100"><i class="bi bi-eye"></i> معاينة</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Already closed warning --}}
    @if($alreadyClosed)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        تم إقفال سنة <strong>{{ $year }}</strong> مسبقاً. يمكنك الاطلاع على قيد الإقفال في دفتر اليومية.
    </div>
    @endif

    {{-- Income summary for the year --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center border-success">
                <div class="card-body py-3">
                    <div class="small text-muted">إجمالي الإيرادات</div>
                    <div class="fw-bold fs-5 text-success">{{ number_format($totalRevenue, 2) }} {{ $currency }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-danger">
                <div class="card-body py-3">
                    <div class="small text-muted">إجمالي المصروفات</div>
                    <div class="fw-bold fs-5 text-danger">{{ number_format($totalCogs + $totalOpex, 2) }} {{ $currency }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-{{ $netIncome >= 0 ? 'primary' : 'warning' }}">
                <div class="card-body py-3">
                    <div class="small text-muted">صافي {{ $netIncome >= 0 ? 'الربح' : 'الخسارة' }}</div>
                    <div class="fw-bold fs-5 text-{{ $netIncome >= 0 ? 'primary' : 'warning' }}">
                        {{ number_format(abs($netIncome), 2) }} {{ $currency }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Accounts to be closed --}}
    @if($revenue || $cogs || $opex)
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0">الحسابات التي سيتم إقفالها</h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th>الكود</th><th>الحساب</th><th>النوع</th><th class="text-end">الرصيد</th><th>القيد</th></tr>
                </thead>
                <tbody>
                    @foreach($revenue as $r)
                    <tr>
                        <td class="text-muted small">{{ $r['account']->code }}</td>
                        <td>{{ $r['account']->name }}</td>
                        <td><span class="badge bg-success">إيراد</span></td>
                        <td class="text-end">{{ number_format($r['amount'], 2) }}</td>
                        <td class="text-muted small">مدين (إقفال)</td>
                    </tr>
                    @endforeach
                    @foreach(array_merge($cogs, $opex) as $e)
                    <tr>
                        <td class="text-muted small">{{ $e['account']->code }}</td>
                        <td>{{ $e['account']->name }}</td>
                        <td><span class="badge bg-danger">مصروف</span></td>
                        <td class="text-end">{{ number_format($e['amount'], 2) }}</td>
                        <td class="text-muted small">دائن (إقفال)</td>
                    </tr>
                    @endforeach
                    <tr class="table-{{ $netIncome >= 0 ? 'primary' : 'warning' }} fw-bold">
                        <td colspan="3">الأرباح المحتجزة (3100)</td>
                        <td class="text-end">{{ number_format(abs($netIncome), 2) }}</td>
                        <td class="small">{{ $netIncome >= 0 ? 'دائن (ربح)' : 'مدين (خسارة)' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Confirmation form --}}
    @if(!$alreadyClosed)
    <div class="card border-danger">
        <div class="card-header bg-danger text-white">
            <i class="bi bi-exclamation-triangle"></i> تأكيد الإقفال
        </div>
        <div class="card-body">
            <div class="alert alert-warning mb-3">
                <strong>تحذير:</strong> عملية الإقفال لا يمكن التراجع عنها. تأكد من مراجعة جميع القيود قبل المتابعة.
                سيتم إنشاء قيد يومية يُصفر أرصدة الإيرادات والمصروفات ويُحوّلها إلى حساب الأرباح المحتجزة.
            </div>
            <form method="POST" action="{{ route('accounting.year-end-closing.store') }}"
                  onsubmit="return confirm('هل أنت متأكد من إقفال سنة {{ $year }}؟ لا يمكن التراجع عن هذه العملية.')">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-lock-fill"></i> تنفيذ الإقفال السنوي لسنة {{ $year }}
                </button>
                <a href="{{ route('accounting.index') }}" class="btn btn-outline-secondary ms-2">إلغاء</a>
            </form>
        </div>
    </div>
    @endif

    @else
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i>
        لا توجد أرصدة إيرادات أو مصروفات مرحّلة لسنة {{ $year }}. تأكد من أن القيود قد تم ترحيلها.
    </div>
    @endif

</div>
</div>
@endsection
