<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #111; direction: rtl; }
    .page { width: 148mm; padding: 8mm; margin: auto; }

    /* Header */
    .voucher-header { text-align: center; border-bottom: 2px solid #222; padding-bottom: 6px; margin-bottom: 10px; }
    .voucher-header .store-name { font-size: 16px; font-weight: bold; }
    .voucher-header .store-sub { font-size: 10px; color: #555; margin-top: 2px; }
    .voucher-title-bar {
        display: flex; justify-content: space-between; align-items: center;
        background: #1a2535; color: #fff;
        padding: 5px 10px; border-radius: 4px; margin-bottom: 10px;
    }
    .voucher-title-bar .title  { font-size: 14px; font-weight: bold; }
    .voucher-title-bar .number { font-size: 12px; }

    /* Info table */
    .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .info-table td { padding: 5px 8px; border: 1px solid #ddd; vertical-align: top; }
    .info-table .label { width: 30%; background: #f5f5f5; font-weight: bold; }

    /* Amount box */
    .amount-box {
        border: 2px solid #222; border-radius: 6px;
        padding: 10px 14px; margin: 10px 0; text-align: center;
    }
    .amount-box .label { font-size: 11px; color: #555; }
    .amount-box .value { font-size: 22px; font-weight: bold; color: #1a6b3c; }
    .amount-box .currency { font-size: 12px; }

    /* Journal lines */
    .je-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; }
    .je-table th { background: #1a2535; color: #fff; padding: 4px 6px; text-align: right; }
    .je-table td { padding: 4px 6px; border-bottom: 1px solid #eee; }
    .je-table .num { text-align: left; }

    /* Signature section */
    .signatures { display: flex; justify-content: space-between; margin-top: 16px; gap: 10px; }
    .sig-box { flex: 1; border-top: 1px solid #999; padding-top: 4px; text-align: center; font-size: 10px; color: #555; }

    /* Footer */
    .footer { text-align: center; margin-top: 14px; font-size: 9px; color: #aaa; border-top: 1px dashed #ccc; padding-top: 5px; }
</style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="voucher-header">
        <div class="store-name">{{ $storeName }}</div>
        @if($storeAddress)<div class="store-sub">{{ $storeAddress }}</div>@endif
        @if($storePhone)<div class="store-sub">هاتف: {{ $storePhone }}</div>@endif
    </div>

    {{-- Title bar --}}
    <div class="voucher-title-bar">
        <span class="title">سند قبض &nbsp;RECEIPT VOUCHER</span>
        <span class="number">{{ $receipt->voucher_number }}</span>
    </div>

    {{-- Info rows --}}
    <table class="info-table">
        <tr>
            <td class="label">التاريخ</td>
            <td>{{ $receipt->voucher_date->format('Y/m/d') }}</td>
            <td class="label">طريقة الاستلام</td>
            <td>{{ $receipt->paymentMethodLabel() }}</td>
        </tr>
        <tr>
            <td class="label">المستلم منه</td>
            <td colspan="3"><strong>{{ $receipt->received_from }}</strong></td>
        </tr>
        @if($receipt->reference)
        <tr>
            <td class="label">المرجع</td>
            <td colspan="3">{{ $receipt->reference }}</td>
        </tr>
        @endif
        @if($receipt->notes)
        <tr>
            <td class="label">البيان / وذلك عن</td>
            <td colspan="3">{{ $receipt->notes }}</td>
        </tr>
        @endif
    </table>

    {{-- Amount box --}}
    <div class="amount-box">
        <div class="label">مبلغ وقدره</div>
        <div class="value">{{ number_format($receipt->amount, 2) }}</div>
        <div class="currency">{{ $currency }}</div>
    </div>

    {{-- Journal lines --}}
    <table class="je-table">
        <thead>
            <tr>
                <th>الحساب</th>
                <th style="text-align:left;">مدين</th>
                <th style="text-align:left;">دائن</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $receipt->cashAccount?->code }} — {{ $receipt->cashAccount?->name }}</td>
                <td class="num">{{ number_format($receipt->amount, 2) }}</td>
                <td class="num">—</td>
            </tr>
            <tr>
                <td>{{ $receipt->account?->code }} — {{ $receipt->account?->name }}</td>
                <td class="num">—</td>
                <td class="num">{{ number_format($receipt->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Signatures --}}
    <div class="signatures">
        <div class="sig-box">المحاسب<br>Accountant</div>
        <div class="sig-box">المدير<br>Manager</div>
        <div class="sig-box">المستلم<br>Received by</div>
    </div>

    <div class="footer">
        {{ $storeName }} — سند قبض رقم {{ $receipt->voucher_number }}
        — {{ $receipt->created_at->format('Y-m-d H:i') }}
    </div>

</div>
</body>
</html>
