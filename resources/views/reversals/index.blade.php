@extends('layouts.app')
@section('title', 'قيود التصحيح')
@section('page-title', 'قيود التصحيح والعكوس')

@section('content')
<div class="container-fluid">

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-arrow-counterclockwise"></i> سجل القيود العكسية</span>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                {{-- Branch filter for global users --}}
                @if(!($branchLocked ?? false) && isset($branches) && $branches->count() >= 1)
                <form method="GET" class="d-flex gap-1 align-items-center mb-0">
                    <select name="branch_id" class="form-select form-select-sm" style="min-width:140px" onchange="this.form.submit()">
                        <option value="">كل الفروع</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ ($branchId ?? null) == $b->id ? 'selected':'' }}>
                                {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                    @if(request('branch_id'))
                        <a href="{{ route('reversals.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x"></i>
                        </a>
                    @endif
                </form>
                @endif
                <a href="{{ route('reversals.create') }}" class="btn btn-danger btn-sm">
                    <i class="bi bi-plus-circle"></i> قيد عكسي جديد
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            @if($reversals->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="bi bi-check2-circle fs-1"></i>
                    <p class="mt-2">لا توجد قيود تصحيح مسجلة</p>
                </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 small">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>المرجع الأصلي</th>
                            <th>منشئ القيد</th>
                            <th>السبب</th>
                            <th>القيد العكسي</th>
                            <th>التاريخ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reversals as $r)
                        <tr>
                            <td class="text-muted">{{ $r->id }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ class_basename($r->original_type) }}</span>
                                #{{ $r->original_id }}
                            </td>
                            <td>{{ $r->createdBy?->name ?? '—' }}</td>
                            <td>{{ $r->reason ?? '—' }}</td>
                            <td>
                                @if($r->reversal_journal_entry_id)
                                    <a href="{{ route('journal_entries.show', $r->reversal_journal_entry_id) }}"
                                       class="badge bg-info text-decoration-none">
                                        <i class="bi bi-journal-text"></i> عرض القيد
                                    </a>
                                @endif
                            </td>
                            <td class="text-muted">{{ $r->created_at->format('Y/m/d H:i') }}</td>
                            <td>
                                <a href="{{ route('reversals.show', $r->id) }}"
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $reversals->links() }}
            </div>
            @endif
        </div>
    </div>

</div>
@endsection
