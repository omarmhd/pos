@extends('layouts.app')
@section('page-title', 'قيد: ' . $je->entry_number)

@section('content')
<div class="row">
    <div class="col-lg-9 mx-auto">

        {{-- Header card --}}
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-journal-text"></i>
                    <span class="font-monospace">{{ $je->entry_number }}</span>
                </h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('journal_entries.pdf', $je->id) }}"
                       class="btn btn-outline-danger btn-sm" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> PDF
                    </a>
                    <a href="{{ route('journal_entries.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-right"></i> السجل
                    </a>
                    @if(is_null($je->source_type))
                    <span class="badge bg-dark align-self-center">قيد يدوي</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="small text-muted">تاريخ القيد</div>
                        <div class="fw-semibold">{{ $je->entry_date?->format('Y-m-d') }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">رقم القيد</div>
                        <div class="fw-semibold font-monospace">{{ $je->entry_number }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">المرجع</div>
                        <div class="fw-semibold">{{ $je->reference ?? '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">بواسطة</div>
                        <div class="fw-semibold">{{ $je->user?->name ?? '—' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">الوصف</div>
                        <div>{{ $je->description }}</div>
                    </div>
                    @if($je->source_type)
                    <div class="col-md-4">
                        <div class="small text-muted">مصدر القيد</div>
                        <div>
                        @php
                            $shortSource = class_basename($je->source_type);
                            $sourceLabel = match($shortSource) {
                                'Sale'            => ['مبيعات',         'success'],
                                'Purchase'        => ['مشتريات',        'primary'],
                                'SupplierPayment' => ['دفعة مورد',      'warning'],
                                'CustomerPayment' => ['تحصيل عميل',     'info'],
                                'PayrollRun'      => ['رواتب',           'secondary'],
                                default           => [$shortSource,     'dark'],
                            };
                        @endphp
                        <span class="badge bg-{{ $sourceLabel[1] }}">{{ $sourceLabel[0] }}</span>
                        <span class="text-muted small">— ID: {{ $je->source_id }}</span>
                        </div>
                    </div>
                    @endif
                    <div class="col-md-3">
                        <div class="small text-muted">وقت الترحيل</div>
                        <div class="small">{{ $je->posted_at?->format('Y-m-d H:i') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Lines --}}
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0">سطور القيد</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:12%">كود الحساب</th>
                            <th>اسم الحساب</th>
                            <th>الجهة</th>
                            <th class="text-end" style="width:14%">مدين</th>
                            <th class="text-end" style="width:14%">دائن</th>
                            <th>البيان</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($je->lines as $line)
                    <tr>
                        <td class="font-monospace small">{{ $line->account?->code }}</td>
                        <td>{{ $line->account?->name }}</td>
                        <td class="small">
                            @if($line->partyLabel())
                                <span class="badge bg-light text-dark border">{{ $line->partyLabel() }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end font-monospace {{ $line->debit > 0 ? 'fw-semibold' : 'text-muted' }}">
                            {{ $line->debit > 0 ? number_format($line->debit, 2) : '—' }}
                        </td>
                        <td class="text-end font-monospace {{ $line->credit > 0 ? 'fw-semibold' : 'text-muted' }}">
                            {{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}
                        </td>
                        <td class="small text-muted">{{ $line->line_description }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="text-end">الإجمالي</td>
                            <td class="text-end font-monospace">{{ number_format($je->debit_total, 2) }}</td>
                            <td class="text-end font-monospace">{{ number_format($je->credit_total, 2) }}</td>
                            <td>
                                @if($je->is_balanced)
                                    <span class="badge bg-success">متوازن</span>
                                @else
                                    <span class="badge bg-danger">
                                        فرق: {{ number_format(abs($je->debit_total - $je->credit_total), 2) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
