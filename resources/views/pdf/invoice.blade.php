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
    .letterhead {
        background: #2c3e50;
        color: white;
        padding: 14px 18px;
        margin-bottom: 3px;
    }
    .lh-table  { width: 100%; }
    .lh-right  { width: 60%; vertical-align: middle; }
    .lh-left   { width: 40%; vertical-align: middle; text-align: left; }
    .co-name   { font-size: 18px; font-weight: bold; }
    .co-sub    { font-size: 9px; margin-top: 2px; color: #ccc; }
    .doc-title { font-size: 20px; font-weight: bold; }
    .doc-date  { font-size: 9px; margin-top: 3px; color: #ccc; }

    .divider { height: 3px; background: #2c3e50; margin-bottom: 16px; }

    /* ── Info boxes ── */
    .info-table { width: 100%; margin-bottom: 16px; }
    .info-box {
        width: 48%;
        vertical-align: top;
        background: #f7f9fc;
        border: 1px solid #dde4ed;
        padding: 9px 12px;
    }
    .info-box-gap { width: 4%; }
    .box-title { font-size: 8px; color: #888; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
    .info-line { margin-bottom: 3px; font-size: 10px; line-height: 1.5; }

    /* ── Items table ── */
    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .items-table thead tr { background: #2c3e50; color: white; }
    .items-table thead th { padding: 7px 9px; font-size: 9px; font-weight: bold; text-align: right; }
    .items-table thead th.ctr { text-align: center; }
    .items-table thead th.lft { text-align: left; }
    .items-table tbody tr:nth-child(even) { background: #f5f8fb; }
    .items-table tbody td { padding: 6px 9px; font-size: 10px; border-bottom: 1px solid #e5ebf2; text-align: right; }
    .items-table tbody td.ctr { text-align: center; }
    .items-table tbody td.lft { text-align: left; font-weight: bold; }

    /* ── Totals ── */
    .totals-outer { width: 100%; margin-bottom: 16px; }
    .totals-spacer { width: 55%; vertical-align: top; }
    .totals-wrap   { width: 45%; vertical-align: top; }
    .totals-table  { width: 100%; border-collapse: collapse; }
    .totals-table td { padding: 5px 9px; font-size: 10px; border-bottom: 1px solid #eee; }
    .totals-table td.lbl { text-align: right; color: #555; }
    .totals-table td.val { text-align: left; font-weight: bold; }
    .totals-table tr.grand td { background: #2c3e50; color: white; font-size: 12px; font-weight: bold; border: none; }

    /* ── Payment ── */
    .pm-cash   { color: #27ae60; font-weight: bold; }
    .pm-card   { color: #2980b9; font-weight: bold; }
    .pm-credit { color: #e74c3c; font-weight: bold; }
    .credit-note { text-align: center; color: #e74c3c; font-weight: bold; font-size: 11px; }

    /* ── Footer ── */
    .footer-table { width: 100%; margin-top: 22px; border-top: 1px solid #ddd; padding-top: 8px; }
    .footer-right { font-size: 9px; color: #777; text-align: right; vertical-align: middle; }
    .footer-left  { font-size: 9px; color: #aaa; text-align: left;  vertical-align: middle; }
</style>
</head>
<body>
<div class="page">

    {{-- ── Letterhead ── --}}
    <table class="lh-table" style="background:#2c3e50; color:white; padding:14px 18px; margin-bottom:3px;">
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
                <div class="doc-title">فاتورة مبيعات</div>
                <div class="doc-date">تاريخ الطباعة: {{ now()->format('Y/m/d  H:i') }}</div>
            </td>
        </tr>
    </table>
    <div class="divider"></div>

    {{-- ── Invoice / Customer Info ── --}}
    <table class="info-table">
        <tr>
            <td class="info-box">
                <div class="box-title">بيانات الفاتورة</div>
                <div class="info-line"><strong>رقم الفاتورة:</strong> {{ $sale->invoice_number }}</div>
                <div class="info-line"><strong>التاريخ:</strong> {{ $sale->created_at->format('Y/m/d  H:i') }}</div>
                <div class="info-line"><strong>الكاشير:</strong> {{ $sale->user->name ?? '—' }}</div>
                <div class="info-line"><strong>طريقة الدفع:</strong>
                    @if($sale->is_credit)
                        <span class="pm-credit">آجل</span>
                    @elseif($sale->payment_method === 'cash')
                        <span class="pm-cash">نقدي</span>
                    @elseif($sale->payment_method === 'card')
                        <span class="pm-card">بطاقة</span>
                    @else
                        {{ $sale->payment_method }}
                    @endif
                </div>
            </td>
            <td class="info-box-gap"></td>
            <td class="info-box">
                <div class="box-title">بيانات العميل</div>
                @if($sale->customer)
                    <div class="info-line"><strong>العميل:</strong> {{ $sale->customer->name }}</div>
                    @if($sale->customer->phone)
                    <div class="info-line"><strong>الهاتف:</strong> {{ $sale->customer->phone }}</div>
                    @endif
                    @if($sale->customer->address)
                    <div class="info-line"><strong>العنوان:</strong> {{ $sale->customer->address }}</div>
                    @endif
                @else
                    <div class="info-line" style="color:#999;">عميل نقدي</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ── Items Table ── --}}
    <table class="items-table">
        <thead>
            <tr>
                <th class="ctr" style="width:5%">#</th>
                <th style="width:42%">المنتج</th>
                <th class="ctr" style="width:13%">الكمية</th>
                <th class="lft" style="width:20%">سعر الوحدة</th>
                <th class="lft" style="width:20%">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $i => $item)
            <tr>
                <td class="ctr">{{ $i + 1 }}</td>
                <td>{{ $item->product->name ?? '—' }}</td>
                <td class="ctr">{{ $item->quantity }}</td>
                <td class="lft">{{ number_format($item->unit_price, 2) }}</td>
                <td class="lft">{{ number_format($item->total_price, 2) }}</td>
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
                    <tr>
                        <td class="lbl">المجموع الفرعي</td>
                        <td class="val">{{ number_format($sale->subtotal, 2) }} ج.م</td>
                    </tr>
                    @if($sale->discount > 0)
                    <tr>
                        <td class="lbl" style="color:#e74c3c;">الخصم</td>
                        <td class="val" style="color:#e74c3c;">- {{ number_format($sale->discount, 2) }} ج.م</td>
                    </tr>
                    @endif
                    <tr class="grand">
                        <td class="lbl" style="color:white;">الإجمالي</td>
                        <td class="val" style="color:white;">{{ number_format($sale->total_amount, 2) }} ج.م</td>
                    </tr>
                    @if(!$sale->is_credit)
                    <tr>
                        <td class="lbl">المدفوع</td>
                        <td class="val">{{ number_format($sale->paid_amount, 2) }} ج.م</td>
                    </tr>
                    <tr>
                        <td class="lbl">الباقي / الفكة</td>
                        <td class="val">{{ number_format($sale->change_amount, 2) }} ج.م</td>
                    </tr>
                    @else
                    <tr>
                        <td colspan="2" class="credit-note">فاتورة آجلة — مستحقة على العميل</td>
                    </tr>
                    @endif
                </table>
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
    </table>

</div>
</body>
</html>
