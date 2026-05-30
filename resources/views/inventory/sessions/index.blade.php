@extends('layouts.app')
@section('page-title', 'جلسات الجرد')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-clipboard-check"></i> جلسات الجرد الدورية</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-box-seam"></i> التعديلات اليدوية
        </a>
        <a href="{{ route('inventory.sessions.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> جلسة جرد جديدة
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>المرجع</th>
                    <th>العنوان</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center">المنتجات</th>
                    <th class="text-center">مُعدّ</th>
                    <th class="text-center">فروقات</th>
                    <th>تاريخ الإنشاء</th>
                    <th>اعتمد من</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions as $session)
                @php
                    $total    = $session->total_items;
                    $counted  = $session->counted_items;
                    $varCount = $session->variance_items;
                @endphp
                <tr>
                    <td><strong class="font-monospace">{{ $session->reference }}</strong></td>
                    <td>{{ $session->title }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ $session->statusColor() }}">{{ $session->statusLabel() }}</span>
                    </td>
                    <td class="text-center">{{ $total }}</td>
                    <td class="text-center">
                        @if($session->status === 'in_progress')
                            <span class="{{ $counted === $total ? 'text-success fw-bold' : 'text-warning' }}">
                                {{ $counted }}/{{ $total }}
                            </span>
                        @else
                            {{ $counted }}
                        @endif
                    </td>
                    <td class="text-center">
                        @if($varCount > 0)
                            <span class="badge bg-danger">{{ $varCount }} فرق</span>
                        @else
                            <span class="text-success">—</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $session->created_at->format('Y/m/d') }}</td>
                    <td class="text-muted small">{{ $session->approvedBy?->name ?? '—' }}</td>
                    <td>
                        <a href="{{ route('inventory.sessions.show', $session) }}"
                           class="btn btn-sm btn-outline-primary">
                            {{ $session->status === 'in_progress' ? 'متابعة' : 'عرض' }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">لا توجد جلسات جرد</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @if($sessions->hasPages())
    <div class="card-footer">{{ $sessions->links() }}</div>
    @endif
</div>
@endsection
