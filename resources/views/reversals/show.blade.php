@extends('layouts.app')
@section('title', 'تفاصيل القيد العكسي')
@section('page-title', 'تفاصيل القيد العكسي #{{ $reversal->id }}')

@section('content')
<div class="col-lg-8 mx-auto">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-arrow-counterclockwise"></i> قيد عكسي #{{ $reversal->id }}</span>
            <a href="{{ route('reversals.index') }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-right"></i> رجوع
            </a>
        </div>
        <div class="card-body">

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card bg-light border h-100">
                        <div class="card-body">
                            <h6 class="text-muted small text-uppercase mb-3">المستند الأصلي</h6>
                            <p class="mb-1">
                                <strong>النوع:</strong>
                                <span class="badge bg-secondary">{{ class_basename($reversal->original_type) }}</span>
                            </p>
                            <p class="mb-1"><strong>الرقم:</strong> #{{ $reversal->original_id }}</p>
                            <p class="mb-0"><strong>السبب:</strong> {{ $reversal->reason ?? '—' }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-light border h-100">
                        <div class="card-body">
                            <h6 class="text-muted small text-uppercase mb-3">معلومات القيد</h6>
                            <p class="mb-1">
                                <strong>منشئ القيد:</strong> {{ $reversal->createdBy?->name ?? '—' }}
                            </p>
                            <p class="mb-1">
                                <strong>تاريخ الإنشاء:</strong> {{ $reversal->created_at->format('Y/m/d H:i') }}
                            </p>
                            <p class="mb-0">
                                <strong>القيد المحاسبي:</strong>
                                @if($reversal->reversal_journal_entry_id)
                                    <a href="{{ route('journal_entries.show', $reversal->reversal_journal_entry_id) }}"
                                       class="btn btn-outline-info btn-sm ms-1">
                                        <i class="bi bi-journal-text"></i> عرض القيد
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Journal Entry Details --}}
            @if($reversal->journalEntry)
                <h6 class="fw-bold mb-3"><i class="bi bi-journal-text"></i> تفاصيل القيد العكسي</h6>
                <div class="table-responsive">
                    <table class="table table-bordered small">
                        <thead class="table-dark">
                            <tr>
                                <th>الحساب</th>
                                <th class="text-end">مدين</th>
                                <th class="text-end">دائن</th>
                                <th>البيان</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reversal->journalEntry->lines ?? [] as $line)
                            <tr>
                                <td>{{ $line->account->code ?? '—' }} — {{ $line->account->name ?? '—' }}</td>
                                <td class="text-end {{ $line->debit > 0 ? 'text-primary fw-bold' : 'text-muted' }}">
                                    {{ $line->debit > 0 ? number_format($line->debit, 2) : '—' }}
                                </td>
                                <td class="text-end {{ $line->credit > 0 ? 'text-success fw-bold' : 'text-muted' }}">
                                    {{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}
                                </td>
                                <td class="text-muted">{{ $line->line_description }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-secondary fw-bold">
                            <tr>
                                <td>الإجمالي</td>
                                <td class="text-end text-primary">{{ number_format($reversal->journalEntry->lines->sum('debit'), 2) }}</td>
                                <td class="text-end text-success">{{ number_format($reversal->journalEntry->lines->sum('credit'), 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

        </div>
    </div>
</div>
@endsection
