@extends('layouts.app')
@section('page-title', 'التحويلات البينية بين الفروع')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-arrow-left-right text-primary me-2"></i>التحويلات البينية بين الفروع
    </h4>
    <a href="{{ route('inter-branch.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> تحويل جديد
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 dt-table" style="width:100%"
                   data-title="التحويلات البينية">
                <thead class="table-dark">
                    <tr>
                        <th>التاريخ</th>
                        <th>من فرع</th>
                        <th>إلى فرع</th>
                        <th class="text-end">المبلغ</th>
                        <th>المرجع</th>
                        <th>الوصف</th>
                        <th>بواسطة</th>
                        <th class="text-center">القيود</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($transfers as $t)
                <tr>
                    <td>{{ $t->transfer_date->format('Y-m-d') }}</td>
                    <td><span class="badge bg-danger bg-opacity-75">{{ $t->fromBranch?->name }}</span></td>
                    <td><span class="badge bg-success bg-opacity-75">{{ $t->toBranch?->name }}</span></td>
                    <td class="text-end font-monospace fw-bold">{{ number_format($t->amount, 2) }} {{ $currency }}</td>
                    <td class="text-muted small">{{ $t->reference ?? '—' }}</td>
                    <td class="text-muted small">{{ Str::limit($t->description ?? '', 40) }}</td>
                    <td class="text-muted small">{{ $t->createdBy?->name ?? '—' }}</td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                            @if($t->from_journal_entry_id)
                                <a href="{{ route('journal_entries.show', $t->from_journal_entry_id) }}"
                                   class="badge bg-danger text-decoration-none" title="قيد الفرع المرسل">
                                    <i class="bi bi-journal-text"></i> صادر
                                </a>
                            @endif
                            @if($t->to_journal_entry_id)
                                <a href="{{ route('journal_entries.show', $t->to_journal_entry_id) }}"
                                   class="badge bg-success text-decoration-none" title="قيد الفرع المستلم">
                                    <i class="bi bi-journal-text"></i> وارد
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @if($transfers->hasPages())
    <div class="card-footer">{{ $transfers->links() }}</div>
    @endif
</div>
@endsection
