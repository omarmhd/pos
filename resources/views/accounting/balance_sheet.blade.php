@extends('layouts.app')

@section('title', 'الميزانية العمومية')
@section('page-title', 'الميزانية العمومية')

@section('content')
<div class="container-fluid">

    {{-- Date Filter --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">بتاريخ</label>
                    <input type="date" name="as_of" class="form-control" value="{{ $asOf->toDateString() }}">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> عرض
                    </button>
                </div>
                <div class="col-md-4 text-muted small pt-2">
                    * صافي الربح المحتسب من {{ $ytdFrom->format('Y/m/d') }} حتى {{ $asOf->format('Y/m/d') }}
                </div>
            </form>
        </div>
    </div>

    {{-- Balance indicator --}}
    @if($isBalanced)
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <strong>الميزانية متوازنة</strong> — إجمالي الأصول يساوي إجمالي الالتزامات وحقوق الملكية
    </div>
    @else
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <strong>تحذير: الميزانية غير متوازنة</strong> — الفرق: {{ number_format($difference, 2) }}
        <small class="ms-2 text-muted">(تحقق من القيود غير المرحّلة أو الحسابات الناقصة)</small>
    </div>
    @endif

    <div class="row g-4">

        {{-- ============================== ASSETS (right column in Arabic layout) ============================== --}}
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h5 class="mb-0"><i class="bi bi-building"></i> الأصول</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">

                        {{-- Current Assets --}}
                        <thead class="table-primary">
                            <tr><th colspan="2" class="ps-3 py-2">الأصول المتداولة</th></tr>
                        </thead>
                        <tbody>
                            @forelse($currentAssets as $row)
                            <tr>
                                <td class="ps-4 text-muted">
                                    <span class="badge bg-secondary me-1">{{ $row['account']->code }}</span>
                                    {{ $row['account']->name }}
                                </td>
                                <td class="text-end pe-3 fw-semibold">{{ number_format($row['amount'], 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="2" class="text-center text-muted py-2">—</td></tr>
                            @endforelse
                            <tr class="table-primary fw-bold">
                                <td class="ps-3">إجمالي الأصول المتداولة</td>
                                <td class="text-end pe-3">{{ number_format($totalCurrentAssets, 2) }}</td>
                            </tr>
                        </tbody>

                        {{-- Fixed Assets --}}
                        <thead class="table-info">
                            <tr><th colspan="2" class="ps-3 py-2">الأصول الثابتة</th></tr>
                        </thead>
                        <tbody>
                            @forelse($fixedAssets as $row)
                            <tr>
                                <td class="ps-4 text-muted">
                                    <span class="badge bg-secondary me-1">{{ $row['account']->code }}</span>
                                    {{ $row['account']->name }}
                                </td>
                                <td class="text-end pe-3 fw-semibold">{{ number_format($row['amount'], 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="2" class="text-center text-muted py-2">—</td></tr>
                            @endforelse
                            <tr class="table-info fw-bold">
                                <td class="ps-3">إجمالي الأصول الثابتة</td>
                                <td class="text-end pe-3">{{ number_format($totalFixedAssets, 2) }}</td>
                            </tr>
                        </tbody>

                        {{-- Total Assets --}}
                        <tbody>
                            <tr class="fw-bold fs-6 bg-primary text-white border-top border-3">
                                <td class="ps-3 py-3">إجمالي الأصول</td>
                                <td class="text-end pe-3 py-3">{{ number_format($totalAssets, 2) }}</td>
                            </tr>
                        </tbody>

                    </table>
                </div>
            </div>
        </div>

        {{-- ============================== LIABILITIES & EQUITY ============================== --}}
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-danger text-white text-center py-3">
                    <h5 class="mb-0"><i class="bi bi-bank"></i> الالتزامات وحقوق الملكية</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">

                        {{-- Current Liabilities --}}
                        <thead class="table-danger">
                            <tr><th colspan="2" class="ps-3 py-2">الالتزامات المتداولة</th></tr>
                        </thead>
                        <tbody>
                            @forelse($currentLiabilities as $row)
                            <tr>
                                <td class="ps-4 text-muted">
                                    <span class="badge bg-secondary me-1">{{ $row['account']->code }}</span>
                                    {{ $row['account']->name }}
                                </td>
                                <td class="text-end pe-3 fw-semibold">{{ number_format($row['amount'], 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="2" class="text-center text-muted py-2">—</td></tr>
                            @endforelse
                            <tr class="table-danger fw-bold">
                                <td class="ps-3">إجمالي الالتزامات المتداولة</td>
                                <td class="text-end pe-3">{{ number_format($totalCurrentLiabilities, 2) }}</td>
                            </tr>
                        </tbody>

                        {{-- Long-term Liabilities --}}
                        <thead class="table-secondary">
                            <tr><th colspan="2" class="ps-3 py-2">الالتزامات طويلة الأجل</th></tr>
                        </thead>
                        <tbody>
                            @forelse($longTermLiabilities as $row)
                            <tr>
                                <td class="ps-4 text-muted">
                                    <span class="badge bg-secondary me-1">{{ $row['account']->code }}</span>
                                    {{ $row['account']->name }}
                                </td>
                                <td class="text-end pe-3 fw-semibold">{{ number_format($row['amount'], 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="2" class="text-center text-muted py-2">—</td></tr>
                            @endforelse
                            <tr class="table-secondary fw-bold">
                                <td class="ps-3">إجمالي الالتزامات طويلة الأجل</td>
                                <td class="text-end pe-3">{{ number_format($totalLongTermLiabilities, 2) }}</td>
                            </tr>
                        </tbody>

                        {{-- Total Liabilities --}}
                        <tbody>
                            <tr class="fw-bold bg-danger bg-opacity-10 border-top border-2">
                                <td class="ps-3 py-2 text-danger">إجمالي الالتزامات</td>
                                <td class="text-end pe-3 py-2 text-danger">{{ number_format($totalLiabilities, 2) }}</td>
                            </tr>
                        </tbody>

                        {{-- Equity --}}
                        <thead class="table-success">
                            <tr><th colspan="2" class="ps-3 py-2">حقوق الملكية</th></tr>
                        </thead>
                        <tbody>
                            @forelse($equityAccounts as $row)
                            <tr>
                                <td class="ps-4 text-muted">
                                    <span class="badge bg-secondary me-1">{{ $row['account']->code }}</span>
                                    {{ $row['account']->name }}
                                </td>
                                <td class="text-end pe-3 fw-semibold">{{ number_format($row['amount'], 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="2" class="text-center text-muted py-2">—</td></tr>
                            @endforelse

                            {{-- Net Income as retained earnings --}}
                            <tr class="{{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}">
                                <td class="ps-4">
                                    <i class="bi bi-arrow-return-left me-1"></i>
                                    {{ $netIncome >= 0 ? 'صافي ربح الفترة' : 'صافي خسارة الفترة' }}
                                    <small class="text-muted">({{ $ytdFrom->format('Y/m/d') }} – {{ $asOf->format('Y/m/d') }})</small>
                                </td>
                                <td class="text-end pe-3 fw-semibold">{{ number_format($netIncome, 2) }}</td>
                            </tr>

                            <tr class="table-success fw-bold">
                                <td class="ps-3">إجمالي حقوق الملكية</td>
                                <td class="text-end pe-3">{{ number_format($totalEquity, 2) }}</td>
                            </tr>
                        </tbody>

                        {{-- Total Liabilities & Equity --}}
                        <tbody>
                            <tr class="fw-bold fs-6 bg-danger text-white border-top border-3">
                                <td class="ps-3 py-3">إجمالي الالتزامات وحقوق الملكية</td>
                                <td class="text-end pe-3 py-3">{{ number_format($totalLiabAndEquity, 2) }}</td>
                            </tr>
                        </tbody>

                    </table>
                </div>
            </div>
        </div>

    </div>{{-- end row --}}

    {{-- Summary bar --}}
    <div class="card mt-4">
        <div class="card-body">
            <div class="row text-center g-2">
                <div class="col-md-4">
                    <div class="p-2 border rounded">
                        <div class="fw-bold text-primary fs-5">{{ number_format($totalAssets, 2) }}</div>
                        <small class="text-muted">إجمالي الأصول</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 border rounded">
                        <div class="fw-bold text-danger fs-5">{{ number_format($totalLiabilities, 2) }}</div>
                        <small class="text-muted">إجمالي الالتزامات</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 border rounded">
                        <div class="fw-bold text-success fs-5">{{ number_format($totalEquity, 2) }}</div>
                        <small class="text-muted">إجمالي حقوق الملكية</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
