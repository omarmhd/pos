<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة - {{ $sale->invoice_number }}</title>
    @php
        $storeName    = \App\Models\Setting::get('store_name',    'سوبر ماركت');
        $storeAddress = \App\Models\Setting::get('store_address', '');
        $storePhone   = \App\Models\Setting::get('store_phone',   '');
        $taxNumber    = \App\Models\Setting::get('store_tax_number', '');
        $cur          = \App\Models\Setting::get('currency_symbol', 'ج.م');
        $footer       = \App\Models\Setting::get('receipt_footer', 'شكراً لزيارتكم');
        $widthMm      = (int) \App\Models\Setting::get('receipt_width_mm', 80);
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            width: {{ $widthMm }}mm;
            margin: 0 auto;
            padding: 10px;
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .header h2 { font-size: 18px; margin-bottom: 4px; }
        .header p  { font-size: 11px; margin: 2px 0; }
        .info { margin-bottom: 10px; font-size: 12px; }
        .info p { margin: 3px 0; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 11px;
        }
        table th {
            border-bottom: 1px solid #000;
            padding: 4px 0;
            text-align: right;
        }
        table td {
            padding: 4px 0;
            border-bottom: 1px dashed #ccc;
        }
        .totals {
            border-top: 2px solid #000;
            margin-top: 10px;
            padding-top: 10px;
            font-size: 13px;
        }
        .totals p {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
        }
        .totals .grand-total {
            font-size: 16px;
            font-weight: bold;
            border-top: 1px solid #000;
            padding-top: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px dashed #000;
            font-size: 11px;
        }
        @media print {
            body { width: {{ $widthMm }}mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="header">
    <h2>{{ $storeName }}</h2>
    @if($storeAddress)<p>{{ $storeAddress }}</p>@endif
    @if($storePhone)<p>هاتف: {{ $storePhone }}</p>@endif
    @if($taxNumber)<p>الرقم الضريبي: {{ $taxNumber }}</p>@endif
</div>

<div class="info">
    <p><strong>رقم الفاتورة:</strong> {{ $sale->invoice_number }}</p>
    <p><strong>التاريخ:</strong> {{ $sale->created_at->format('Y-m-d H:i:s') }}</p>
    <p><strong>الكاشير:</strong> {{ $sale->user->name }}</p>
    @if($sale->customer)
    <p><strong>العميل:</strong> {{ $sale->customer->name }}</p>
    @endif
    <p><strong>طريقة الدفع:</strong>
        @if($sale->is_credit) آجل
        @elseif($sale->payment_method == 'cash') نقدي
        @elseif($sale->payment_method == 'card') بطاقة بنكية
        @else محفظة إلكترونية
        @endif
    </p>
</div>

<table>
    <thead>
    <tr>
        <th style="width:50%">المنتج</th>
        <th style="width:15%; text-align:center;">الكمية</th>
        <th style="width:17%; text-align:left;">السعر</th>
        <th style="width:18%; text-align:left;">المجموع</th>
    </tr>
    </thead>
    <tbody>
    @foreach($sale->items as $item)
        <tr>
            <td>{{ $item->product->name }}</td>
            <td style="text-align:center;">{{ $item->quantity }}</td>
            <td style="text-align:left;">{{ number_format($item->unit_price, 2) }}</td>
            <td style="text-align:left;"><strong>{{ number_format($item->total_price, 2) }}</strong></td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="totals">
    <p>
        <span>المجموع الفرعي:</span>
        <span>{{ number_format($sale->subtotal, 2) }} {{ $cur }}</span>
    </p>
    @if($sale->discount > 0)
    <p>
        <span>الخصم:</span>
        <span>- {{ number_format($sale->discount, 2) }} {{ $cur }}</span>
    </p>
    @endif
    @if($sale->tax > 0)
    <p>
        <span>ضريبة القيمة المضافة:</span>
        <span>{{ number_format($sale->tax, 2) }} {{ $cur }}</span>
    </p>
    @endif
    <p class="grand-total">
        <span>الإجمالي:</span>
        <span>{{ number_format($sale->total_amount, 2) }} {{ $cur }}</span>
    </p>
    @if(!$sale->is_credit)
    <p>
        <span>المدفوع:</span>
        <span>{{ number_format($sale->paid_amount, 2) }} {{ $cur }}</span>
    </p>
    <p>
        <span>الباقي:</span>
        <span>{{ number_format($sale->change_amount, 2) }} {{ $cur }}</span>
    </p>
    @else
    <p><span>بيع آجل</span><span>—</span></p>
    @endif
</div>

<div class="footer">
    <p>{{ $footer }}</p>
    <p style="margin-top:10px;">* * * * * * * * * *</p>
</div>

<div class="no-print" style="text-align:center; margin-top:20px;">
    <button onclick="window.print()" style="padding:10px 30px; font-size:16px; cursor:pointer;">طباعة</button>
    <button onclick="window.close()" style="padding:10px 30px; font-size:16px; cursor:pointer; margin-right:10px;">إغلاق</button>
</div>

<script>
    window.onload = function() { setTimeout(function() { window.print(); }, 400); };
</script>
</body>
</html>
