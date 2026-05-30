@extends('layouts.app')
@section('page-title', 'جلسة جرد — ' . $session->reference)

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="bi bi-clipboard-check"></i> {{ $session->title }}</h4>
        <small class="text-muted">{{ $session->reference }}
            <span class="badge bg-{{ $session->statusColor() }} ms-1">{{ $session->statusLabel() }}</span>
        </small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($session->status === 'in_progress')
            <form method="POST" action="{{ route('inventory.sessions.cancel', $session) }}"
                  onsubmit="return confirm('إلغاء جلسة الجرد؟')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-x-circle"></i> إلغاء الجلسة
                </button>
            </form>
            <form method="POST" action="{{ route('inventory.sessions.approve', $session) }}"
                  onsubmit="return confirm('هل تريد اعتماد الجرد؟ سيتم تحديث المخزون وإنشاء القيود المحاسبية.')">
                @csrf
                <button class="btn btn-sm btn-success">
                    <i class="bi bi-check-circle"></i> اعتماد الجرد
                </button>
            </form>
        @endif
        <a href="{{ route('inventory.sessions.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>
</div>

{{-- Stats from DB aggregate (single query, not PHP collection iteration) --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card text-center py-2">
            <div class="fs-4 fw-bold">{{ $stats->total }}</div>
            <div class="small text-muted">إجمالي المنتجات</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-2 {{ $stats->counted == $stats->total ? 'border-success' : 'border-warning' }}">
            <div class="fs-4 fw-bold {{ $stats->counted == $stats->total ? 'text-success' : 'text-warning' }}">{{ $stats->counted }}/{{ $stats->total }}</div>
            <div class="small text-muted">تم عدّه</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-2 {{ $stats->shortage_val > 0 ? 'border-danger' : '' }}">
            <div class="fs-4 fw-bold text-danger">{{ number_format($stats->shortage_val, 2) }} {{ $currency }}</div>
            <div class="small text-muted">إجمالي العجز</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-2 {{ $stats->surplus_val > 0 ? 'border-success' : '' }}">
            <div class="fs-4 fw-bold text-success">{{ number_format($stats->surplus_val, 2) }} {{ $currency }}</div>
            <div class="small text-muted">إجمالي الزيادة</div>
        </div>
    </div>
</div>

{{-- GL entry link if approved --}}
@if($session->journal_entry_id)
<div class="alert alert-success mb-3">
    <i class="bi bi-journal-check"></i>
    تم اعتماد الجرد وإنشاء القيد المحاسبي.
    <a href="{{ route('journal_entries.show', $session->journal_entry_id) }}" class="alert-link">
        عرض القيد
    </a>
    — اعتمد بواسطة {{ $session->approvedBy?->name }} في {{ $session->approved_at?->format('Y/m/d H:i') }}
</div>
@endif

{{-- Barcode scanner (only in_progress) --}}
@if($session->status === 'in_progress')
<div class="card mb-3 border-primary">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-upc-scan fs-3 text-primary"></i>
            <div class="flex-grow-1">
                <input type="text" id="scanInput" class="form-control"
                       placeholder="امسح الباركود هنا للانتقال المباشر للمنتج..."
                       autocomplete="off">
            </div>
            <span id="scanStatus" class="text-muted small"></span>
        </div>
    </div>
</div>
@endif

{{-- Filters --}}
<form method="GET" class="card mb-2">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="category" class="form-select form-select-sm">
                    <option value="">— كل الفئات —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="filter" class="form-select form-select-sm">
                    <option value="">— كل المنتجات —</option>
                    <option value="uncounted" {{ request('filter')=='uncounted' ? 'selected' : '' }}>لم يُعدّ بعد</option>
                    <option value="shortage"  {{ request('filter')=='shortage'  ? 'selected' : '' }}>عجز فقط</option>
                    <option value="surplus"   {{ request('filter')=='surplus'   ? 'selected' : '' }}>زيادة فقط</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-funnel"></i> تصفية
                </button>
                @if(request()->hasAny(['category','filter']))
                    <a href="{{ route('inventory.sessions.show', $session) }}" class="btn btn-sm btn-outline-danger ms-1">
                        <i class="bi bi-x"></i>
                    </a>
                @endif
            </div>
            <div class="col-auto ms-auto text-muted small">
                عرض {{ $items->firstItem() }}–{{ $items->lastItem() }} من {{ $items->total() }} منتج
            </div>
        </div>
    </div>
</form>

{{-- Items table --}}
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small" id="itemsTable">
            <thead class="table-dark">
                <tr>
                    <th>المنتج</th>
                    <th class="d-none d-md-table-cell">الفئة</th>
                    <th class="text-center">كمية النظام</th>
                    <th class="text-center" style="min-width:130px">الكمية الفعلية</th>
                    <th class="text-center">الفرق</th>
                    <th class="text-end d-none d-md-table-cell">قيمة الفرق</th>
                    <th class="d-none d-md-table-cell">ملاحظات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr id="row-{{ $item->product_id }}"
                    class="{{ $item->counted_quantity !== null && $item->difference != 0 ? ($item->difference < 0 ? 'table-danger' : 'table-success') . ' bg-opacity-25' : '' }}">
                    <td>
                        <strong>{{ $item->product->name }}</strong>
                        @if($item->product->barcode)
                            <small class="text-muted d-block">{{ $item->product->barcode }}</small>
                        @endif
                    </td>
                    <td class="d-none d-md-table-cell text-muted">{{ $item->product->category?->name ?? '—' }}</td>
                    <td class="text-center">{{ number_format($item->system_quantity, 3) }}</td>
                    <td class="text-center">
                        @if($session->status === 'in_progress')
                            <input type="number"
                                   id="qty-{{ $item->product_id }}"
                                   class="form-control form-control-sm text-center count-input"
                                   data-product="{{ $item->product_id }}"
                                   min="0" step="0.001"
                                   value="{{ $item->counted_quantity ?? '' }}"
                                   placeholder="0">
                        @else
                            {{ $item->counted_quantity !== null ? number_format($item->counted_quantity, 3) : '—' }}
                        @endif
                    </td>
                    <td class="text-center" id="diff-{{ $item->product_id }}">
                        @if($item->counted_quantity !== null)
                            <span class="{{ $item->difference < 0 ? 'text-danger' : ($item->difference > 0 ? 'text-success' : 'text-muted') }} fw-bold">
                                {{ $item->difference > 0 ? '+' : '' }}{{ number_format($item->difference, 3) }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end d-none d-md-table-cell" id="val-{{ $item->product_id }}">
                        @if($item->counted_quantity !== null && $item->difference != 0)
                            <span class="{{ $item->difference < 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format(abs($item->difference * $item->cost_per_unit), 2) }} {{ $currency }}
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="d-none d-md-table-cell text-muted small">{{ $item->notes ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @if($items->hasPages())
    <div class="card-footer">
        {{ $items->links() }}
    </div>
    @endif
</div>

@endsection

@if($session->status === 'in_progress')
@section('scripts')
<script>
const SESSION_ID   = {{ $session->id }};
const CSRF         = document.querySelector('meta[name=csrf-token]').content;
const CUR          = '{{ $currency }}';
let saveTimer = {};

// Barcode scan → focus matching row's input
document.getElementById('scanInput').addEventListener('keydown', async function(e) {
    if (e.key !== 'Enter') return;
    const barcode = this.value.trim();
    if (!barcode) return;
    this.value = '';

    try {
        const res = await fetch(`/inventory/sessions/${SESSION_ID}/scan`, {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json' },
            body: JSON.stringify({ barcode }),
        });
        const data = await res.json();
        if (!res.ok) {
            document.getElementById('scanStatus').textContent = '❌ ' + (data.error || 'غير موجود');
            return;
        }
        const input = document.getElementById('qty-' + data.product_id);
        if (input) {
            input.focus();
            input.select();
            input.scrollIntoView({ behavior:'smooth', block:'center' });
            document.getElementById('scanStatus').textContent = '✅ ' + data.name;
        }
    } catch {
        document.getElementById('scanStatus').textContent = '❌ خطأ في الاتصال';
    }
});

// Event delegation: ONE listener for ALL count inputs (supports 10,000+ rows)
document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('count-input')) return;
    const pid = e.target.dataset.product;
    clearTimeout(saveTimer[pid]);
    saveTimer[pid] = setTimeout(() => saveCount(pid, e.target.value), 400);
});

async function saveCount(productId, value) {
    const val = parseFloat(value);
    if (isNaN(val) || val < 0) return;

    try {
        const res = await fetch(`/inventory/sessions/${SESSION_ID}/item`, {
            method: 'PATCH',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json' },
            body: JSON.stringify({ product_id: productId, counted_quantity: val }),
        });
        const data = await res.json();
        if (!res.ok) return;

        const diff  = parseFloat(data.difference);
        const vv    = parseFloat(data.variance_value);
        const diffEl = document.getElementById('diff-' + productId);
        const valEl  = document.getElementById('val-' + productId);
        const row    = document.getElementById('row-' + productId);

        diffEl.innerHTML = diff === 0
            ? '<span class="text-muted">0</span>'
            : `<span class="${diff < 0 ? 'text-danger' : 'text-success'} fw-bold">${diff > 0 ? '+' : ''}${diff.toFixed(3)}</span>`;

        valEl.innerHTML = diff === 0
            ? '—'
            : `<span class="${diff < 0 ? 'text-danger' : 'text-success'}">${Math.abs(vv).toFixed(2)} ${CUR}</span>`;

        row.className = diff < 0 ? 'table-danger bg-opacity-25' : (diff > 0 ? 'table-success bg-opacity-25' : '');
    } catch(e) {}
}
</script>
@endsection
@endif
