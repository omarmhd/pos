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
    .lh-table  { width: 100%; background: #2c3e50; color: white; padding: 14px 18px; margin-bottom: 3px; }
    .lh-right  { width: 60%; vertical-align: middle; }
    .lh-left   { width: 40%; vertical-align: middle; text-align: left; }
    .co-name   { font-size: 18px; font-weight: bold; }
    .co-sub    { font-size: 9px; margin-top: 2px; color: #ccc; }
    .doc-title { font-size: 20px; font-weight: bold; }
    .doc-date  { font-size: 9px; margin-top: 3px; color: #ccc; }

    .divider { height: 3px; background: #2c3e50; margin-bottom: 16px; }

    /* ── Meta boxes ── */
    .meta-outer { width: 100%; margin-bottom: 16px; }
    .meta-box   { width: 48%; vertical-align: top; background: #f7f9fc; border: 1px solid #dde4ed; padding: 9px 12px; }
    .meta-gap   { width: 4%; }
    .box-title  { font-size: 8px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; border-bottom: 1px solid #eee; padding-bottom: 3px; }
    .row-inner  { width: 100%; margin-bottom: 3px; }
    .row-key    { width: 50%; color: #555; vertical-align: top; font-size: 10px; }
    .row-val    { font-weight: bold; text-align: left; vertical-align: top; font-size: 10px; }

    /* ── Description band ── */
    .desc-band {
        background: #eaf0fb;
        border-right: 4px solid #2980b9;
        padding: 9px 12px;
        margin-bottom: 16px;
        font-size: 11px;
    }
    .desc-lbl { font-size: 8px; color: #888; margin-bottom: 3px; }

    /* ── Lines table ── */
    .lines-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .lines-table thead tr   { background: #2c3e50; color: white; }
    .lines-table thead th   { padding: 7px 9px; font-size: 9px; font-weight: bold; text-align: right; }
    .lines-table thead th.ctr { text-align: center; }
    .lines-table thead th.lft { text-align: left; }
    .lines-table tbody tr:nth-child(even) { background: #f5f8fb; }
    .lines-table tbody td   { padding: 6px 9px; font-size: 10px; border-bottom: 1px solid #e5ebf2; text-align: right; }
    .lines-table tbody td.ctr { text-align: center; }
    .lines-table tbody td.num { text-align: left; font-family: monospace; font-weight: bold; }
    .lines-table tbody td.dim { color: #999; font-size: 9px; }
    .lines-table tfoot tr   { background: #2c3e50; color: white; }
    .lines-table tfoot td   { padding: 7px 9px; font-size: 11px; font-weight: bold; text-align: right; border: none; }
    .lines-table tfoot td.num { text-align: left; font-family: monospace; }

    /* ── Badges ── */
    .badge-ok  { background: #27ae60; color: white; padding: 1px 8px; font-size: 9px; }
    .badge-err { background: #e74c3c; color: white; padding: 1px 8px; font-size: 9px; }
    .badge-src { background: #34495e; color: white; padding: 1px 8px; font-size: 9px; }

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
                <div class="doc-title">قيد يومية</div>
                <div class="doc-date">تاريخ الطباعة: {{ now()->format('Y/m/d  H:i') }}</div>
            </td>
        </tr>
    </table>
    <div class="divider"></div>

    {{-- ── Meta info ── --}}
    <table class="meta-outer">
        <tr>
            <td class="meta-box">
                <div class="box-title">بيانات القيد</div>
                <table class="row-inner"><tr>
                    <td class="row-key">رقم القيد:</td>
                    <td class="row-val">{{ $je->entry_number }}</td>
                </tr></table>
                <table class="row-inner"><tr>
                    <td class="row-key">تاريخ القيد:</td>
                    <td class="row-val">{{ $je->entry_date?->format('Y/m/d') }}</td>
                </tr></table>
                <table class="row-inner"><tr>
                    <td class="row-key">تاريخ الترحيل:</td>
                    <td class="row-val">{{ $je->posted_at?->format('Y/m/d H:i') ?? '—' }}</td>
                </tr></table>
                <table class="row-inner"><tr>
                    <td class="row-key">بواسطة:</td>
                    <td class="row-val">{{ $je->user?->name ?? '—' }}</td>
                </tr></table>
            </td>
            <td class="meta-gap"></td>
            <td class="meta-box">
                <div class="box-title">معلومات إضافية</div>
                <table class="row-inner"><tr>
                    <td class="row-key">المرجع:</td>
                    <td class="row-val">{{ $je->reference ?? '—' }}</td>
                </tr></table>
                <table class="row-inner"><tr>
                    <td class="row-key">مصدر القيد:</td>
                    <td class="row-val">
                        @if(is_null($je->source_type))
                            <span class="badge-src">يدوي</span>
                        @else
                            <span class="badge-src">{{ match(true) {
                                str_contains($je->source_type, 'Sale')            => 'مبيعات',
                                str_contains($je->source_type, 'Purchase')        => 'مشتريات',
                                str_contains($je->source_type, 'SupplierPayment') => 'دفعة مورد',
                                str_contains($je->source_type, 'CustomerPayment') => 'تحصيل عميل',
                                str_contains($je->source_type, 'PayrollRun')      => 'رواتب',
                                default => class_basename($je->source_type),
                            } }}</span>
                        @endif
                    </td>
                </tr></table>
                <table class="row-inner"><tr>
                    <td class="row-key">الحالة:</td>
                    <td class="row-val">
                        @php $diff = abs($je->debit_total - $je->credit_total); @endphp
                        @if($diff < 0.01)
                            <span class="badge-ok">متوازن</span>
                        @else
                            <span class="badge-err">فرق: {{ number_format($diff, 2) }}</span>
                        @endif
                    </td>
                </tr></table>
            </td>
        </tr>
    </table>

    {{-- ── Description ── --}}
    @if($je->description)
    <div class="desc-band">
        <div class="desc-lbl">الوصف / البيان</div>
        {{ $je->description }}
    </div>
    @endif

    {{-- ── Lines ── --}}
    <table class="lines-table">
        <thead>
            <tr>
                <th class="ctr" style="width:5%">#</th>
                <th style="width:13%">كود الحساب</th>
                <th style="width:37%">اسم الحساب</th>
                <th class="lft" style="width:20%">مدين</th>
                <th class="lft" style="width:20%">دائن</th>
                <th style="width:5%">البيان</th>
            </tr>
        </thead>
        <tbody>
            @foreach($je->lines as $i => $line)
            <tr>
                <td class="ctr">{{ $i + 1 }}</td>
                <td style="font-family:monospace; font-size:10px;">{{ $line->account?->code }}</td>
                <td>{{ $line->account?->name ?? '—' }}</td>
                <td class="{{ $line->debit  > 0 ? 'num' : 'num dim' }}">
                    {{ $line->debit  > 0 ? number_format($line->debit,  2) : '—' }}
                </td>
                <td class="{{ $line->credit > 0 ? 'num' : 'num dim' }}">
                    {{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}
                </td>
                <td class="dim">{{ $line->line_description ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right; color:white;">الإجمالي</td>
                <td class="num" style="color:white;">{{ number_format($je->debit_total,  2) }}</td>
                <td class="num" style="color:white;">{{ number_format($je->credit_total, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
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
