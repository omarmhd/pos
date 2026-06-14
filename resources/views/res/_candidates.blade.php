{{-- جدول المرشحين للكشف مع خانات الاختيار (إلغاء التحديد = حجز للكشف التالي)
     يتوقع: $candidates, $currency, واختيارياً $memberIds (ids الأعضاء الحاليين لكل نوع) --}}
@php
    $dateOf = function ($type, $doc) {
        return match ($type) {
            'sales', 'purchases' => $doc->created_at?->format('Y-m-d'),
            'sale_returns', 'purchase_returns' => $doc->return_date?->format('Y-m-d'),
            'expense_invoices' => $doc->invoice_date?->format('Y-m-d'),
            'fixed_assets' => \Illuminate\Support\Carbon::parse($doc->purchase_date)->format('Y-m-d'),
            'customs_declarations' => \Illuminate\Support\Carbon::parse($doc->declaration_date)->format('Y-m-d'),
            'service_invoices' => \Illuminate\Support\Carbon::parse($doc->invoice_date)->format('Y-m-d'),
            default => '—',
        };
    };
    $numberOf = function ($type, $doc) {
        return $doc->invoice_number ?? $doc->return_number
            ?? $doc->asset_code ?? $doc->declaration_number ?? ('#' . $doc->id);
    };
    $partyOf = function ($type, $doc) {
        return $doc->customer?->name ?? $doc->supplier?->name
            ?? $doc->vendor_name ?? $doc->supplier_name ?? '—';
    };
    // مبلغ المستند (الأصول تستخدم تكلفة الشراء بدل total_amount)
    $amountOf = fn($doc) => (float) ($doc->total_amount ?? $doc->purchase_cost ?? 0);
    $taxOf = fn($doc) => (float) ($doc->tax ?? $doc->tax_amount ?? 0);
@endphp

@foreach($candidates as $type => $section)
<div class="card mb-3">
    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
        <strong>{{ $section['label'] }} ({{ $section['docs']->count() }})</strong>
        @if($section['docs']->count())
        <div>
            <button type="button" class="btn btn-sm btn-outline-success" onclick="toggleSection('{{ $type }}', true)">تحديد الكل</button>
            <button type="button" class="btn btn-sm btn-outline-warning" onclick="toggleSection('{{ $type }}', false)">حجز الكل</button>
        </div>
        @endif
    </div>
    <div class="card-body p-0">
        @if($section['docs']->isEmpty())
            <p class="text-muted small p-3 mb-0">لا توجد مستندات مرشحة.</p>
        @else
        <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:5%"></th>
                        <th>الرقم</th>
                        <th>التاريخ</th>
                        <th>الطرف</th>
                        <th class="text-end">المبلغ ({{ $currency }})</th>
                        <th class="text-end">الضريبة</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($section['docs'] as $doc)
                    @php
                        $isMember = isset($memberIds) && in_array($doc->id, $memberIds[$type] ?? []);
                        $isLate   = isset($memberIds) && !$isMember;   // غير عضو في شاشة التعديل = متأخرة/محجوزة
                    @endphp
                    <tr class="{{ $isLate ? 'table-warning' : '' }}">
                        <td>
                            <input type="checkbox" class="form-check-input cand-{{ $type }}"
                                   name="include[{{ $type }}][]" value="{{ $doc->id }}"
                                   {{ (!isset($memberIds) || $isMember) ? 'checked' : '' }}>
                        </td>
                        <td class="font-monospace">{{ $numberOf($type, $doc) }}</td>
                        <td>{{ $dateOf($type, $doc) }}</td>
                        <td>{{ $partyOf($type, $doc) }}</td>
                        <td class="text-end">{{ number_format($amountOf($doc), 2) }}</td>
                        <td class="text-end">{{ number_format($taxOf($doc), 2) }}</td>
                        <td>
                            @if($isLate)
                                <span class="badge bg-warning text-dark">متأخرة / محجوزة</span>
                            @elseif(isset($memberIds))
                                <span class="badge bg-success">ضمن الكشف</span>
                            @else
                                <span class="badge bg-secondary">مرشحة</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endforeach

<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle"></i>
    إلغاء تحديد فاتورة = <strong>حجزها</strong> (تبقى خارج هذا الكشف وتُلتقط تلقائياً في الكشف التالي) — مكافئ مفتاح F7 في الأصيل.
</div>

<script>
function toggleSection(type, state) {
    document.querySelectorAll('.cand-' + type).forEach(cb => cb.checked = state);
}
</script>
