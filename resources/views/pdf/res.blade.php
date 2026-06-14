<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #111; direction: rtl; }
    .page { width: 210mm; padding: 10mm; margin: auto; }

    .head { text-align: center; border-bottom: 2px solid #222; padding-bottom: 6px; margin-bottom: 8px; }
    .head .store-name { font-size: 16px; font-weight: bold; }
    .head .store-sub  { font-size: 9px; color: #555; margin-top: 2px; }
    .title-bar { background: #1a2535; color: #fff; padding: 5px 10px; border-radius: 4px; margin-bottom: 8px; }
    .title-bar .t { font-size: 14px; font-weight: bold; }
    .title-bar .n { font-size: 11px; float: left; }

    .info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .info td { padding: 4px 8px; border: 1px solid #ddd; }
    .info .label { background: #f5f5f5; font-weight: bold; width: 18%; }

    .grid { width: 100%; border-collapse: collapse; }
    .grid td { vertical-align: top; width: 50%; padding: 0 4px; }

    .box { border: 1px solid #bbb; border-radius: 4px; margin-bottom: 8px; }
    .box .bh { padding: 4px 8px; font-weight: bold; font-size: 12px; border-bottom: 1px solid #bbb; }
    .box.rev .bh { background: #e8f5e9; color: #1a6b3c; }
    .box.exp .bh { background: #fdecea; color: #b02418; }
    .box.cap .bh { background: #e7f0fb; color: #134a8e; }
    .box.cus .bh { background: #eef0f2; color: #444; }
    .box table { width: 100%; border-collapse: collapse; }
    .box table td { padding: 3px 8px; border-bottom: 1px solid #eee; }
    .box .tot { font-weight: bold; background: #fafafa; }
    .num { text-align: left; }
    .memo td { color: #777; font-size: 10px; }

    .summary { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .summary td { width: 33.33%; padding: 6px; text-align: center; border: 1px solid #ccc; }
    .summary .lbl { font-size: 10px; color: #555; }
    .summary .val { font-size: 18px; font-weight: bold; }

    h4 { margin: 12px 0 4px; font-size: 12px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
    .dt { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 6px; }
    .dt th { background: #1a2535; color: #fff; padding: 3px 6px; text-align: right; }
    .dt td { padding: 3px 6px; border-bottom: 1px solid #eee; }
    .footer { text-align: center; margin-top: 12px; font-size: 8px; color: #aaa; border-top: 1px dashed #ccc; padding-top: 4px; }
</style>
</head>
<body>
@php
    $s = $re_statement;
    $revTotal = $s->sales_amount - $s->sales_returns_amount;
    $expTotal = $s->purchases_amount - $s->purchase_returns_amount + $s->expenses_amount;
    $hasCap   = $s->assets_amount > 0 || $s->assets_tax > 0;
    $hasCus   = $s->customs_amount > 0 || $s->customs_tax > 0;
@endphp
<div class="page">

    <div class="head">
        <div class="store-name">{{ $storeName }}</div>
        @if($storeAddress)<div class="store-sub">{{ $storeAddress }}</div>@endif
        @if($storePhone)<div class="store-sub">هاتف: {{ $storePhone }}</div>@endif
    </div>

    <div class="title-bar">
        <span class="n">{{ $s->number }}</span>
        <span class="t">كشف الإيرادات والمصروفات — Revenue &amp; Expense Statement</span>
    </div>

    <table class="info">
        <tr>
            <td class="label">تاريخ القطع</td><td>{{ $s->statement_date->format('Y-m-d') }}</td>
            <td class="label">البيان</td><td>{{ $s->description ?? '—' }}</td>
            <td class="label">المستخدم</td><td>{{ $s->user?->name ?? '—' }}</td>
        </tr>
    </table>

    <table class="grid"><tr>
        <td>
            <div class="box rev">
                <div class="bh">الإيرادات (Revenue)</div>
                <table>
                    <tr><td>المبيعات (صافي)</td><td class="num">{{ number_format($s->sales_amount, 2) }}</td><td class="num">ض: {{ number_format($s->sales_tax, 2) }}</td></tr>
                    @if($s->services_amount > 0)
                    <tr class="memo"><td>↳ منها إيرادات الخدمات</td><td class="num">{{ number_format($s->services_amount, 2) }}</td><td class="num">{{ number_format($s->services_tax, 2) }}</td></tr>
                    @endif
                    <tr><td>(−) مردودات المبيعات</td><td class="num">{{ number_format($s->sales_returns_amount, 2) }}</td><td></td></tr>
                    @if($s->credit_notes_amount > 0)
                    <tr class="memo"><td>↳ منها إشعارات دائنة</td><td class="num">{{ number_format($s->credit_notes_amount, 2) }}</td><td></td></tr>
                    @endif
                    <tr class="tot"><td>مجموع الإيرادات</td><td class="num">{{ number_format($revTotal, 2) }}</td><td></td></tr>
                </table>
            </div>
        </td>
        <td>
            <div class="box exp">
                <div class="bh">المصروفات (Expenses)</div>
                <table>
                    <tr><td>المشتريات (صافي)</td><td class="num">{{ number_format($s->purchases_amount, 2) }}</td><td class="num">ض: {{ number_format($s->purchases_tax, 2) }}</td></tr>
                    <tr><td>(−) مردودات المشتريات</td><td class="num">{{ number_format($s->purchase_returns_amount, 2) }}</td><td></td></tr>
                    @if($s->debit_notes_amount > 0)
                    <tr class="memo"><td>↳ منها إشعارات مدينة</td><td class="num">{{ number_format($s->debit_notes_amount, 2) }}</td><td></td></tr>
                    @endif
                    <tr><td>المصروفات التشغيلية (صافي)</td><td class="num">{{ number_format($s->expenses_amount, 2) }}</td><td class="num">ض: {{ number_format($s->expenses_tax, 2) }}</td></tr>
                    <tr class="tot"><td>مجموع المصروفات</td><td class="num">{{ number_format($expTotal, 2) }}</td><td></td></tr>
                </table>
            </div>
        </td>
    </tr></table>

    @if($hasCap || $hasCus)
    <table class="grid"><tr>
        <td>
            @if($hasCap)
            <div class="box cap">
                <div class="bh">الأصول الرأسمالية (Capital Assets)</div>
                <table><tr><td>تكلفة الأصول</td><td class="num">{{ number_format($s->assets_amount, 2) }}</td><td class="num">ض. مدخلات: {{ number_format($s->assets_tax, 2) }}</td></tr></table>
            </div>
            @endif
        </td>
        <td>
            @if($hasCus)
            <div class="box cus">
                <div class="bh">الإقرارات الجمركية (Import VAT)</div>
                <table><tr><td>قيمة الواردات/الرسوم</td><td class="num">{{ number_format($s->customs_amount, 2) }}</td><td class="num">ض. مدخلات: {{ number_format($s->customs_tax, 2) }}</td></tr></table>
            </div>
            @endif
        </td>
    </tr></table>
    @endif

    <table class="summary">
        <tr>
            <td><div class="lbl">المبلغ الإجمالي (صافي الربح)</div><div class="val">{{ number_format($s->net_amount, 2) }} {{ $currency }}</div></td>
            <td><div class="lbl">صافي ض.ق.م المستحقة (مخرجات − مدخلات)</div><div class="val">{{ number_format($s->net_vat, 2) }} {{ $currency }}</div></td>
            <td><div class="lbl">هامش مجمل الربح</div><div class="val">{{ number_format($s->profit_percent, 2) }}%</div></td>
        </tr>
    </table>

    @php
        $sections = [
            ['l' => 'فواتير المبيعات',    'd' => $s->sales,               'no' => 'invoice_number',     'dt' => fn($x)=>$x->created_at?->format('Y-m-d'),                       'p' => fn($x)=>$x->customer?->name ?? 'نقدي',                'a' => fn($x)=>$x->total_amount, 'tx' => fn($x)=>$x->tax],
            ['l' => 'مردودات المبيعات',   'd' => $s->saleReturns,         'no' => 'return_number',      'dt' => fn($x)=>\Illuminate\Support\Carbon::parse($x->return_date)->format('Y-m-d'),      'p' => fn($x)=>$x->refundMethodLabel(),                'a' => fn($x)=>$x->total_amount, 'tx' => fn($x)=>0],
            ['l' => 'فواتير المشتريات',   'd' => $s->purchases,           'no' => 'invoice_number',     'dt' => fn($x)=>$x->created_at?->format('Y-m-d'),                       'p' => fn($x)=>$x->supplier?->name ?? '—',             'a' => fn($x)=>$x->total_amount, 'tx' => fn($x)=>$x->tax_amount],
            ['l' => 'مردودات المشتريات',  'd' => $s->purchaseReturns,     'no' => 'return_number',      'dt' => fn($x)=>\Illuminate\Support\Carbon::parse($x->return_date)->format('Y-m-d'),      'p' => fn($x)=>$x->refundMethodLabel(),                'a' => fn($x)=>$x->total_amount, 'tx' => fn($x)=>0],
            ['l' => 'فواتير المصاريف',    'd' => $s->expenseInvoices,     'no' => 'invoice_number',     'dt' => fn($x)=>\Illuminate\Support\Carbon::parse($x->invoice_date)->format('Y-m-d'),     'p' => fn($x)=>$x->vendor_name,                        'a' => fn($x)=>$x->total_amount, 'tx' => fn($x)=>$x->tax_amount],
            ['l' => 'الأصول الرأسمالية',  'd' => $s->fixedAssets,         'no' => 'asset_code',         'dt' => fn($x)=>\Illuminate\Support\Carbon::parse($x->purchase_date)->format('Y-m-d'),    'p' => fn($x)=>$x->name,                               'a' => fn($x)=>$x->purchase_cost, 'tx' => fn($x)=>$x->tax_amount],
            ['l' => 'الإقرارات الجمركية', 'd' => $s->customsDeclarations, 'no' => 'declaration_number', 'dt' => fn($x)=>\Illuminate\Support\Carbon::parse($x->declaration_date)->format('Y-m-d'), 'p' => fn($x)=>$x->supplier?->name ?? $x->vendor_name, 'a' => fn($x)=>$x->total_amount, 'tx' => fn($x)=>$x->tax_amount],
        ];
    @endphp

    @foreach($sections as $sec)
    @if($sec['d']->count())
    <h4>{{ $sec['l'] }} ({{ $sec['d']->count() }})</h4>
    <table class="dt">
        <thead><tr><th>الرقم</th><th>التاريخ</th><th>الطرف</th><th class="num">المبلغ</th><th class="num">الضريبة</th></tr></thead>
        <tbody>
            @foreach($sec['d'] as $doc)
            <tr>
                <td>{{ $doc->{$sec['no']} }}</td>
                <td>{{ $sec['dt']($doc) }}</td>
                <td>{{ $sec['p']($doc) }}</td>
                <td class="num">{{ number_format($sec['a']($doc), 2) }}</td>
                <td class="num">{{ number_format($sec['tx']($doc), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    @endforeach

    <div class="footer">{{ $storeName }} — {{ $s->number }} — طُبع في {{ now()->format('Y-m-d H:i') }}</div>

</div>
</body>
</html>
