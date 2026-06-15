<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 11px;
        color: #1a1a1a;
        direction: rtl;
    }

    .page { padding: 24px 28px; }

    /* ── Letterhead ── */
    .lh-table  { width: 100%; background: #1a3c5e; color: white; padding: 14px 18px; margin-bottom: 3px; }
    .lh-right  { width: 60%; vertical-align: middle; }
    .lh-left   { width: 40%; vertical-align: middle; text-align: left; }
    .co-name   { font-size: 18px; font-weight: bold; }
    .co-sub    { font-size: 9px; margin-top: 2px; color: #b0c4d8; }
    .doc-title { font-size: 20px; font-weight: bold; }
    .doc-date  { font-size: 9px; margin-top: 3px; color: #b0c4d8; }

    .divider { height: 3px; background: #1a3c5e; margin-bottom: 16px; }

    /* ── Info boxes ── */
    .info-table { width: 100%; margin-bottom: 16px; }
    .info-box   { width: 48%; vertical-align: top; background: #f4f7fb; border: 1px solid #d5e0ee; padding: 9px 12px; }
    .info-gap   { width: 4%; }
    .box-title  { font-size: 8px; color: #7a90a8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; border-bottom: 1px solid #dde8f2; padding-bottom: 3px; }
    .info-line  { margin-bottom: 3px; font-size: 10px; line-height: 1.5; }

    /* ── Status badge ── */
    .badge-draft     { background: #95a5a6; color: white; padding: 1px 8px; font-size: 9px; }
    .badge-sent      { background: #2980b9; color: white; padding: 1px 8px; font-size: 9px; }
    .badge-partial   { background: #f39c12; color: white; padding: 1px 8px; font-size: 9px; }
    .badge-received  { background: #27ae60; color: white; padding: 1px 8px; font-size: 9px; }
    .badge-cancelled { background: #e74c3c; color: white; padding: 1px 8px; font-size: 9px; }

    /* ── Items table ── */
    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .items-table thead tr   { background: #1a3c5e; color: white; }
    .items-table thead th   { padding: 7px 9px; font-size: 9px; font-weight: bold; text-align: right; }
    .items-table thead th.ctr { text-align: center; }
    .items-table thead th.lft { text-align: left; }
    .items-table tbody tr:nth-child(even) { background: #f4f7fb; }
    .items-table tbody td   { padding: 6px 9px; font-size: 10px; border-bottom: 1px solid #e0eaf4; text-align: right; }
    .items-table tbody td.ctr { text-align: center; }
    .items-table tbody td.lft { text-align: left; font-weight: bold; }

    /* ── Totals ── */
    .totals-outer  { width: 100%; margin-bottom: 16px; }
    .totals-spacer { width: 55%; vertical-align: top; }
    .totals-wrap   { width: 45%; vertical-align: top; }
    .totals-table  { width: 100%; border-collapse: collapse; }
    .totals-table td       { padding: 5px 9px; font-size: 10px; border-bottom: 1px solid #eee; }
    .totals-table td.lbl   { text-align: right; color: #555; }
    .totals-table td.val   { text-align: left; font-weight: bold; }
    .totals-table tr.grand td { background: #1a3c5e; color: white; font-size: 12px; font-weight: bold; border: none; }

    /* ── Notes / terms ── */
    .notes-box { background: #fffbea; border-right: 4px solid #f39c12; padding: 9px 12px; margin-bottom: 14px; }
    .notes-lbl { font-size: 8px; color: #888; margin-bottom: 3px; }
    .terms-box { background: #f4f7fb; border-right: 4px solid #1a3c5e; padding: 9px 12px; margin-bottom: 14px; }

    /* ── Signatures ── */
    .sign-table { width: 100%; margin-top: 30px; }
    .sign-cell  { width: 45%; vertical-align: top; text-align: center; font-size: 10px; color: #555; }
    .sign-gap   { width: 10%; }
    .sign-line  { border-top: 1px solid #999; margin: 28px 12px 5px; }

    /* ── Footer ── */
    .footer-table { width: 100%; margin-top: 22px; border-top: 1px solid #ddd; padding-top: 8px; }
    .footer-right { font-size: 9px; color: #777; text-align: right; vertical-align: middle; }
    .footer-left  { font-size: 9px; color: #aaa; text-align: left;  vertical-align: middle; }
</style>
</head>
<body>
<div class="page">

    {{-- ── Letterhead ── --}}
    <table class="lh-table">
        <tr>
            <td class="lh-right">
                <div class="co-name">{{ \App\Models\Setting::get('store_name', 'نظام POS للسوبر ماركت') }}</div>
                @if(\App\Models\Setting::get('company_address'))
                <div class="co-sub">{{ \App\Models\Setting::get('company_address') }}</div>
                @endif
                @if(\App\Models\Setting::get('company_phone'))
                <div class="co-sub">هاتف: {{ \App\Models\Setting::get('company_phone') }}</div>
                @endif
            </td>
            <td class="lh-left">
                <div class="doc-title">أمر شراء</div>
                <div class="doc-date">تاريخ الطباعة: {{ now()->format('Y/m/d  H:i') }}</div>
            </td>
        </tr>
    </table>
    <div class="divider"></div>

    {{-- ── Info boxes ── --}}
    <table class="info-table">
        <tr>
            <td class="info-box">
                <div class="box-title">بيانات المورد</div>
                <div class="info-line"><strong>الاسم:</strong> {{ $purchaseOrder->supplier->name }}</div>
                @if($purchaseOrder->supplier->company)
                <div class="info-line"><strong>الشركة:</strong> {{ $purchaseOrder->supplier->company }}</div>
                @endif
                @if($purchaseOrder->supplier->tax_number)
                <div class="info-line"><strong>الرقم الضريبي:</strong> {{ $purchaseOrder->supplier->tax_number }}</div>
                @endif
                <div class="info-line"><strong>الهاتف:</strong> {{ $purchaseOrder->supplier->phone ?? '—' }}</div>
                @if($purchaseOrder->supplier->email)
                <div class="info-line"><strong>البريد:</strong> {{ $purchaseOrder->supplier->email }}</div>
                @endif
            </td>
            <td class="info-gap"></td>
            <td class="info-box">
                <div class="box-title">بيانات الأمر</div>
                <div class="info-line"><strong>رقم الأمر:</strong> {{ $purchaseOrder->po_number }}</div>
                <div class="info-line"><strong>تاريخ الأمر:</strong> {{ $purchaseOrder->order_date->format('Y/m/d') }}</div>
                @if($purchaseOrder->expected_delivery_date)
                <div class="info-line"><strong>تاريخ التسليم المتوقع:</strong> {{ $purchaseOrder->expected_delivery_date->format('Y/m/d') }}</div>
                @endif
                <div class="info-line"><strong>المخزن:</strong> {{ $purchaseOrder->warehouse?->name ?? '—' }}</div>
                <div class="info-line"><strong>الفرع:</strong> {{ $purchaseOrder->branch?->name ?? '—' }}</div>
                <div class="info-line"><strong>بواسطة:</strong> {{ $purchaseOrder->user->name ?? '—' }}</div>
                <div class="info-line"><strong>الحالة:</strong>
                    @php $st = $purchaseOrder->status; @endphp
                    <span class="badge-{{ $st }}">{{ $purchaseOrder->statusLabel() }}</span>
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Items ── --}}
    <table class="items-table">
        <thead>
            <tr>
                <th class="ctr" style="width:5%">#</th>
                <th style="width:47%">المنتج</th>
                <th class="ctr" style="width:15%">الكمية المطلوبة</th>
                <th class="lft" style="width:16%">سعر الوحدة</th>
                <th class="lft" style="width:17%">المجموع</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->items as $i => $item)
            <tr>
                <td class="ctr">{{ $i + 1 }}</td>
                <td>{{ $item->product->name ?? '—' }}</td>
                <td class="ctr">{{ $item->quantity_ordered + 0 }}</td>
                <td class="lft">{{ number_format($item->unit_price, 2) }} {{ $currency }}</td>
                <td class="lft">{{ number_format($item->total_price, 2) }} {{ $currency }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── Totals ── --}}
    <table class="totals-outer">
        <tr>
            <td class="totals-spacer"></td>
            <td class="totals-wrap">
                <table class="totals-table">
                    <tr class="grand">
                        <td class="lbl" style="color:white;">إجمالي الأمر</td>
                        <td class="val" style="color:white;">{{ number_format($purchaseOrder->total_amount, 2) }} {{ $currency }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── Terms ── --}}
    @if($purchaseOrder->terms)
    <div class="terms-box">
        <div class="notes-lbl">الشروط</div>
        {{ $purchaseOrder->terms }}
    </div>
    @endif

    {{-- ── Notes ── --}}
    @if($purchaseOrder->notes)
    <div class="notes-box">
        <div class="notes-lbl">ملاحظات</div>
        {{ $purchaseOrder->notes }}
    </div>
    @endif

    {{-- ── Signatures ── --}}
    <table class="sign-table">
        <tr>
            <td class="sign-cell">
                <div class="sign-line"></div>
                المُعتمِد
            </td>
            <td class="sign-gap"></td>
            <td class="sign-cell">
                <div class="sign-line"></div>
                المورد
            </td>
        </tr>
    </table>

    {{-- ── Footer ── --}}
    <table class="footer-table">
        <tr>
            <td class="footer-right">
                {{ \App\Models\Setting::get('store_name', 'نظام POS') }}
                @if(\App\Models\Setting::get('company_phone'))
                    | هاتف: {{ \App\Models\Setting::get('company_phone') }}
                @endif
            </td>
            <td class="footer-left">طُبع: {{ now()->format('Y/m/d H:i') }}</td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: center; font-size: 8px; color: #999; padding-top: 8px; border-top: 1px dashed #ddd;">
                {{ \App\Models\Setting::get('invoice_footer', '') }}
            </td>
        </tr>
        @if(config('mizaan.print_footer'))
        <tr>
            <td colspan="2" style="text-align:center;font-size:7.5px;color:#bbb;padding-top:5px;letter-spacing:0.3px;">
                {{ config('mizaan.print_footer') }}
            </td>
        </tr>
        @endif
    </table>

</div>
</body>
</html>
