@extends('layouts.app')
@section('page-title', 'تعديلات المخزون')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-box-seam"></i> تعديلات المخزون</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('inventory.sessions.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-clipboard-check"></i> جلسات الجرد
        </a>
        <a href="{{ route('inventory.adjustments.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> تعديل جديد
        </a>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">— كل المنتجات —</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>
                            {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="reason" class="form-select form-select-sm">
                    <option value="">— كل الأسباب —</option>
                    @foreach(\App\Models\InventoryAdjustment::$reasons as $k => $v)
                        <option value="{{ $k }}" {{ request('reason') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-funnel"></i> تصفية
                </button>
                @if(request()->hasAny(['product_id','reason']))
                    <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-outline-danger btn-sm ms-1">
                        <i class="bi bi-x"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-dark">
                <tr>
                    <th>التاريخ</th>
                    <th>المنتج</th>
                    <th class="text-center">قبل</th>
                    <th class="text-center">بعد</th>
                    <th class="text-center">الفرق</th>
                    <th>السبب</th>
                    <th class="text-end">القيمة</th>
                    <th>المستخدم</th>
                    <th class="text-center">قيد</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($adjustments as $adj)
                <tr>
                    <td class="text-muted">{{ $adj->created_at->format('Y/m/d H:i') }}</td>
                    <td>
                        <strong>{{ $adj->product->name }}</strong>
                        @if($adj->inventory_session_id)
                            <span class="badge bg-info ms-1">جرد</span>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format($adj->quantity_before, 2) }}</td>
                    <td class="text-center">{{ number_format($adj->quantity_after, 2) }}</td>
                    <td class="text-center">
                        <span class="fw-bold {{ $adj->quantity_change < 0 ? 'text-danger' : 'text-success' }}">
                            {{ $adj->quantity_change > 0 ? '+' : '' }}{{ number_format($adj->quantity_change, 2) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-secondary">
                            {{ \App\Models\InventoryAdjustment::$reasons[$adj->reason] ?? $adj->reason }}
                        </span>
                    </td>
                    <td class="text-end">
                        <span class="{{ $adj->quantity_change < 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($adj->total_cost, 2) }} {{ $currency }}
                        </span>
                    </td>
                    <td class="text-muted">{{ $adj->createdBy?->name ?? '—' }}</td>
                    <td>
                        @if($adj->journalEntry)
                            <a href="{{ route('journal_entries.show', $adj->journal_entry_id) }}"
                               class="text-success text-decoration-none font-monospace small" target="_blank">
                                <i class="bi bi-journal-check me-1"></i>{{ $adj->journalEntry->entry_number }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('inventory.adjustments.show', $adj) }}"
                           class="btn btn-sm btn-outline-secondary btn-action">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">لا توجد تعديلات</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @if($adjustments->hasPages())
    <div class="card-footer">{{ $adjustments->links() }}</div>
    @endif
</div>
@endsection
